<?php

namespace App\Application\Events\Actions;

use App\Application\Events\DataTransferObjects\EventMetricsBucket;
use App\Application\Events\Services\EventMetricsAggregator;
use App\Domain\Events\Contracts\EventRepository;
use Carbon\CarbonImmutable;

final class ExportEventMetricsAction
{
    private const string MetricsPrefix = 'eventflow_events';

    public function __construct(
        private readonly EventRepository $events,
        private readonly EventMetricsAggregator $metrics,
    ) {}

    public function handle(): string
    {
        return $this->render($this->metrics->groupByEventName($this->events->list()));
    }

    /**
     * @param  array<string, EventMetricsBucket>  $buckets
     */
    private function render(array $buckets): string
    {
        $lines = [];

        $this->appendMetricHeader(
            $lines,
            name: self::MetricsPrefix.'_total',
            help: 'Current number of persisted events by status and event name.',
        );

        foreach ($buckets as $eventName => $bucket) {
            foreach ($bucket->byStatus as $status => $count) {
                $lines[] = $this->metricLine(
                    name: self::MetricsPrefix.'_total',
                    labels: [
                        'event_name' => $eventName,
                        'status' => $status,
                    ],
                    value: $count,
                );
            }
        }

        $this->appendGaugeMetric($lines, self::MetricsPrefix.'_pending_total', 'Current number of pending events by event name.', $buckets, static fn (EventMetricsBucket $bucket): int => $bucket->pending);
        $this->appendGaugeMetric($lines, self::MetricsPrefix.'_failed_total', 'Current number of failed events by event name.', $buckets, static fn (EventMetricsBucket $bucket): int => $bucket->failed);
        $this->appendGaugeMetric($lines, self::MetricsPrefix.'_retryable_total', 'Current number of retryable events by event name.', $buckets, static fn (EventMetricsBucket $bucket): int => $bucket->retryable);
        $this->appendTimestampMetric($lines, self::MetricsPrefix.'_last_received_timestamp_seconds', 'Unix timestamp of the latest received event by event name.', $buckets, static fn (EventMetricsBucket $bucket): ?CarbonImmutable => $bucket->lastReceivedAt);
        $this->appendTimestampMetric($lines, self::MetricsPrefix.'_last_processed_timestamp_seconds', 'Unix timestamp of the latest processed event by event name.', $buckets, static fn (EventMetricsBucket $bucket): ?CarbonImmutable => $bucket->lastProcessedAt);
        $this->appendTimestampMetric($lines, self::MetricsPrefix.'_oldest_pending_timestamp_seconds', 'Unix timestamp of the oldest pending event by event name.', $buckets, static fn (EventMetricsBucket $bucket): ?CarbonImmutable => $bucket->oldestPendingAt);

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  array<int, string>  $lines
     * @param  array<string, EventMetricsBucket>  $buckets
     * @param  callable(EventMetricsBucket): int  $valueResolver
     */
    private function appendGaugeMetric(array &$lines, string $name, string $help, array $buckets, callable $valueResolver): void
    {
        $this->appendMetricHeader($lines, $name, $help);

        foreach ($buckets as $eventName => $bucket) {
            $lines[] = $this->metricLine(
                name: $name,
                labels: ['event_name' => $eventName],
                value: $valueResolver($bucket),
            );
        }
    }

    /**
     * @param  array<int, string>  $lines
     * @param  array<string, EventMetricsBucket>  $buckets
     * @param  callable(EventMetricsBucket): ?CarbonImmutable  $valueResolver
     */
    private function appendTimestampMetric(array &$lines, string $name, string $help, array $buckets, callable $valueResolver): void
    {
        $this->appendMetricHeader($lines, $name, $help);

        foreach ($buckets as $eventName => $bucket) {
            $value = $valueResolver($bucket);

            if ($value === null) {
                continue;
            }

            $lines[] = $this->metricLine(
                name: $name,
                labels: ['event_name' => $eventName],
                value: $value->getTimestamp(),
            );
        }
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function appendMetricHeader(array &$lines, string $name, string $help): void
    {
        $lines[] = sprintf('# HELP %s %s', $name, $help);
        $lines[] = sprintf('# TYPE %s gauge', $name);
    }

    /**
     * @param  array<string, string>  $labels
     */
    private function metricLine(string $name, array $labels, int $value): string
    {
        $encodedLabels = array_map(
            fn (string $label, string $labelValue): string => sprintf('%s="%s"', $label, $this->escapeLabelValue($labelValue)),
            array_keys($labels),
            array_values($labels),
        );

        return sprintf('%s{%s} %d', $name, implode(',', $encodedLabels), $value);
    }

    private function escapeLabelValue(string $value): string
    {
        return str_replace(
            ['\\', '"', "\n"],
            ['\\\\', '\\"', '\\n'],
            $value,
        );
    }
}
