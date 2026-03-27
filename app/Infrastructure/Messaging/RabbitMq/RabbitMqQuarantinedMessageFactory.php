<?php

namespace App\Infrastructure\Messaging\RabbitMq;

use App\Application\Events\DataTransferObjects\QuarantinedMessageData;
use App\Domain\Events\Enums\EventStatus;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

final class RabbitMqQuarantinedMessageFactory
{
    public function fromAmqpMessage(AMQPMessage $message, ?EventStatus $persistedEventStatus = null): QuarantinedMessageData
    {
        $body = json_decode($message->getBody(), true);
        $decodedBody = is_array($body) ? $body : null;
        $headers = $this->extractHeaders($message);
        $deadLetterHistory = $this->extractDeadLetterHistory($headers);

        return new QuarantinedMessageData(
            messageId: $this->extractMessageId($message),
            eventId: $this->extractEventId($message, $decodedBody),
            traceId: $this->extractTraceId($headers, $decodedBody),
            eventName: $this->extractEventName($message, $decodedBody),
            exchange: $message->getExchange(),
            routingKey: $message->getRoutingKey(),
            body: $decodedBody,
            rawBody: $message->getBody(),
            headers: $headers,
            deadLetterHistory: $deadLetterHistory,
            deadLetterReason: $this->extractDeadLetterReason($headers, $deadLetterHistory),
            persistedEventStatus: $persistedEventStatus,
        );
    }

    public function makeReplayMessage(QuarantinedMessageData $snapshot, string $replayedAt): AMQPMessage
    {
        $properties = [
            'content_type' => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'timestamp' => time(),
            'application_headers' => new AMQPTable(array_merge(
                $this->sanitizeReplayHeaders($snapshot->headers),
                [
                    'quarantine_replay_source' => 'api',
                    'quarantine_replayed_at' => $replayedAt,
                ],
            )),
        ];

        if ($snapshot->messageId !== null) {
            $properties['message_id'] = $snapshot->messageId;
        }

        if ($snapshot->eventName !== null) {
            $properties['type'] = $snapshot->eventName;
        }

        return new AMQPMessage($snapshot->rawBody, $properties);
    }

    public function resolveReplayRoutingKey(QuarantinedMessageData $snapshot): string
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
