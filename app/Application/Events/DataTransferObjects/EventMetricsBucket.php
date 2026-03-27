<?php

namespace App\Application\Events\DataTransferObjects;

use App\Domain\Events\Enums\EventStatus;
use Carbon\CarbonImmutable;

final class EventMetricsBucket
{
    public int $total = 0;

    public int $pending = 0;

    public int $failed = 0;

    public int $retryable = 0;

    public ?CarbonImmutable $lastReceivedAt = null;

    public ?CarbonImmutable $lastProcessedAt = null;

    public ?CarbonImmutable $oldestPendingAt = null;

    /**
     * @var array<string, int>
     */
    public array $byStatus = [];

    public function __construct()
    {
        foreach (EventStatus::cases() as $status) {
            $this->byStatus[$status->value] = 0;
        }
    }
}
