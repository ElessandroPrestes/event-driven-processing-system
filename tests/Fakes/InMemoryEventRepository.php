<?php

namespace Tests\Fakes;

use App\Domain\Events\Contracts\EventRepository;
use App\Domain\Events\DataTransferObjects\EventListCriteriaData;
use App\Domain\Events\DataTransferObjects\EventPayloadData;
use App\Domain\Events\DataTransferObjects\PaginatedEventsData;
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
     * @return array<int, StoredEventData>
     */
    public function list(?EventListCriteriaData $criteria = null): array
    {
        $events = $this->filteredEvents($criteria);
        $this->sortEvents($events);

        return $events;
    }

    public function paginate(EventListCriteriaData $criteria): PaginatedEventsData
    {
        $page = max($criteria->page, 1);
        $perPage = max($criteria->perPage, 1);
        $events = $this->filteredEvents($criteria);

        $this->sortEvents($events);

        $total = count($events);
        $lastPage = max(1, (int) ceil($total / $perPage));

        return new PaginatedEventsData(
            items: array_slice($events, ($page - 1) * $perPage, $perPage),
            currentPage: $page,
            perPage: $perPage,
            total: $total,
            lastPage: $lastPage,
        );
    }

    public function create(EventPayloadData $payload): StoredEventData
    {
        $timestamp = CarbonImmutable::now();
        $event = new StoredEventData(
            id: (string) Str::uuid(),
            traceId: $payload->traceId,
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
            traceId: $event->traceId,
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
            traceId: $event->traceId,
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
            traceId: $event->traceId,
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
            traceId: $event->traceId,
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
            traceId: $event->traceId,
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

    /**
     * @return array<int, StoredEventData>
     */
    private function filteredEvents(?EventListCriteriaData $criteria = null): array
    {
        $statuses = $criteria?->statuses ?? [];
        $eventName = $criteria?->eventName;
        $traceId = $criteria?->traceId;

        return array_values(array_filter(
            $this->events,
            function (StoredEventData $event) use ($statuses, $eventName, $traceId): bool {
                if ($statuses !== [] && ! in_array($event->status->value, $statuses, true)) {
                    return false;
                }

                if ($eventName !== null && $event->eventName !== $eventName) {
                    return false;
                }

                if ($traceId !== null && $event->traceId !== $traceId) {
                    return false;
                }

                return true;
            },
        ));
    }

    /**
     * @param  array<int, StoredEventData>  $events
     */
    private function sortEvents(array &$events): void
    {
        usort($events, function (StoredEventData $left, StoredEventData $right): int {
            $byCreatedAt = $right->createdAt <=> $left->createdAt;

            if ($byCreatedAt !== 0) {
                return $byCreatedAt;
            }

            return strcmp($right->id, $left->id);
        });
    }
}
