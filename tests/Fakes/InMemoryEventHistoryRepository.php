<?php

namespace Tests\Fakes;

use App\Domain\Events\Contracts\EventHistoryRepository;
use App\Domain\Events\DataTransferObjects\EventHistoryEntryData;
use App\Domain\Events\DataTransferObjects\PaginatedEventHistoryData;
use App\Domain\Events\Enums\EventStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class InMemoryEventHistoryRepository implements EventHistoryRepository
{
    /**
     * @var array<string, list<EventHistoryEntryData>>
     */
    private array $entries = [];

    /**
     * @param  array<string, mixed>|null  $context
     */
    public function record(
        string $eventId,
        string $action,
        string $source,
        ?EventStatus $fromStatus,
        ?EventStatus $toStatus,
        ?array $context = null,
    ): EventHistoryEntryData {
        $entry = new EventHistoryEntryData(
            id: (string) Str::uuid(),
            eventId: $eventId,
            action: $action,
            source: $source,
            fromStatus: $fromStatus,
            toStatus: $toStatus,
            context: $context,
            createdAt: CarbonImmutable::now(),
        );

        $this->entries[$eventId] ??= [];
        $this->entries[$eventId][] = $entry;

        return $entry;
    }

    /**
     * @return array<int, EventHistoryEntryData>
     */
    public function listForEvent(string $eventId): array
    {
        return array_values($this->entries[$eventId] ?? []);
    }

    public function paginateForEvent(string $eventId, int $page = 1, int $perPage = 20): PaginatedEventHistoryData
    {
        $page = max($page, 1);
        $perPage = max($perPage, 1);
        $entries = $this->listForEvent($eventId);
        $total = count($entries);
        $lastPage = max(1, (int) ceil($total / $perPage));

        return new PaginatedEventHistoryData(
            items: array_slice($entries, ($page - 1) * $perPage, $perPage),
            currentPage: $page,
            perPage: $perPage,
            total: $total,
            lastPage: $lastPage,
        );
    }
}
