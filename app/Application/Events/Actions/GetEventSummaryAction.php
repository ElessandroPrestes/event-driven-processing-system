<?php

namespace App\Application\Events\Actions;

use App\Application\Events\DataTransferObjects\EventSummaryData;
use App\Application\Events\Services\EventMetricsAggregator;
use App\Domain\Events\Contracts\EventRepository;
use App\Domain\Events\DataTransferObjects\EventListCriteriaData;

final class GetEventSummaryAction
{
    public function __construct(
        private readonly EventRepository $events,
        private readonly EventMetricsAggregator $metrics,
    ) {}

    public function handle(?string $eventName = null): EventSummaryData
    {
        $summary = $this->metrics->summarize($this->events->list(
            new EventListCriteriaData(eventName: $eventName),
        ));

        return new EventSummaryData(
            total: $summary->total,
            pending: $summary->pending,
            failed: $summary->failed,
            retryable: $summary->retryable,
            byStatus: $summary->byStatus,
            lastReceivedAt: $summary->lastReceivedAt,
            lastProcessedAt: $summary->lastProcessedAt,
            oldestPendingAt: $summary->oldestPendingAt,
        );
    }
}
