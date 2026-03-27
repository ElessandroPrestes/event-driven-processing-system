<?php

namespace App\Application\Events\DataTransferObjects;

use Carbon\CarbonImmutable;

final readonly class EventSummaryData
{
    /**
     * @param  array<string, int>  $byStatus
     */
    public function __construct(
        public int $total,
        public int $pending,
        public int $failed,
        public int $retryable,
        public array $byStatus,
        public ?CarbonImmutable $lastReceivedAt,
        public ?CarbonImmutable $lastProcessedAt,
        public ?CarbonImmutable $oldestPendingAt,
    ) {}
}
