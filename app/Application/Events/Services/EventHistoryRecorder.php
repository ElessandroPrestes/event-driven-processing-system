<?php

namespace App\Application\Events\Services;

use App\Domain\Events\Contracts\EventHistoryRepository;
use App\Domain\Events\DataTransferObjects\EventHistoryEntryData;
use App\Domain\Events\DataTransferObjects\StoredEventData;
use App\Domain\Events\Enums\EventStatus;

final class EventHistoryRecorder
{
    public function __construct(
        private readonly EventHistoryRepository $history,
    ) {}

    /**
     * @param  array<string, mixed>|null  $context
     */
    public function record(
        StoredEventData $event,
        string $action,
        string $source,
        ?EventStatus $fromStatus = null,
        ?array $context = null,
    ): EventHistoryEntryData {
        return $this->history->record(
            eventId: $event->id,
            action: $action,
            source: $source,
            fromStatus: $fromStatus,
            toStatus: $event->status,
            context: $context,
        );
    }
}
