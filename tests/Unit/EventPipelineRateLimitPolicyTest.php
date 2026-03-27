<?php

use App\Interfaces\Http\Support\EventPipelineRateLimitPolicy;
use Illuminate\Http\Request;

it('builds a policy from valid configuration', function (): void {
    $policy = EventPipelineRateLimitPolicy::fromConfig('operations', [
        'max_attempts' => 120,
        'decay_seconds' => 60,
    ]);

    expect($policy)->not->toBeNull()
        ->and($policy?->scope())->toBe('operations')
        ->and($policy?->maxAttempts())->toBe(120)
        ->and($policy?->decaySeconds())->toBe(60);
});

it('rejects invalid rate limit configuration', function (): void {
    $policy = EventPipelineRateLimitPolicy::fromConfig('operations', [
        'max_attempts' => 0,
        'decay_seconds' => 0,
    ]);

    expect($policy)->toBeNull();
});

it('builds a deterministic key, headers and payload for a request', function (): void {
    $policy = new EventPipelineRateLimitPolicy(
        scope: 'ingest',
        maxAttempts: 60,
        decaySeconds: 30,
    );
    $request = Request::create('/api/v1/events', 'POST', server: ['REMOTE_ADDR' => '127.0.0.21']);

    expect($policy->keyFor($request))->toBe('event_pipeline:rate_limit:ingest:'.sha1('127.0.0.21'))
        ->and($policy->successHeaders(12))->toBe([
            'X-RateLimit-Limit' => '60',
            'X-RateLimit-Remaining' => '12',
        ])
        ->and($policy->limitedHeaders(45))->toBe([
            'Retry-After' => '45',
            'X-RateLimit-Limit' => '60',
            'X-RateLimit-Remaining' => '0',
        ])
        ->and($policy->limitedPayload(45))->toBe([
            'scope' => 'ingest',
            'limit' => 60,
            'retry_after_seconds' => 45,
        ]);
});
