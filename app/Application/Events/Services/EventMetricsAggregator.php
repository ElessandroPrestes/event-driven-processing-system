<?php

namespace App\Application\Events\Services;

use App\Application\Events\DataTransferObjects\EventMetricsBucket;
use App\Domain\Events\DataTransferObjects\StoredEventData;
use App\Domain\Events\Enums\EventStatus;
use Carbon\CarbonImmutable;

final class EventMetricsAggregator
{
    public const string AggregateBucketName = 'all';

    /**
     * @param  array<int, StoredEventData>  $events
     */
    public function summarize(array $events): EventMetricsBucket
    {
        $bucket = new EventMetricsBucket;

        foreach ($events as $event) {
            $this->accumulate($bucket, $event);
        }

        return $bucket;
    }

    /**
     * @param  array<int, StoredEventData>  $events
     * @return array<string, EventMetricsBucket>
     */
    public function groupByEventName(array $events): array
    {
        $buckets = $this->initializeBuckets($events);

        foreach ($events as $event) {
            $this->accumulate($buckets[self::AggregateBucketName], $event);
            $this->accumulate($buckets[$event->eventName], $event);
        }

        return $buckets;
    }

    /**
     * @param  array<int, StoredEventData>  $events
     * @return array<string, EventMetricsBucket>
     */
    private function initializeBuckets(array $events): array
    {
        $presentEventNames = array_values(array_unique(array_map(
            static fn (StoredEventData $event): string => $event->eventName,
            $events,
        )));

        $dynamicEventNames = array_values(array_diff($presentEventNames, $this->configuredEventNames()));
        sort($dynamicEventNames);

        $eventNames = array_merge(
            [self::AggregateBucketName],
            $this->configuredEventNames(),
            $dynamicEventNames,
        );

        $buckets = [];

        foreach ($eventNames as $eventName) {
            $buckets[$eventName] = new EventMetricsBucket;
        }

        return $buckets;
    }

    private function accumulate(EventMetricsBucket $bucket, StoredEventData $event): void
    {
        $bucket->total++;
        $bucket->byStatus[$event->status->value]++;
        $bucket->lastReceivedAt = $this->latest($bucket->lastReceivedAt, $event->createdAt);
        $bucket->lastProcessedAt = $this->latest($bucket->lastProcessedAt, $event->processedAt);

        if ($this->isPendingStatus($event->status)) {
            $bucket->pending++;
            $bucket->oldestPendingAt = $this->oldest($bucket->oldestPendingAt, $event->queuedAt ?? $event->createdAt);
        }

        if ($this->isFailureStatus($event->status)) {
            $bucket->failed++;
            $bucket->retryable++;
        }
    }

    /**
     * @return array<int, string>
     */
    private function configuredEventNames(): array
    {
        return array_values(array_filter(
            config('event_pipeline.supported_events', []),
            static fn (mixed $eventName): bool => is_string($eventName) && $eventName !== '',
        ));
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

    private function isPendingStatus(EventStatus $status): bool
    {
        return in_array($status, [EventStatus::RECEIVED, EventStatus::QUEUED, EventStatus::PROCESSING], true);
    }

    private function isFailureStatus(EventStatus $status): bool
    {
        return in_array($status, [EventStatus::PUBLISH_FAILED, EventStatus::PROCESSING_FAILED], true);
    }
}
