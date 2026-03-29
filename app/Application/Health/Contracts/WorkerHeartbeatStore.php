<?php

namespace App\Application\Health\Contracts;

use App\Application\Health\DataTransferObjects\WorkerHeartbeatData;

interface WorkerHeartbeatStore
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function record(string $workerName, array $context = []): WorkerHeartbeatData;

    public function latest(string $workerName): ?WorkerHeartbeatData;
}
