<?php

namespace App\Interfaces\Http\Middleware;

use App\Interfaces\Http\Support\EventPipelineRateLimitPolicy;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

final class EnsureEventPipelineRateLimit
{
    public function handle(Request $request, Closure $next, string $scope): mixed
    {
        $config = config(sprintf('event_pipeline.rate_limits.%s', $scope));

        if (! is_array($config)) {
            Log::error('event.rate_limit_scope_invalid', [
                'scope' => $scope,
                'path' => $request->path(),
            ]);

            return $this->errorResponse(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'Escopo de limitacao de taxa da API invalido.',
            );
        }

        $policy = EventPipelineRateLimitPolicy::fromConfig($scope, $config);

        if ($policy === null) {
            Log::error('event.rate_limit_not_configured', [
                'scope' => $scope,
                'path' => $request->path(),
            ]);

            return $this->errorResponse(
                Response::HTTP_SERVICE_UNAVAILABLE,
                'A limitacao de taxa da API nao esta configurada.',
            );
        }

        $key = $policy->keyFor($request);

        if (RateLimiter::tooManyAttempts($key, $policy->maxAttempts())) {
            $retryAfterSeconds = max(RateLimiter::availableIn($key), 1);

            Log::warning('event.rate_limit_exceeded', [
                'scope' => $policy->scope(),
                'path' => $request->path(),
                'route' => $request->route()?->getName(),
                'ip' => $request->ip(),
                'retry_after_seconds' => $retryAfterSeconds,
            ]);

            return $this->errorResponse(
                Response::HTTP_TOO_MANY_REQUESTS,
                'Limite de requisicoes excedido para este escopo da API.',
                headers: $policy->limitedHeaders($retryAfterSeconds),
                data: $policy->limitedPayload($retryAfterSeconds),
            );
        }

        RateLimiter::hit($key, $policy->decaySeconds());

        $response = $next($request);

        if ($response instanceof Response) {
            foreach ($policy->successHeaders(
                RateLimiter::remaining($key, $policy->maxAttempts()),
            ) as $header => $value) {
                $response->headers->set($header, $value);
            }
        }

        return $response;
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, int|string>  $data
     */
    private function errorResponse(int $status, string $message, array $headers = [], array $data = []): JsonResponse
    {
        $payload = [
            'message' => $message,
        ];

        if ($data !== []) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $status, $headers);
    }
}
