<?php

namespace App\Domain\Events\Contracts;

use App\Domain\Events\DataTransferObjects\EventPayloadData;
use App\Domain\Events\DataTransferObjects\StoredEventData;
use Carbon\CarbonImmutable;

interface EventRepository
{
    public function findById(string $eventId): ?StoredEventData;

    public function findByIdempotencyKey(string $idempotencyKey): ?StoredEventData;

    public function create(EventPayloadData $payload): StoredEventData;

    public function markAsQueued(string $eventId, CarbonImmutable $queuedAt): StoredEventData;

    public function markAsPublishFailed(string $eventId, string $failureReason): StoredEventData;
}
