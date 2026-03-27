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
use App\Infrastructure\Messaging\RabbitMq\Contracts\AmqpConnectionFactory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

final class RabbitMqEventQuarantine implements EventQuarantineManager
{
    private const int DEFAULT_MAX_LIMIT = 50;

    public function __construct(
        private readonly EventRepository $events,
        private readonly EventHistoryRecorder $history,
        private readonly AmqpConnectionFactory $connections,
        private readonly RabbitMqEventMessageFactory $messages,
        private readonly RabbitMqQuarantinedMessageFactory $quarantinedMessages,
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

    /**
     * @param  array<int, string>  $messageIds
     */
    public function replay(int $limit, array $messageIds = []): QuarantineReplayResultData
    {
        $limit = max(1, min($limit, self::DEFAULT_MAX_LIMIT));
        $targetMessageIds = $this->normalizeTargetMessageIds($messageIds);
        /** @var array<string, bool> $pendingMessageIds */
        $pendingMessageIds = array_fill_keys($targetMessageIds, true);
        $replayed = [];
        $deferredMessages = [];
        $stoppedReason = null;
        $connection = $this->connections->make();
        $channel = $connection->channel();

        try {
            $queue = $this->prepareDeadLetterQueue($channel);
            $iterations = $targetMessageIds === []
                ? min($limit, $queue['depth'])
                : $queue['depth'];

            for ($index = 0; $index < $iterations; $index++) {
                $message = $channel->basic_get($queue['name'], false);

                if (! $message instanceof AMQPMessage) {
                    break;
                }

                $snapshot = $this->snapshotMessage($message);

                if ($this->shouldDeferMessage($pendingMessageIds, $snapshot->messageId)) {
                    $deferredMessages[] = $message;

                    continue;
                }

                try {
                    $replayedMessage = $this->replayMessage($channel, $snapshot);
                    $message->ack();
                    $replayed[] = $replayedMessage;

                    if ($replayedMessage->messageId !== null) {
                        unset($pendingMessageIds[$replayedMessage->messageId]);
                    }

                    if ($targetMessageIds !== [] && $pendingMessageIds === []) {
                        break;
                    }
                } catch (Throwable $exception) {
                    $message->nack(true);
                    $stoppedReason = $exception->getMessage();
                    $this->logReplayFailure($queue['name'], $snapshot, $exception);

                    break;
                }
            }

            $this->requeueMessages($deferredMessages);

            $remainingDepth = $this->currentQueueDepth($channel, $queue['name']);
            $missingMessageIds = array_keys($pendingMessageIds);

            Log::info('event.quarantine_replayed', [
                'queue' => $queue['name'],
                'requested' => $limit,
                'target_message_ids' => $targetMessageIds,
                'replayed' => count($replayed),
                'missing_message_ids' => $missingMessageIds,
                'remaining_depth' => $remainingDepth,
                'stopped_reason' => $stoppedReason,
            ]);

            return new QuarantineReplayResultData(
                requested: $limit,
                replayedCount: count($replayed),
                remainingDepth: $remainingDepth,
                messages: $replayed,
                stoppedReason: $stoppedReason,
                missingMessageIds: $missingMessageIds,
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

    /**
     * @param  array<int, AMQPMessage>  $messages
     */
    private function requeueMessages(array $messages): void
    {
        foreach (array_reverse($messages) as $message) {
            $message->nack(true);
        }
    }

    /**
     * @param  array<int, string>  $messageIds
     * @return array<int, string>
     */
    private function normalizeTargetMessageIds(array $messageIds): array
    {
        return array_values(array_unique(array_filter(
            $messageIds,
            static fn (string $messageId): bool => $messageId !== '',
        )));
    }

    /**
     * @param  array<string, bool>  $pendingMessageIds
     */
    private function shouldDeferMessage(array $pendingMessageIds, ?string $messageId): bool
    {
        if ($pendingMessageIds === []) {
            return false;
        }

        if ($messageId === null) {
            return true;
        }

        return ! array_key_exists($messageId, $pendingMessageIds);
    }

    private function logReplayFailure(
        string $queue,
        QuarantinedMessageData $snapshot,
        Throwable $exception,
    ): void {
        Log::error('event.quarantine_replay_failed', [
            'queue' => $queue,
            'message_id' => $snapshot->messageId,
            'event_id' => $snapshot->eventId,
            'event_name' => $snapshot->eventName,
            'error' => $exception->getMessage(),
        ]);
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
        $replayedAt = CarbonImmutable::now()->toIso8601String();

        $channel->basic_publish(
            msg: $this->quarantinedMessages->makeReplayMessage($snapshot, $replayedAt),
            exchange: (string) config('event_pipeline.rabbitmq.exchange'),
            routing_key: $this->quarantinedMessages->resolveReplayRoutingKey($snapshot),
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
        $snapshot = $this->quarantinedMessages->fromAmqpMessage($message);
        $persistedEvent = $snapshot->eventId === null ? null : $this->events->findById($snapshot->eventId);

        return $snapshot->withPersistedEventStatus($persistedEvent?->status);
    }
}
