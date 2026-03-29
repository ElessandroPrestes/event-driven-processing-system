<?php

use App\Application\Health\Actions\GetHealthStatusAction;
use App\Application\Health\Services\HealthProbeRegistry;
use Carbon\CarbonImmutable;
use Tests\Fakes\FakeHealthProbe;

it('builds a stable health payload', function (): void {
    CarbonImmutable::setTestNow('2026-03-29T12:00:00+00:00');

    $action = new GetHealthStatusAction(new HealthProbeRegistry([
        new FakeHealthProbe('database'),
        new FakeHealthProbe('redis'),
        new FakeHealthProbe('rabbitmq'),
        new FakeHealthProbe('workers'),
    ]));
    $payload = $action->handle();

    CarbonImmutable::setTestNow();

    expect($payload)
        ->toHaveKeys(['service', 'status', 'ready', 'timestamp', 'checks'])
        ->and($payload['service'])->toBe(config('app.name'))
        ->and($payload['status'])->toBe('ok')
        ->and($payload['ready'])->toBeTrue()
        ->and($payload['timestamp'])->toBe('2026-03-29T12:00:00+00:00')
        ->and($payload['checks'])->toHaveKeys(['database', 'redis', 'rabbitmq', 'workers']);
});
