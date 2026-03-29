<?php

namespace App\Infrastructure\Health;

use App\Application\Health\Contracts\HealthProbe;
use App\Application\Health\DataTransferObjects\ComponentHealthData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Throwable;

final class RedisHealthProbe implements HealthProbe
{
    public function check(): ComponentHealthData
    {
        $observedAt = CarbonImmutable::now();

        try {
            $response = Redis::connection((string) config('event_pipeline.health.redis.connection'))
                ->command('ping');

            if (! $this->isHealthyResponse($response)) {
                throw new RuntimeException('Unexpected Redis ping response.');
            }

            return new ComponentHealthData(
                name: 'redis',
                status: 'ok',
                observedAt: $observedAt,
            );
        } catch (Throwable) {
            return new ComponentHealthData(
                name: 'redis',
                status: 'degraded',
                observedAt: $observedAt,
                message: 'Redis connectivity check failed.',
            );
        }
    }

    private function isHealthyResponse(mixed $response): bool
    {
        if ($response === true) {
            return true;
        }

        if (! is_string($response)) {
            return false;
        }

        return strtoupper(ltrim(trim($response), '+')) === 'PONG';
    }
}
