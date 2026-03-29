<?php

use App\Application\Health\Contracts\WorkerHeartbeatStore;
use App\Application\Health\DataTransferObjects\WorkerHeartbeatData;
use App\Infrastructure\Health\WorkersHealthProbe;
use Carbon\CarbonImmutable;

afterEach(function (): void {
    Mockery::close();
    CarbonImmutable::setTestNow();
});

it('reports workers as healthy when the heartbeats are fresh', function (): void {
    CarbonImmutable::setTestNow('2026-03-29T12:00:00+00:00');
    config()->set('event_pipeline.health.workers.required', ['worker', 'ingest-worker']);
    config()->set('event_pipeline.health.workers.stale_after_seconds', 60);

    $store = Mockery::mock(WorkerHeartbeatStore::class);
    $store->shouldReceive('latest')->once()->with('worker')->andReturn(
        new WorkerHeartbeatData('worker', CarbonImmutable::parse('2026-03-29T11:59:30+00:00')),
    );
    $store->shouldReceive('latest')->once()->with('ingest-worker')->andReturn(
        new WorkerHeartbeatData('ingest-worker', CarbonImmutable::parse('2026-03-29T11:59:10+00:00')),
    );

    $result = (new WorkersHealthProbe($store))->check();

    expect($result->status)->toBe('ok')
        ->and($result->details['healthy_workers'])->toBe(2)
        ->and($result->details['workers']['worker']['status'])->toBe('ok')
        ->and($result->details['workers']['ingest-worker']['status'])->toBe('ok');
});

it('reports workers as degraded when a heartbeat is missing or stale', function (): void {
    CarbonImmutable::setTestNow('2026-03-29T12:00:00+00:00');
    config()->set('event_pipeline.health.workers.required', ['worker', 'ingest-worker']);
    config()->set('event_pipeline.health.workers.stale_after_seconds', 60);

    $store = Mockery::mock(WorkerHeartbeatStore::class);
    $store->shouldReceive('latest')->once()->with('worker')->andReturn(
        new WorkerHeartbeatData('worker', CarbonImmutable::parse('2026-03-29T11:57:00+00:00')),
    );
    $store->shouldReceive('latest')->once()->with('ingest-worker')->andReturnNull();

    $result = (new WorkersHealthProbe($store))->check();

    expect($result->status)->toBe('degraded')
        ->and($result->message)->toBe('One or more workers are unavailable or stale.')
        ->and($result->details['healthy_workers'])->toBe(0)
        ->and($result->details['workers']['worker']['status'])->toBe('stale')
        ->and($result->details['workers']['ingest-worker']['status'])->toBe('missing');
});
