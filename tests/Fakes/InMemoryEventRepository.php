<?php

namespace Tests\Fakes;

use App\Domain\Events\Contracts\EventRepository;
use App\Domain\Events\DataTransferObjects\EventPayloadData;
use App\Domain\Events\DataTransferObjects\StoredEventData;
use App\Domain\Events\Enums\EventStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use RuntimeException;

final class InMemoryEventRepository implements EventRepository
{
    /**
     * @var array<string, StoredEventData>
     */
    private array $events = [];

    public function findById(string $eventId): ?StoredEventData
    {
        return $this->events[$eventId] ?? null;
    }

    public function findByIdempotencyKey(string $idempotencyKey): ?StoredEventData
    {
        foreach ($this->events as $event) {
            if ($event->idempotencyKey === $idempotencyKey) {
                return $event;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $statuses
     * @return array<int, StoredEventData>
     */
    public function list(array $statuses = [], ?string $eventName = null): array
    {
        $events = array_values(array_filter(
            $this->events,
            function (StoredEventData $event) use ($statuses, $eventName): bool {
                if ($statuses !== [] && ! in_array($event->status->value, $statuses, true)) {
                    return false;
                }

                if ($eventName !== null && $event->eventName !== $eventName) {
                    return false;
                }

                return true;
            },
        ));

        usort($events, fn (StoredEventData $left, StoredEventData $right): int => $right->createdAt <=> $left->createdAt);

        return $events;
    }

    public function create(EventPayloadData $payload): StoredEventData
    {
        $timestamp = CarbonImmutable::now();
        $event = new StoredEventData(
            id: (string) Str::uuid(),
            eventName: $payload->eventName,
            payload: $payload->payload,
            metadata: $payload->metadata,
            status: EventStatus::RECEIVED,
            idempotencyKey: $payload->idempotencyKey,
            contentHash: $payload->contentHash(),
            occurredAt: $payload->occurredAt,
            queuedAt: null,
            consumedAt: null,
            processedAt: null,
            processingAttempts: 0,
            processingResult: null,
            failureReason: null,
            createdAt: $timestamp,
            updatedAt: $timestamp,
        );

        $this->events[$event->id] = $event;

        return $event;
    }

    public function markAsQueued(string $eventId, CarbonImmutable $queuedAt, ?string $failureReason = null): StoredEventData
    {
        return $this->mutate($eventId, fn (StoredEventData $event): StoredEventData => new StoredEventData(
            id: $event->id,
            eventName: $event->eventName,
            payload: $event->payload,
            metadata: $event->metadata,
            status: EventStatus::QUEUED,
            idempotencyKey: $event->idempotencyKey,
            contentHash: $event->contentHash,
            occurredAt: $event->occurredAt,
            queuedAt: $queuedAt,
            consumedAt: $event->consumedAt,
            processedAt: $event->processedAt,
            processingAttempts: $event->processingAttempts,
            processingResult: $event->processingResult,
            failureReason: $failureReason,
            createdAt: $event->createdAt,
            updatedAt: CarbonImmutable::now(),
        ));
    }

    public function markAsPublishFailed(string $eventId, string $failureReason): StoredEventData
    {
        return $this->mutate($eventId, fn (StoredEventData $event): StoredEventData => new StoredEventData(
            id: $event->id,
            eventName: $event->eventName,
            payload: $event->payload,
            metadata: $event->metadata,
            status: EventStatus::PUBLISH_FAILED,
            idempotencyKey: $event->idempotencyKey,
            contentHash: $event->contentHash,
            occurredAt: $event->occurredAt,
            queuedAt: $event->queuedAt,
            consumedAt: $event->consumedAt,
            processedAt: $event->processedAt,
            processingAttempts: $event->processingAttempts,
            processingResult: $event->processingResult,
            failureReason: $failureReason,
            createdAt: $event->createdAt,
            updatedAt: CarbonImmutable::now(),
        ));
    }

    public function markAsProcessing(string $eventId, CarbonImmutable $consumedAt): StoredEventData
    {
        return $this->mutate($eventId, fn (StoredEventData $event): StoredEventData => new StoredEventData(
            id: $event->id,
            eventName: $event->eventName,
            payload: $event->payload,
            metadata: $event->metadata,
            status: EventStatus::PROCESSING,
            idempotencyKey: $event->idempotencyKey,
            contentHash: $event->contentHash,
            occurredAt: $event->occurredAt,
            queuedAt: $event->queuedAt,
            consumedAt: $consumedAt,
            processedAt: $event->processedAt,
            processingAttempts: $event->processingAttempts + 1,
            processingResult: $event->processingResult,
            failureReason: $event->failureReason,
            createdAt: $event->createdAt,
            updatedAt: CarbonImmutable::now(),
        ));
    }

    /**
     * @param  array<string, mixed>  $processingResult
     */
    public function markAsProcessed(string $eventId, CarbonImmutable $processedAt, array $processingResult): StoredEventData
    {
        return $this->mutate($eventId, fn (StoredEventData $event): StoredEventData => new StoredEventData(
            id: $event->id,
            eventName: $event->eventName,
            payload: $event->payload,
            metadata: $event->metadata,
            status: EventStatus::PROCESSED,
            idempotencyKey: $event->idempotencyKey,
            contentHash: $event->contentHash,
            occurredAt: $event->occurredAt,
            queuedAt: $event->queuedAt,
            consumedAt: $event->consumedAt,
            processedAt: $processedAt,
            processingAttempts: $event->processingAttempts,
            processingResult: $processingResult,
            failureReason: null,
            createdAt: $event->createdAt,
            updatedAt: CarbonImmutable::now(),
        ));
    }

    public function markAsProcessingFailed(string $eventId, string $failureReason): StoredEventData
    {
        return $this->mutate($eventId, fn (StoredEventData $event): StoredEventData => new StoredEventData(
            id: $event->id,
            eventName: $event->eventName,
            payload: $event->payload,
            metadata: $event->metadata,
            status: EventStatus::PROCESSING_FAILED,
            idempotencyKey: $event->idempotencyKey,
            contentHash: $event->contentHash,
            occurredAt: $event->occurredAt,
            queuedAt: $event->queuedAt,
            consumedAt: $event->consumedAt,
            processedAt: $event->processedAt,
            processingAttempts: $event->processingAttempts,
            processingResult: $event->processingResult,
            failureReason: $failureReason,
            createdAt: $event->createdAt,
            updatedAt: CarbonImmutable::now(),
        ));
    }

    /**
     * @param  callable(StoredEventData): StoredEventData  $callback
     */
    private function mutate(string $eventId, callable $callback): StoredEventData
    {
        $event = $this->events[$eventId] ?? null;

        if ($event === null) {
            throw new RuntimeException(sprintf('Evento %s nao encontrado no repositorio em memoria.', $eventId));
        }

        $updatedEvent = $callback($event);
        $this->events[$eventId] = $updatedEvent;

        return $updatedEvent;
    }
}
