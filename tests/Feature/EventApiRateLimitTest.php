<?php

use App\Domain\Events\Contracts\EventHistoryRepository;
use App\Domain\Events\Contracts\EventPublisher;
use App\Domain\Events\Contracts\EventRepository;
use Tests\Fakes\FakeEventPublisher;
use Tests\Fakes\InMemoryEventHistoryRepository;
use Tests\Fakes\InMemoryEventRepository;

beforeEach(function (): void {
    configureEventPipelineApiKeys();

    $this->events = new InMemoryEventRepository;
    $this->publisher = new FakeEventPublisher;
    $this->history = new InMemoryEventHistoryRepository;

    app()->instance(EventRepository::class, $this->events);
    app()->instance(EventPublisher::class, $this->publisher);
    app()->instance(EventHistoryRepository::class, $this->history);
});

it('rate limits event ingestion requests', function (): void {
    config()->set('event_pipeline.rate_limits.ingest.max_attempts', 1);
    config()->set('event_pipeline.rate_limits.ingest.decay_seconds', 60);

    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.11'])
        ->withHeaders(eventIngestHeaders('evt-rate-limit-001'))
        ->postJson('/api/v1/events', [
            'event_name' => 'user.created',
            'payload' => [
                'user_id' => 'rate-limit-001',
            ],
        ])
        ->assertAccepted()
        ->assertHeader('X-RateLimit-Limit', '1')
        ->assertHeader('X-RateLimit-Remaining', '0');

    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.11'])
        ->withHeaders(eventIngestHeaders('evt-rate-limit-002'))
        ->postJson('/api/v1/events', [
            'event_name' => 'user.created',
            'payload' => [
                'user_id' => 'rate-limit-002',
            ],
        ])
        ->assertTooManyRequests()
        ->assertJsonPath('message', 'Limite de requisicoes excedido para este escopo da API.')
        ->assertJsonPath('data.scope', 'ingest')
        ->assertJsonPath('data.limit', 1)
        ->assertHeader('X-RateLimit-Limit', '1')
        ->assertHeader('X-RateLimit-Remaining', '0');
});

it('applies an independent rate limit bucket for operational endpoints', function (): void {
    config()->set('event_pipeline.rate_limits.ingest.max_attempts', 2);
    config()->set('event_pipeline.rate_limits.operations.max_attempts', 1);
    config()->set('event_pipeline.rate_limits.ingest.decay_seconds', 60);
    config()->set('event_pipeline.rate_limits.operations.decay_seconds', 60);

    $eventId = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.12'])
        ->withHeaders(eventIngestHeaders('evt-rate-limit-003'))
        ->postJson('/api/v1/events', [
            'event_name' => 'user.created',
            'payload' => [
                'user_id' => 'rate-limit-003',
            ],
        ])
        ->assertAccepted()
        ->json('data.id');

    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.12'])
        ->withHeaders(eventOperationsHeaders())
        ->getJson("/api/v1/events/{$eventId}")
        ->assertOk()
        ->assertHeader('X-RateLimit-Limit', '1')
        ->assertHeader('X-RateLimit-Remaining', '0');

    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.12'])
        ->withHeaders(eventOperationsHeaders())
        ->getJson('/api/v1/events')
        ->assertTooManyRequests()
        ->assertJsonPath('data.scope', 'operations');

    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.12'])
        ->withHeaders(eventIngestHeaders('evt-rate-limit-004'))
        ->postJson('/api/v1/events', [
            'event_name' => 'user.created',
            'payload' => [
                'user_id' => 'rate-limit-004',
            ],
        ])
        ->assertAccepted();
});
