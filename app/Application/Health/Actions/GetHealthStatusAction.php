<?php

namespace App\Application\Health\Actions;

use App\Application\Health\Services\HealthProbeRegistry;
use Carbon\CarbonImmutable;

final class GetHealthStatusAction
{
    public function __construct(
        private readonly HealthProbeRegistry $probes,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(): array
    {
        $checks = [];
        $ready = true;

        foreach ($this->probes->all() as $probe) {
            $result = $probe->check();
            $checks[$result->name] = $result->toArray();
            $ready = $ready && $result->healthy();
        }

        return [
            'service' => config('app.name'),
            'status' => $ready ? 'ok' : 'degraded',
            'ready' => $ready,
            'timestamp' => CarbonImmutable::now()->toIso8601String(),
            'checks' => $checks,
        ];
    }
}
