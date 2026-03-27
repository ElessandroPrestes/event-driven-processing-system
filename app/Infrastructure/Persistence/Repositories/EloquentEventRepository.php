<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Events\Contracts\EventRepository;
use App\Domain\Events\DataTransferObjects\EventPayloadData;
use App\Domain\Events\DataTransferObjects\StoredEventData;
use App\Domain\Events\Enums\EventStatus;
use App\Infrastructure\Persistence\Models\EventRecord;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class EloquentEventRepository implements EventRepository
{
    public function findById(string $eventId): ?StoredEventData
    {
        $record = EventRecord::query()->find($eventId);

        return $record === null ? null : $this->toStoredEventData($record);
    }

    public function findByIdempotencyKey(string $idempotencyKey): ?StoredEventData
    {
        $record = EventRecord::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        return $record === null ? null : $this->toStoredEventData($record);
    }

    /**
     * @param  array<int, string>  $statuses
     * @return array<int, StoredEventData>
     */
    public function list(array $statuses = [], ?string $eventName = null, ?string $traceId = null): array
    {
        $query = EventRecord::query()
            ->orderByDesc('created_at');

        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        }

        if ($eventName !== null) {
            $query->where('event_name', $eventName);
        }

        if ($traceId !== null) {
            $query->where('trace_id', $traceId);
        }

        return $query
            ->get()
            ->map(fn (EventRecord $record): StoredEventData => $this->toStoredEventData($record))
            ->all();
    }

    public function create(EventPayloadData $payload): StoredEventData
    {
        $record = EventRecord::query()->create([
            'id' => (string) Str::uuid(),
            'trace_id' => $payload->traceId,
            'event_name' => $payload->eventName,
            'payload' => $payload->payload,
            'metadata' => $payload->metadata,
            'status' => EventStatus::RECEIVED,
            'idempotency_key' => $payload->idempotencyKey,
            'content_hash' => $payload->contentHash(),
            'occurred_at' => $payload->occurredAt,
        ]);

        return $this->toStoredEventData($record);
    }

    public function markAsQueued(string $eventId, CarbonImmutable $queuedAt, ?string $failureReason = null): StoredEventData
    {
        $record = EventRecord::query()->findOrFail($eventId);
        $record->forceFill([
            'status' => EventStatus::QUEUED,
            'queued_at' => $queuedAt,
            'failure_reason' => $failureReason === null ? null : Str::limit($failureReason, 1000),
        ])->save();

        /** @var EventRecord $freshRecord */
        $freshRecord = $record->fresh();

        return $this->toStoredEventData($freshRecord);
    }

    public function markAsPublishFailed(string $eventId, string $failureReason): StoredEventData
    {
        $record = EventRecord::query()->findOrFail($eventId);
        $record->forceFill([
            'status' => EventStatus::PUBLISH_FAILED,
            'failure_reason' => Str::limit($failureReason, 1000),
        ])->save();

        /** @var EventRecord $freshRecord */
        $freshRecord = $record->fresh();

        return $this->toStoredEventData($freshRecord);
    }

    public function markAsProcessing(string $eventId, CarbonImmutable $consumedAt): StoredEventData
    {
        $record = EventRecord::query()->findOrFail($eventId);
        $record->forceFill([
            'status' => EventStatus::PROCESSING,
            'consumed_at' => $consumedAt,
            'processing_attempts' => $record->processing_attempts + 1,
        ])->save();

        /** @var EventRecord $freshRecord */
        $freshRecord = $record->fresh();

        return $this->toStoredEventData($freshRecord);
    }

    /**
     * @param  array<string, mixed>  $processingResult
     */
    public function markAsProcessed(string $eventId, CarbonImmutable $processedAt, array $processingResult): StoredEventData
    {
        $record = EventRecord::query()->findOrFail($eventId);
        $record->forceFill([
            'status' => EventStatus::PROCESSED,
            'processed_at' => $processedAt,
            'processing_result' => $processingResult,
            'failure_reason' => null,
        ])->save();

        /** @var EventRecord $freshRecord */
        $freshRecord = $record->fresh();

        return $this->toStoredEventData($freshRecord);
    }

    public function markAsProcessingFailed(string $eventId, string $failureReason): StoredEventData
    {
        $record = EventRecord::query()->findOrFail($eventId);
        $record->forceFill([
            'status' => EventStatus::PROCESSING_FAILED,
            'failure_reason' => Str::limit($failureReason, 1000),
        ])->save();

        /** @var EventRecord $freshRecord */
        $freshRecord = $record->fresh();

        return $this->toStoredEventData($freshRecord);
    }

    private function toStoredEventData(EventRecord $record): StoredEventData
    {
        /** @var array<string, mixed> $payload */
        $payload = $record->payload ?? [];
        /** @var array<string, mixed>|null $metadata */
        $metadata = $record->metadata;
        /** @var array<string, mixed>|null $processingResult */
        $processingResult = $record->processing_result;
        /** @var CarbonImmutable|null $occurredAt */
        $occurredAt = $record->occurred_at;
        /** @var CarbonImmutable|null $queuedAt */
        $queuedAt = $record->queued_at;
        /** @var CarbonImmutable|null $consumedAt */
        $consumedAt = $record->consumed_at;
        /** @var CarbonImmutable|null $processedAt */
        $processedAt = $record->processed_at;
        /** @var CarbonImmutable $createdAt */
        $createdAt = $record->created_at;
        /** @var CarbonImmutable $updatedAt */
        $updatedAt = $record->updated_at;
        /** @var string $rawStatus */
        $rawStatus = $record->getRawOriginal('status');
        $status = EventStatus::from($rawStatus);
        /** @var int $processingAttempts */
        $processingAttempts = $record->processing_attempts ?? 0;

        return new StoredEventData(
            id: $record->id,
            traceId: $record->trace_id,
            eventName: $record->event_name,
            payload: $payload,
            metadata: $metadata,
            status: $status,
            idempotencyKey: $record->idempotency_key,
            contentHash: $record->content_hash,
            occurredAt: $occurredAt,
            queuedAt: $queuedAt,
            consumedAt: $consumedAt,
            processedAt: $processedAt,
            processingAttempts: $processingAttempts,
            processingResult: $processingResult,
            failureReason: $record->failure_reason,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }
}
