<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Events\Contracts\EventHistoryRepository;
use App\Domain\Events\DataTransferObjects\EventHistoryEntryData;
use App\Domain\Events\DataTransferObjects\PaginatedEventHistoryData;
use App\Domain\Events\Enums\EventStatus;
use App\Infrastructure\Persistence\Models\EventHistoryRecord;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

final class EloquentEventHistoryRepository implements EventHistoryRepository
{
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
        $record = EventHistoryRecord::query()->create([
            'id' => (string) Str::orderedUuid(),
            'event_id' => $eventId,
            'action' => $action,
            'source' => $source,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'context' => $context,
            'created_at' => CarbonImmutable::now(),
        ]);

        return $this->toData($record);
    }

    /**
     * @return array<int, EventHistoryEntryData>
     */
    public function listForEvent(string $eventId): array
    {
        return $this->queryForEvent($eventId)
            ->get()
            ->map(fn (EventHistoryRecord $record): EventHistoryEntryData => $this->toData($record))
            ->all();
    }

    public function paginateForEvent(string $eventId, int $page = 1, int $perPage = 20): PaginatedEventHistoryData
    {
        $page = max($page, 1);
        $perPage = max($perPage, 1);

        $paginator = $this->queryForEvent($eventId)
            ->paginate($perPage, ['*'], 'page', $page);

        return $this->toPaginatedData($paginator);
    }

    /**
     * @return Builder<EventHistoryRecord>
     */
    private function queryForEvent(string $eventId): Builder
    {
        return EventHistoryRecord::query()
            ->where('event_id', $eventId)
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /**
     * @param  LengthAwarePaginator<int, EventHistoryRecord>  $paginator
     */
    private function toPaginatedData(LengthAwarePaginator $paginator): PaginatedEventHistoryData
    {
        /** @var array<int, EventHistoryRecord> $items */
        $items = $paginator->items();

        return new PaginatedEventHistoryData(
            items: array_map(
                fn (EventHistoryRecord $record): EventHistoryEntryData => $this->toData($record),
                $items,
            ),
            currentPage: $paginator->currentPage(),
            perPage: $paginator->perPage(),
            total: $paginator->total(),
            lastPage: $paginator->lastPage(),
        );
    }

    private function toData(EventHistoryRecord $record): EventHistoryEntryData
    {
        /** @var EventStatus|null $fromStatus */
        $fromStatus = $record->from_status;
        /** @var EventStatus|null $toStatus */
        $toStatus = $record->to_status;
        /** @var array<string, mixed>|null $context */
        $context = $record->context;
        /** @var CarbonImmutable $createdAt */
        $createdAt = $record->created_at;

        return new EventHistoryEntryData(
            id: $record->id,
            eventId: $record->event_id,
            action: $record->action,
            source: $record->source,
            fromStatus: $fromStatus,
            toStatus: $toStatus,
            context: $context,
            createdAt: $createdAt,
        );
    }
}
