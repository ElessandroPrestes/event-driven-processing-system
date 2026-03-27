<?php

namespace App\Application\Events\Actions;

use App\Application\Events\DataTransferObjects\EventSummaryData;
use App\Domain\Events\Contracts\EventRepository;
use App\Domain\Events\Enums\EventStatus;
use Carbon\CarbonImmutable;

final class GetEventSummaryAction
{
    public function __construct(
        private readonly EventRepository $events,
    ) {}

    public function handle(?string $eventName = null): EventSummaryData
    {
        $events = $this->events->list([], $eventName);
        $byStatus = $this->emptyStatusMap();
        $lastReceivedAt = null;
        $lastProcessedAt = null;
        $oldestPendingAt = null;

        foreach ($events as $event) {
            $byStatus[$event->status->value]++;
            $lastReceivedAt = $this->latest($lastReceivedAt, $event->createdAt);
            $lastProcessedAt = $this->latest($lastProcessedAt, $event->processedAt);

            if (in_array($event->status, [EventStatus::RECEIVED, EventStatus::QUEUED, EventStatus::PROCESSING], true)) {
                $oldestPendingAt = $this->oldest($oldestPendingAt, $event->queuedAt ?? $event->createdAt);
            }
        }

        return new EventSummaryData(
            total: count($events),
            pending: $byStatus[EventStatus::RECEIVED->value] + $byStatus[EventStatus::QUEUED->value] + $byStatus[EventStatus::PROCESSING->value],
            failed: $byStatus[EventStatus::PROCESSING_FAILED->value] + $byStatus[EventStatus::PUBLISH_FAILED->value],
            retryable: $byStatus[EventStatus::PROCESSING_FAILED->value] + $byStatus[EventStatus::PUBLISH_FAILED->value],
            byStatus: $byStatus,
            lastReceivedAt: $lastReceivedAt,
            lastProcessedAt: $lastProcessedAt,
            oldestPendingAt: $oldestPendingAt,
        );
    }

    /**
     * @return array<string, int>
     */
    private function emptyStatusMap(): array
    {
        $counts = [];

        foreach (EventStatus::cases() as $status) {
            $counts[$status->value] = 0;
        }

        return $counts;
    }

    private function latest(?CarbonImmutable $current, ?CarbonImmutable $candidate): ?CarbonImmutable
    {
        if ($candidate === null) {
            return $current;
        }

        if ($current === null || $candidate->greaterThan($current)) {
            return $candidate;
        }

        return $current;
    }

    private function oldest(?CarbonImmutable $current, CarbonImmutable $candidate): CarbonImmutable
    {
        if ($current === null || $candidate->lessThan($current)) {
            return $candidate;
        }

        return $current;
    }
}
