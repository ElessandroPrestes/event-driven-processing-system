<?php

use App\Application\Health\Actions\GetHealthStatusAction;

it('builds a stable health payload', function (): void {
    $payload = app(GetHealthStatusAction::class)->handle();

    expect($payload)
        ->toHaveKeys(['service', 'status', 'timestamp'])
        ->and($payload['service'])->toBe(config('app.name'))
        ->and($payload['status'])->toBe('ok')
        ->and($payload['timestamp'])->not->toBeEmpty();
});
