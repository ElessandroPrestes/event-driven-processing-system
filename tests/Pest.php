<?php

use Tests\TestCase;

pest()->extend(TestCase::class)
    ->in('Feature', 'Unit');

function configureEventPipelineApiKeys(): void
{
    config()->set('event_pipeline.auth.ingest.header', 'X-Ingest-Api-Key');
    config()->set('event_pipeline.auth.ingest.key', 'test-ingest-key');
    config()->set('event_pipeline.auth.operations.header', 'X-Operations-Api-Key');
    config()->set('event_pipeline.auth.operations.key', 'test-operations-key');
    config()->set('event_pipeline.observability.trace_header', 'X-Trace-Id');
}

function eventIngestHeaders(string $idempotencyKey, ?string $apiKey = null, ?string $traceId = null): array
{
    $headers = [
        (string) config('event_pipeline.auth.ingest.header') => $apiKey ?? (string) config('event_pipeline.auth.ingest.key'),
        (string) config('event_pipeline.api.idempotency_header') => $idempotencyKey,
    ];

    if ($traceId !== null) {
        $headers[(string) config('event_pipeline.observability.trace_header')] = $traceId;
    }

    return $headers;
}

function eventOperationsHeaders(?string $apiKey = null, ?string $traceId = null): array
{
    $headers = [
        (string) config('event_pipeline.auth.operations.header') => $apiKey ?? (string) config('event_pipeline.auth.operations.key'),
    ];

    if ($traceId !== null) {
        $headers[(string) config('event_pipeline.observability.trace_header')] = $traceId;
    }

    return $headers;
}
