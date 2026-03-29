<?php

namespace App\Infrastructure\Health;

use App\Application\Health\Contracts\HealthProbe;
use App\Application\Health\DataTransferObjects\ComponentHealthData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

final class DatabaseHealthProbe implements HealthProbe
{
    public function check(): ComponentHealthData
    {
        $observedAt = CarbonImmutable::now();

        try {
            DB::connection((string) config('event_pipeline.health.database.connection'))
                ->select('select 1');

            return new ComponentHealthData(
                name: 'database',
                status: 'ok',
                observedAt: $observedAt,
            );
        } catch (Throwable) {
            return new ComponentHealthData(
                name: 'database',
                status: 'degraded',
                observedAt: $observedAt,
                message: 'Database connectivity check failed.',
            );
        }
    }
}
