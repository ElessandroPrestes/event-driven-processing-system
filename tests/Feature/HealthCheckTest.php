<?php

it('redirects the root route to the api health check', function (): void {
    $this->get('/')
        ->assertRedirect('/api/v1/health');
});

it('returns the application health payload', function (): void {
    $response = $this->getJson('/api/v1/health');

    $response->assertOk()
        ->assertJsonPath('data.status', 'ok')
        ->assertJsonPath('data.service', config('app.name'))
        ->assertJsonStructure([
            'data' => [
                'service',
                'status',
                'timestamp',
            ],
        ]);
});
