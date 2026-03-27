<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Events\Contracts\EventHistoryRepository;
use App\Domain\Events\DataTransferObjects\EventHistoryEntryData;
use App\Domain\Events\Enums\EventStatus;
use App\Infrastructure\Persistence\Models\EventHistoryRecord;
use Carbon\CarbonImmutable;
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
            'id' => (string) Str::uuid(),
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
        return EventHistoryRecord::query()
            ->where('event_id', $eventId)
            ->orderBy('created_at')
            ->get()
            ->map(fn (EventHistoryRecord $record): EventHistoryEntryData => $this->toData($record))
            ->all();
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
