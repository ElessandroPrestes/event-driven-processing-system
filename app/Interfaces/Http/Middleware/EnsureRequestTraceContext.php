<?php

namespace App\Interfaces\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class EnsureRequestTraceContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) config('event_pipeline.observability.trace_header', 'X-Trace-Id');
        $incomingTraceId = $request->header($header);
        $traceId = $this->normalizeTraceId($incomingTraceId);

        $request->attributes->set('trace_id', $traceId);
        Log::withContext([
            'trace_id' => $traceId,
        ]);

        try {
            /** @var Response $response */
            $response = $next($request);
            $response->headers->set($header, $traceId);

            return $response;
        } finally {
            Log::withoutContext(['trace_id']);
        }
    }

    private function normalizeTraceId(mixed $incomingTraceId): string
    {
        if (is_string($incomingTraceId)) {
            $traceId = trim($incomingTraceId);

            if ($traceId !== '' && strlen($traceId) <= 128) {
                return $traceId;
            }
        }

        return (string) Str::uuid();
    }
}
