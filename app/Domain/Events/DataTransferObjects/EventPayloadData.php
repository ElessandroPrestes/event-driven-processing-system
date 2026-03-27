<?php

namespace App\Domain\Events\DataTransferObjects;

use Carbon\CarbonImmutable;

final readonly class EventPayloadData
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $eventName,
        public array $payload,
        public ?array $metadata,
        public string $idempotencyKey,
        public ?CarbonImmutable $occurredAt,
        public string $traceId,
    ) {}

    public function contentHash(): string
    {
        return hash('sha256', json_encode([
            'event_name' => $this->eventName,
            'payload' => self::normalizeValue($this->payload),
            'metadata' => self::normalizeValue($this->metadata),
            'occurred_at' => $this->occurredAt?->toIso8601String(),
        ], JSON_THROW_ON_ERROR));
    }

    private static function normalizeValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(self::normalizeValue(...), $value);
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = self::normalizeValue($item);
        }

        return $value;
    }
}
