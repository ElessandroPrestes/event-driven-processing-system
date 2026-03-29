<?php

namespace App\Application\Health\DataTransferObjects;

use Carbon\CarbonImmutable;

final readonly class WorkerHeartbeatData
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $workerName,
        public CarbonImmutable $recordedAt,
        public array $context = [],
    ) {}

    public function ageInSeconds(CarbonImmutable $reference): int
    {
        return (int) abs($reference->diffInSeconds($this->recordedAt, false));
    }
}
