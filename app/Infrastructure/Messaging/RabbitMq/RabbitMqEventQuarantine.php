<?php

namespace App\Infrastructure\Messaging\RabbitMq;

use App\Application\Events\Contracts\EventQuarantineManager;
use App\Application\Events\DataTransferObjects\QuarantinedMessageData;
use App\Application\Events\DataTransferObjects\QuarantineInspectionData;
use App\Application\Events\DataTransferObjects\QuarantineReplayResultData;
use App\Application\Events\Services\EventHistoryRecorder;
use App\Domain\Events\Contracts\EventRepository;
use App\Domain\Events\DataTransferObjects\StoredEventData;
use App\Domain\Events\Enums\EventStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Throwable;

final class RabbitMqEventQuarantine implements EventQuarantineManager
{
    private const int DEFAULT_MAX_LIMIT = 50;

    public function __construct(
        private readonly EventRepository $events,
        private readonly EventHistoryRecorder $history,
        private readonly RabbitMqConnectionFactory $connections,
        private readonly RabbitMqEventMessageFactory $messages,
        private readonly RabbitMqTopologyManager $topology,
    ) {}

    public function inspect(int $limit): QuarantineInspectionData
    {
        $limit = max(1, min($limit, self::DEFAULT_MAX_LIMIT));
        $connection = $this->connections->make();
        $channel = $connection->channel();

        try {
            $queue = $this->prepareDeadLetterQueue($channel);
            $messages = [];
            $snapshots = [];

            for ($index = 0; $index < min($limit, $queue['depth']); $index++) {
                $message = $channel->basic_get($queue['name'], false);

                if (! $message instanceof AMQPMessage) {
                    break;
                }

                $messages[] = $message;
                $snapshots[] = $this->snapshotMessage($message);
            }

            foreach (array_reverse($messages) as $message) {
                $message->nack(true);
            }

            Log::info('event.quarantine_inspected', [
                'queue' => $queue['name'],
                'limit' => $limit,
                'returned' => count($snapshots),
                'depth' => $queue['depth'],
            ]);

            return new QuarantineInspectionData(
                depth: $queue['depth'],
                limit: $limit,
                messages: $snapshots,
            );
        } finally {
            $channel->close();
            $connection->close();
        }
    }

    public function replay(int $limit): QuarantineReplayResultData
    {
        $limit = max(1, min($limit, self::DEFAULT_MAX_LIMIT));
        $connection = $this->connections->make();
        $channel = $connection->channel();

        try {
            $queue = $this->prepareDeadLetterQueue($channel);
            $replayed = [];
            $stoppedReason = null;

            for ($index = 0; $index < min($limit, $queue['depth']); $index++) {
                $message = $channel->basic_get($queue['name'], false);

                if (! $message instanceof AMQPMessage) {
                    break;
                }

                $snapshot = $this->snapshotMessage($message);

                try {
                    $replayed[] = $this->replayMessage($channel, $snapshot);
                    $message->ack();
                } catch (Throwable $exception) {
                    $message->nack(true);
                    $stoppedReason = $exception->getMessage();

                    Log::error('event.quarantine_replay_failed', [
                        'queue' => $queue['name'],
                        'message_id' => $snapshot->messageId,
                        'event_id' => $snapshot->eventId,
                        'event_name' => $snapshot->eventName,
                        'error' => $exception->getMessage(),
                    ]);

                    break;
                }
            }

            $remainingDepth = $this->currentQueueDepth($channel, $queue['name']);

            Log::info('event.quarantine_replayed', [
                'queue' => $queue['name'],
                'requested' => $limit,
                'replayed' => count($replayed),
                'remaining_depth' => $remainingDepth,
                'stopped_reason' => $stoppedReason,
            ]);

            return new QuarantineReplayResultData(
                requested: $limit,
                replayedCount: count($replayed),
                remainingDepth: $remainingDepth,
                messages: $replayed,
                stoppedReason: $stoppedReason,
            );
        } finally {
            $channel->close();
            $connection->close();
        }
    }

    /**
     * @return array{name: string, depth: int}
     */
    private function prepareDeadLetterQueue(AMQPChannel $channel): array
    {
        $this->topology->declare($channel);

        $queue = (string) config('event_pipeline.rabbitmq.dead_letter_queue');
        $durable = (bool) config('event_pipeline.rabbitmq.durable');
        $declared = $channel->queue_declare($queue, false, $durable, false, false);
        $depth = isset($declared[1]) ? (int) $declared[1] : 0;

        return [
            'name' => $queue,
            'depth' => $depth,
        ];
    }

