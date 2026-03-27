<?php

use App\Domain\Events\DataTransferObjects\StoredEventData;
use App\Domain\Events\Enums\EventStatus;
use Carbon\CarbonImmutable;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->in('Feature', 'Unit');

function configureEventPipelineApiKeys(): void
{
    config()->set('event_pipeline.auth.ingest.header', 'X-Ingest-Api-Key');
    config()->set('event_pipeline.auth.ingest.key', 'test-ingest-key');
    config()->set('event_pipeline.auth.operations.header', 'X-Operations-Api-Key');
    config()->set('event_pipeline.auth.operations.key', 'test-operations-key');
    config()->set('event_pipeline.rate_limits.ingest.max_attempts', 60);
    config()->set('event_pipeline.rate_limits.ingest.decay_seconds', 60);
    config()->set('event_pipeline.rate_limits.operations.max_attempts', 120);
    config()->set('event_pipeline.rate_limits.operations.decay_seconds', 60);
    config()->set('event_pipeline.api.pagination.events.default_per_page', 20);
    config()->set('event_pipeline.api.pagination.events.max_per_page', 100);
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

/**
 * @param  array<string, mixed>  $payload
 * @param  array<string, mixed>  $attributes
 */
function storedEvent(
    string $eventName = 'user.created',
    array $payload = [],
    EventStatus $status = EventStatus::QUEUED,
    array $attributes = [],
): StoredEventData {
    $timestamp = CarbonImmutable::parse('2026-03-27T00:00:00+00:00');

    return new StoredEventData(
        id: (string) ($attributes['id'] ?? sprintf('evt-%s', uniqid('', true))),
        traceId: $attributes['trace_id'] ?? sprintf('trace-%s', uniqid('', true)),
        eventName: $eventName,
        payload: $payload,
        metadata: $attributes['metadata'] ?? null,
        status: $status,
        idempotencyKey: (string) ($attributes['idempotency_key'] ?? sprintf('idem-%s', uniqid('', true))),
        contentHash: (string) ($attributes['content_hash'] ?? sprintf('hash-%s', uniqid('', true))),
        occurredAt: $attributes['occurred_at'] ?? $timestamp,
        queuedAt: $attributes['queued_at'] ?? ($status === EventStatus::RECEIVED ? null : $timestamp),
        consumedAt: $attributes['consumed_at'] ?? null,
        processedAt: $attributes['processed_at'] ?? null,
        processingAttempts: (int) ($attributes['processing_attempts'] ?? 0),
        processingResult: $attributes['processing_result'] ?? null,
        failureReason: $attributes['failure_reason'] ?? null,
        createdAt: $attributes['created_at'] ?? $timestamp,
        updatedAt: $attributes['updated_at'] ?? $timestamp,
    );
}
