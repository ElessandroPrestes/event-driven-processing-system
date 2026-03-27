<?php

use App\Interfaces\Http\Middleware\EnsureEventPipelineRateLimit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

afterEach(function (): void {
    RateLimiter::clear('event_pipeline:rate_limit:operations:'.sha1('127.0.0.1'));
    RateLimiter::clear('event_pipeline:rate_limit:ingest:'.sha1('127.0.0.1'));
});

it('returns internal server error when the rate limit scope configuration is invalid', function (): void {
    $middleware = new EnsureEventPipelineRateLimit;
    $request = Request::create('/api/v1/events', 'GET', server: ['REMOTE_ADDR' => '127.0.0.1']);

    $response = $middleware->handle($request, static fn () => response('ok'), 'missing_scope');

    expect($response->getStatusCode())->toBe(Response::HTTP_INTERNAL_SERVER_ERROR)
        ->and($response->getData(true))->toMatchArray([
            'message' => 'Escopo de limitacao de taxa da API invalido.',
        ]);
});

it('returns service unavailable when the rate limit configuration is incomplete', function (): void {
    config()->set('event_pipeline.rate_limits.operations', [
        'max_attempts' => 0,
        'decay_seconds' => 0,
    ]);

    $middleware = new EnsureEventPipelineRateLimit;
    $request = Request::create('/api/v1/events', 'GET', server: ['REMOTE_ADDR' => '127.0.0.1']);

    $response = $middleware->handle($request, static fn () => response('ok'), 'operations');

    expect($response->getStatusCode())->toBe(Response::HTTP_SERVICE_UNAVAILABLE)
        ->and($response->getData(true))->toMatchArray([
            'message' => 'A limitacao de taxa da API nao esta configurada.',
        ]);
});

it('adds rate limit headers when the request is allowed', function (): void {
    config()->set('event_pipeline.rate_limits.operations', [
        'max_attempts' => 2,
        'decay_seconds' => 60,
    ]);

    $middleware = new EnsureEventPipelineRateLimit;
    $request = Request::create('/api/v1/events', 'GET', server: ['REMOTE_ADDR' => '127.0.0.1']);

    $response = $middleware->handle($request, static fn () => response('ok', Response::HTTP_ACCEPTED), 'operations');

    expect($response->getStatusCode())->toBe(Response::HTTP_ACCEPTED)
        ->and($response->headers->get('X-RateLimit-Limit'))->toBe('2')
        ->and($response->headers->get('X-RateLimit-Remaining'))->toBe('1')
        ->and($response->headers->has('Retry-After'))->toBeFalse();
});

it('returns too many requests when the limit is exceeded', function (): void {
    config()->set('event_pipeline.rate_limits.operations', [
        'max_attempts' => 1,
        'decay_seconds' => 60,
    ]);

    $middleware = new EnsureEventPipelineRateLimit;
    $request = Request::create('/api/v1/events', 'GET', server: ['REMOTE_ADDR' => '127.0.0.1']);

    $middleware->handle($request, static fn () => response('ok'), 'operations');
    $response = $middleware->handle($request, static fn () => response('ok'), 'operations');

    expect($response->getStatusCode())->toBe(Response::HTTP_TOO_MANY_REQUESTS)
        ->and($response->headers->get('X-RateLimit-Limit'))->toBe('1')
        ->and($response->headers->get('X-RateLimit-Remaining'))->toBe('0')
        ->and($response->getData(true))->toMatchArray([
            'message' => 'Limite de requisicoes excedido para este escopo da API.',
            'data' => [
                'scope' => 'operations',
                'limit' => 1,
                'retry_after_seconds' => 60,
            ],
        ])
        ->and((int) $response->headers->get('Retry-After'))->toBeGreaterThan(0);
});