    private function currentQueueDepth(AMQPChannel $channel, string $queue): int
    {
        $durable = (bool) config('event_pipeline.rabbitmq.durable');
        $declared = $channel->queue_declare($queue, false, $durable, false, false);

        return isset($declared[1]) ? (int) $declared[1] : 0;
    }

    private function replayMessage(AMQPChannel $channel, QuarantinedMessageData $snapshot): QuarantinedMessageData
    {
        $event = $snapshot->eventId === null ? null : $this->events->findById($snapshot->eventId);

        if ($this->shouldReplayStoredEvent($event)) {
            return $this->replayStoredEvent($channel, $event, $snapshot);
        }

        return $this->replayRawMessage($channel, $snapshot);
    }

    private function replayStoredEvent(
        AMQPChannel $channel,
        StoredEventData $event,
        QuarantinedMessageData $snapshot,
    ): QuarantinedMessageData {
        $this->history->record(
            event: $event,
            action: 'quarantine_replay_requested',
            source: 'api',
            fromStatus: $event->status,
            context: [
                'strategy' => 'stored_event',
            ],
        );

        $channel->basic_publish(
            msg: $this->messages->make(
                $event,
                applicationHeaders: [
                    'quarantine_replay_source' => 'api',
                    'quarantine_replayed_at' => CarbonImmutable::now()->toIso8601String(),
                ],
            ),
            exchange: (string) config('event_pipeline.rabbitmq.exchange'),
            routing_key: $event->eventName,
        );

        $queuedEvent = $this->events->markAsQueued($event->id, CarbonImmutable::now());

        $this->history->record(
            event: $queuedEvent,
            action: 'quarantine_replayed',
            source: 'api',
            fromStatus: $event->status,
            context: [
                'strategy' => 'stored_event',
                'queued_at' => $queuedEvent->queuedAt?->toIso8601String(),
            ],
        );

        return $snapshot->withReplayStrategy('stored_event', $queuedEvent->status);
    }

    private function replayRawMessage(AMQPChannel $channel, QuarantinedMessageData $snapshot): QuarantinedMessageData
    {
        $channel->basic_publish(
            msg: $this->makeReplayMessage($snapshot),
            exchange: (string) config('event_pipeline.rabbitmq.exchange'),
            routing_key: $this->resolveReplayRoutingKey($snapshot),
        );

        return $snapshot->withReplayStrategy('raw_message');
    }

    private function shouldReplayStoredEvent(?StoredEventData $event): bool
    {
        return $event !== null
            && in_array($event->status, [EventStatus::PROCESSING_FAILED, EventStatus::PUBLISH_FAILED], true);
    }

    private function snapshotMessage(AMQPMessage $message): QuarantinedMessageData
    {
        $body = json_decode($message->getBody(), true);
        $decodedBody = is_array($body) ? $body : null;
        $headers = $this->extractHeaders($message);
        $eventId = $this->extractEventId($message, $decodedBody);
        $traceId = $this->extractTraceId($headers, $decodedBody);
        $eventName = $this->extractEventName($message, $decodedBody);
        $deadLetterHistory = $this->extractDeadLetterHistory($headers);
        $persistedEvent = $eventId === null ? null : $this->events->findById($eventId);

        return new QuarantinedMessageData(
            messageId: $this->extractMessageId($message),
            eventId: $eventId,
            traceId: $traceId,
            eventName: $eventName,
            exchange: $message->getExchange(),
            routingKey: $message->getRoutingKey(),
            body: $decodedBody,
            rawBody: $message->getBody(),
            headers: $headers,
            deadLetterHistory: $deadLetterHistory,
            deadLetterReason: $this->extractDeadLetterReason($headers, $deadLetterHistory),
            persistedEventStatus: $persistedEvent?->status,
        );
    }

