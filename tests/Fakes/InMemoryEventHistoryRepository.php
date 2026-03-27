<?php

namespace Tests\Fakes;

use App\Domain\Events\Contracts\EventHistoryRepository;
use App\Domain\Events\DataTransferObjects\EventHistoryEntryData;
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
        return $this->entries[$eventId] ?? [];
    }
}
