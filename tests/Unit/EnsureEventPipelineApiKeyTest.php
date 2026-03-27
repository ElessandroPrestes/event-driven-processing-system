<?php

use App\Interfaces\Http\Middleware\EnsureEventPipelineApiKey;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

it('returns internal server error when the authentication scope configuration is invalid', function (): void {
    $middleware = new EnsureEventPipelineApiKey;
    $request = Request::create('/api/v1/events', 'GET');

    $response = $middleware->handle($request, static fn () => response('ok'), 'missing_scope');

    expect($response->getStatusCode())->toBe(Response::HTTP_INTERNAL_SERVER_ERROR)
        ->and($response->getData(true))->toMatchArray([
            'message' => 'Escopo de autenticacao da API invalido.',
        ]);
});

it('returns service unavailable when the api key configuration is incomplete', function (): void {
    config()->set('event_pipeline.auth.operations', [
        'header' => '',
        'key' => '',
    ]);

    $middleware = new EnsureEventPipelineApiKey;
    $request = Request::create('/api/v1/events', 'GET');

    $response = $middleware->handle($request, static fn () => response('ok'), 'operations');

    expect($response->getStatusCode())->toBe(Response::HTTP_SERVICE_UNAVAILABLE)
        ->and($response->getData(true))->toMatchArray([
            'message' => 'A autenticacao da API nao esta configurada.',
        ]);
});

it('returns unauthorized when the provided api key is invalid', function (): void {
    config()->set('event_pipeline.auth.operations', [
        'header' => 'X-Operations-Api-Key',
        'key' => 'expected-key',
    ]);

    $middleware = new EnsureEventPipelineApiKey;
    $request = Request::create('/api/v1/events', 'GET');
    $request->headers->set('X-Operations-Api-Key', 'invalid-key');

    $response = $middleware->handle($request, static fn () => response('ok'), 'operations');

    expect($response->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED)
        ->and($response->getData(true))->toMatchArray([
            'message' => 'A chave de API informada e invalida.',
        ]);
});

it('allows the request to continue when the api key is valid', function (): void {
    config()->set('event_pipeline.auth.operations', [
        'header' => 'X-Operations-Api-Key',
        'key' => 'expected-key',
    ]);

    $middleware = new EnsureEventPipelineApiKey;
    $request = Request::create('/api/v1/events', 'GET');
    $request->headers->set('X-Operations-Api-Key', 'expected-key');

    $response = $middleware->handle($request, static fn () => response('ok', Response::HTTP_ACCEPTED), 'operations');

    expect($response->getStatusCode())->toBe(Response::HTTP_ACCEPTED)
        ->and($response->getContent())->toBe('ok');
});
