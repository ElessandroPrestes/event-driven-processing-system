<?php

namespace App\Domain\Events\DataTransferObjects;

use App\Domain\Events\Enums\EventStatus;
use Carbon\CarbonImmutable;

final readonly class StoredEventData
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $metadata
     * @param  array<string, mixed>|null  $processingResult
     */
    public function __construct(
        public string $id,
        public ?string $traceId,
        public string $eventName,
        public array $payload,
        public ?array $metadata,
        public EventStatus $status,
        public string $idempotencyKey,
        public string $contentHash,
        public ?CarbonImmutable $occurredAt,
        public ?CarbonImmutable $queuedAt,
        public ?CarbonImmutable $consumedAt,
        public ?CarbonImmutable $processedAt,
        public int $processingAttempts,
        public ?array $processingResult,
        public ?string $failureReason,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}
}
