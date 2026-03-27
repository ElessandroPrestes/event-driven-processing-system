<?php

namespace App\Domain\Events\Contracts;

use App\Domain\Events\DataTransferObjects\EventPayloadData;
use App\Domain\Events\DataTransferObjects\StoredEventData;
use Carbon\CarbonImmutable;

interface EventRepository
{
    public function findById(string $eventId): ?StoredEventData;

    public function findByIdempotencyKey(string $idempotencyKey): ?StoredEventData;

    /**
     * @param  array<int, string>  $statuses
     * @return array<int, StoredEventData>
     */
    public function list(array $statuses = [], ?string $eventName = null, ?string $traceId = null): array;

    public function create(EventPayloadData $payload): StoredEventData;

    public function markAsQueued(string $eventId, CarbonImmutable $queuedAt, ?string $failureReason = null): StoredEventData;

    public function markAsPublishFailed(string $eventId, string $failureReason): StoredEventData;

    public function markAsProcessing(string $eventId, CarbonImmutable $consumedAt): StoredEventData;

    /**
     * @param  array<string, mixed>  $processingResult
     */
    public function markAsProcessed(string $eventId, CarbonImmutable $processedAt, array $processingResult): StoredEventData;

    public function markAsProcessingFailed(string $eventId, string $failureReason): StoredEventData;
}
