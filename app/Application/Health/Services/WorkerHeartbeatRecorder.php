<?php

namespace App\Application\Health\Services;

use App\Application\Health\Contracts\WorkerHeartbeatStore;

final readonly class WorkerHeartbeatRecorder
{
    public function __construct(
        private WorkerHeartbeatStore $store,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function record(string $workerName, array $context = []): void
    {
        $this->store->record($workerName, $context);
    }
}
