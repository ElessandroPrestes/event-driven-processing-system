<?php

namespace App\Interfaces\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class EnsureEventPipelineApiKey
{
    public function handle(Request $request, Closure $next, string $scope): mixed
    {
        $config = config(sprintf('event_pipeline.auth.%s', $scope));

        if (! is_array($config)) {
            Log::error('event.auth_scope_invalid', [
                'scope' => $scope,
                'path' => $request->path(),
            ]);

            return $this->errorResponse(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'Escopo de autenticacao da API invalido.',
            );
        }

        $header = $config['header'] ?? null;
        $expectedKey = $config['key'] ?? null;

        if (! is_string($header) || $header === '' || ! is_string($expectedKey) || $expectedKey === '') {
            Log::error('event.auth_not_configured', [
                'scope' => $scope,
                'path' => $request->path(),
            ]);

            return $this->errorResponse(
                Response::HTTP_SERVICE_UNAVAILABLE,
                'A autenticacao da API nao esta configurada.',
            );
        }

        $providedKey = $request->header($header);

        if (! is_string($providedKey) || $providedKey === '' || ! hash_equals($expectedKey, $providedKey)) {
            Log::warning('event.auth_failed', [
                'scope' => $scope,
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            return $this->errorResponse(
                Response::HTTP_UNAUTHORIZED,
                'A chave de API informada e invalida.',
            );
        }

        return $next($request);
    }

    private function errorResponse(int $status, string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], $status);
    }
}