    private function makeReplayMessage(QuarantinedMessageData $snapshot): AMQPMessage
    {
        $properties = [
            'content_type' => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'timestamp' => time(),
        ];

        if ($snapshot->messageId !== null) {
            $properties['message_id'] = $snapshot->messageId;
        }

        if ($snapshot->eventName !== null) {
            $properties['type'] = $snapshot->eventName;
        }

        $headers = $this->sanitizeReplayHeaders($snapshot->headers);
        $headers['quarantine_replay_source'] = 'api';
        $headers['quarantine_replayed_at'] = CarbonImmutable::now()->toIso8601String();

        $properties['application_headers'] = new AMQPTable($headers);

        return new AMQPMessage($snapshot->rawBody, $properties);
    }

    private function resolveReplayRoutingKey(QuarantinedMessageData $snapshot): string
    {
        if ($snapshot->eventName !== null && $snapshot->eventName !== '') {
            return $snapshot->eventName;
        }

        foreach ($snapshot->deadLetterHistory ?? [] as $entry) {
            $routingKeys = $entry['routing-keys'] ?? null;

            if (is_array($routingKeys) && isset($routingKeys[0]) && is_string($routingKeys[0]) && $routingKeys[0] !== '') {
                return $routingKeys[0];
            }
        }

        return $snapshot->routingKey;
    }

    private function extractMessageId(AMQPMessage $message): ?string
    {
        if (! $message->has('message_id')) {
            return null;
        }

        $messageId = $message->get('message_id');

        return is_string($messageId) && $messageId !== '' ? $messageId : null;
    }

    /**
     * @param  array<string, mixed>|null  $body
     */
    private function extractEventId(AMQPMessage $message, ?array $body): ?string
    {
        $messageId = $this->extractMessageId($message);

        if ($messageId !== null) {
            return $messageId;
        }

        $eventId = $body['id'] ?? null;

        return is_string($eventId) && $eventId !== '' ? $eventId : null;
    }

    /**
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>|null  $body
     */
    private function extractTraceId(array $headers, ?array $body): ?string
    {
        $traceId = $headers['trace_id'] ?? $body['trace_id'] ?? null;

        return is_string($traceId) && $traceId !== '' ? $traceId : null;
    }

    /**
     * @param  array<string, mixed>|null  $body
     */
    private function extractEventName(AMQPMessage $message, ?array $body): ?string
    {
        if ($message->has('type')) {
            $type = $message->get('type');

            if (is_string($type) && $type !== '') {
                return $type;
            }
        }

        $eventName = $body['event_name'] ?? null;

        return is_string($eventName) && $eventName !== '' ? $eventName : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractHeaders(AMQPMessage $message): array
    {
        if (! $message->has('application_headers')) {
            return [];
        }

        $headers = $message->get('application_headers');

        if (! $headers instanceof AMQPTable) {
            return [];
        }

        $normalized = $this->normalizeValue($headers->getNativeData());

        return is_array($normalized) ? $normalized : [];
    }

    /**
     * @param  array<string, mixed>  $headers
     * @return array<int, array<string, mixed>>|null
     */
    private function extractDeadLetterHistory(array $headers): ?array
    {
        $history = $headers['x-death'] ?? null;

        if (! is_array($history)) {
            return null;
        }

        $entries = array_values(array_filter($history, static fn (mixed $entry): bool => is_array($entry)));

        return $entries === [] ? null : $entries;
    }

    /**
     * @param  array<string, mixed>  $headers
     * @param  array<int, array<string, mixed>>|null  $deadLetterHistory
     */
    private function extractDeadLetterReason(array $headers, ?array $deadLetterHistory): ?string
    {
        $firstDeathReason = $headers['x-first-death-reason'] ?? null;

        if (is_string($firstDeathReason) && $firstDeathReason !== '') {
            return $firstDeathReason;
        }

        $reason = $deadLetterHistory[0]['reason'] ?? null;

        return is_string($reason) && $reason !== '' ? $reason : null;
    }

    /**
     * @param  array<string, mixed>  $headers
     * @return array<string, mixed>
     */
    private function sanitizeReplayHeaders(array $headers): array
    {
        return array_filter(
            $headers,
            static fn (string $key): bool => ! str_starts_with($key, 'x-'),
            ARRAY_FILTER_USE_KEY,
        );
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof AMQPTable) {
            return $this->normalizeValue($value->getNativeData());
        }

        if (is_array($value)) {
            $normalized = [];

            foreach ($value as $key => $item) {
                $normalized[$key] = $this->normalizeValue($item);
            }

            return $normalized;
        }

        return $value;
    }
}
