<?php

namespace App\Infrastructure\Messaging\RabbitMq;

use App\Application\Events\Support\EventPayloadDataFactory;
use App\Domain\Events\DataTransferObjects\EventPayloadData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use RuntimeException;

final class RabbitMqInboundEventPayloadFactory
{
    /**
     * @var list<string>
     */
    private const array RESERVED_BODY_KEYS = [
        'event_name',
        'payload',
        'metadata',
        'occurred_at',
        'idempotency_key',
        'trace_id',
    ];

    public function __construct(
        private readonly EventPayloadDataFactory $payloads,
    ) {}

    public function fromMessage(AMQPMessage $message): EventPayloadData
    {
        $body = $this->decodeBody($message);
        $headers = $this->extractHeaders($message);
        $traceId = $this->resolveTraceId($message, $body, $headers) ?? (string) Str::uuid();

        return $this->payloads->fromArray([
            'event_name' => $this->extractEventName($message, $body),
            'payload' => $this->extractPayload($body),
            'metadata' => $this->extractMetadata($body),
            'occurred_at' => $this->extractOccurredAt($message, $body),
            'idempotency_key' => $this->extractIdempotencyKey($message, $body, $headers),
        ], $traceId);
    }

    public function traceIdFromMessage(AMQPMessage $message): ?string
    {
        $body = $this->decodeBody($message, false);
        $headers = $this->extractHeaders($message);

        return $this->resolveTraceId($message, $body, $headers);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeBody(AMQPMessage $message, bool $throwOnInvalidJson = true): array
    {
        $rawBody = $message->getBody();

        if ($rawBody === '') {
            return [];
        }

        $decoded = json_decode($rawBody, true);

        if (is_array($decoded) && ! array_is_list($decoded)) {
            return $decoded;
        }

        if ($throwOnInvalidJson) {
            throw new RuntimeException('A mensagem de ingestao RabbitMQ deve conter um objeto JSON valido.');
        }

        return [];
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
     * @param  array<string, mixed>  $body
     */
    private function extractEventName(AMQPMessage $message, array $body): ?string
    {
        $eventName = $body['event_name'] ?? null;

        if (is_string($eventName) && $eventName !== '') {
            return $eventName;
        }

        if ($message->has('type')) {
            $type = $message->get('type');

            if (is_string($type) && $type !== '') {
                return $type;
            }
        }

        $routingKey = $message->getRoutingKey();

        return $routingKey !== '' ? $routingKey : null;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>|mixed
     */
    private function extractPayload(array $body): mixed
    {
        if (array_key_exists('payload', $body)) {
            return $body['payload'];
        }

        $payload = $body;

        foreach (self::RESERVED_BODY_KEYS as $key) {
            unset($payload[$key]);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>|null
     */
    private function extractMetadata(array $body): ?array
    {
        $metadata = $body['metadata'] ?? null;

        return is_array($metadata) ? $metadata : null;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function extractOccurredAt(AMQPMessage $message, array $body): ?string
    {
        $occurredAt = $body['occurred_at'] ?? null;

        if (is_string($occurredAt) && $occurredAt !== '') {
            return $occurredAt;
        }

        if (! $message->has('timestamp')) {
            return null;
        }

        $timestamp = $message->get('timestamp');

        if (is_int($timestamp) || (is_string($timestamp) && ctype_digit($timestamp))) {
            return CarbonImmutable::createFromTimestampUTC((int) $timestamp)->toIso8601String();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $headers
     */
    private function extractIdempotencyKey(AMQPMessage $message, array $body, array $headers): ?string
    {
        foreach ([
            $body['idempotency_key'] ?? null,
            $headers['idempotency_key'] ?? null,
            $headers[(string) config('event_pipeline.api.idempotency_header')] ?? null,
            $message->has('message_id') ? $message->get('message_id') : null,
        ] as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $headers
     */
    private function resolveTraceId(AMQPMessage $message, array $body, array $headers): ?string
    {
        foreach ([
            $headers['trace_id'] ?? null,
            $headers[(string) config('event_pipeline.observability.trace_header')] ?? null,
            $body['trace_id'] ?? null,
            $message->has('correlation_id') ? $message->get('correlation_id') : null,
        ] as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof AMQPTable) {
            return $this->normalizeValue($value->getNativeData());
        }

        if (! is_array($value)) {
            return $value;
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            $normalized[$key] = $this->normalizeValue($item);
        }

        return $normalized;
    }
}
