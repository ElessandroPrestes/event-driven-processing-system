<?php

namespace App\Infrastructure\Health;

use App\Application\Health\Contracts\HealthProbe;
use App\Application\Health\Contracts\WorkerHeartbeatStore;
use App\Application\Health\DataTransferObjects\ComponentHealthData;
use App\Application\Health\DataTransferObjects\WorkerHeartbeatData;
use Carbon\CarbonImmutable;

final readonly class WorkersHealthProbe implements HealthProbe
{
    public function __construct(
        private WorkerHeartbeatStore $heartbeats,
    ) {}

    public function check(): ComponentHealthData
    {
        $observedAt = CarbonImmutable::now();
        $staleAfterSeconds = max(1, (int) config('event_pipeline.health.workers.stale_after_seconds', 60));
        $requiredWorkers = $this->requiredWorkers();
        $workers = [];
        $healthyWorkers = 0;

        foreach ($requiredWorkers as $workerName) {
            $heartbeat = $this->heartbeats->latest($workerName);
            $snapshot = $this->buildWorkerSnapshot($heartbeat, $observedAt, $staleAfterSeconds);
            $workers[$workerName] = $snapshot;

            if ($snapshot['status'] === 'ok') {
                $healthyWorkers++;
            }
        }

        $allHealthy = $healthyWorkers === count($requiredWorkers);

        return new ComponentHealthData(
            name: 'workers',
            status: $allHealthy ? 'ok' : 'degraded',
            observedAt: $observedAt,
            message: $allHealthy ? null : 'One or more workers are unavailable or stale.',
            details: [
                'required_workers' => array_values($requiredWorkers),
                'healthy_workers' => $healthyWorkers,
                'stale_after_seconds' => $staleAfterSeconds,
                'workers' => $workers,
            ],
        );
    }

    /**
     * @return array<int, string>
     */
    private function requiredWorkers(): array
    {
        $workers = config('event_pipeline.health.workers.required', []);

        if (! is_array($workers)) {
            return [];
        }

        return array_values(array_filter(
            $workers,
            static fn (mixed $worker): bool => is_string($worker) && trim($worker) !== '',
        ));
    }

    /**
     * @return array<string, int|string|null>
     */
    private function buildWorkerSnapshot(
        ?WorkerHeartbeatData $heartbeat,
        CarbonImmutable $observedAt,
        int $staleAfterSeconds,
    ): array {
        if ($heartbeat === null) {
            return [
                'status' => 'missing',
                'last_heartbeat_at' => null,
                'age_seconds' => null,
                'stale_after_seconds' => $staleAfterSeconds,
            ];
        }

        $ageInSeconds = $heartbeat->ageInSeconds($observedAt);

        return [
            'status' => $ageInSeconds <= $staleAfterSeconds ? 'ok' : 'stale',
            'last_heartbeat_at' => $heartbeat->recordedAt->toIso8601String(),
            'age_seconds' => $ageInSeconds,
            'stale_after_seconds' => $staleAfterSeconds,
        ];
    }
}
