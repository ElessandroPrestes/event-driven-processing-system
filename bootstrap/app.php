<?php

use App\Application\Events\Exceptions\EventPublicationException;
use App\Application\Events\Exceptions\EventRetryDispatchException;
use App\Application\Events\Exceptions\EventRetryNotAllowedException;
use App\Application\Events\Exceptions\IdempotencyConflictException;
use App\Interfaces\Http\Resources\Api\V1\EventResource;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (EventPublicationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'Falha ao publicar o evento no RabbitMQ.',
                'data' => EventResource::make($exception->event())->resolve($request),
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        });

        $exceptions->render(function (IdempotencyConflictException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'A chave de idempotencia ja foi utilizada com um payload diferente.',
            ], Response::HTTP_CONFLICT);
        });

        $exceptions->render(function (EventRetryNotAllowedException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'O evento informado nao pode ser reenfileirado no estado atual.',
                'data' => EventResource::make($exception->event())->resolve($request),
            ], Response::HTTP_CONFLICT);
        });

        $exceptions->render(function (EventRetryDispatchException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'Falha ao reenfileirar o evento no RabbitMQ.',
                'data' => EventResource::make($exception->event())->resolve($request),
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        });
    })->create();
