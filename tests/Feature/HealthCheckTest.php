<?php

use App\Application\Health\Services\HealthProbeRegistry;
use Tests\Fakes\FakeHealthProbe;

it('redirects the root route to the api health check', function (): void {
    $this->get('/')
        ->assertRedirect('/api/v1/health');
});

it('returns an ok health payload when all probes are healthy', function (): void {
    app()->instance(HealthProbeRegistry::class, new HealthProbeRegistry([
        new FakeHealthProbe('database'),
        new FakeHealthProbe('redis'),
        new FakeHealthProbe('rabbitmq'),
        new FakeHealthProbe('workers'),
    ]));

    $response = $this->getJson('/api/v1/health');

    $response->assertOk()
        ->assertJsonPath('data.status', 'ok')
        ->assertJsonPath('data.ready', true)
        ->assertJsonPath('data.service', config('app.name'))
        ->assertJsonStructure([
            'data' => [
                'service',
                'status',
                'ready',
                'timestamp',
                'checks' => [
                    'database' => ['status', 'observed_at'],
                    'redis' => ['status', 'observed_at'],
                    'rabbitmq' => ['status', 'observed_at'],
                    'workers' => ['status', 'observed_at'],
                ],
            ],
        ]);
});

it('returns service unavailable when any required probe is degraded', function (): void {
    app()->instance(HealthProbeRegistry::class, new HealthProbeRegistry([
        new FakeHealthProbe('database'),
        new FakeHealthProbe('redis'),
        new FakeHealthProbe('rabbitmq', 'degraded', 'RabbitMQ connectivity check failed.'),
        new FakeHealthProbe('workers'),
    ]));

    $this->getJson('/api/v1/health')
        ->assertStatus(503)
        ->assertJsonPath('data.status', 'degraded')
        ->assertJsonPath('data.ready', false)
        ->assertJsonPath('data.checks.rabbitmq.status', 'degraded');
});
