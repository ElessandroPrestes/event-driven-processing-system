<?php

use App\Domain\Events\Contracts\EventHistoryRepository;
use App\Domain\Events\Contracts\EventPublisher;
use App\Domain\Events\Contracts\EventRepository;
use App\Domain\Events\Enums\EventStatus;
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

it('accepts a supported event and queues it', function (): void {
    $response = $this
        ->withHeaders(eventIngestHeaders('evt-user-created-001'))
        ->postJson('/api/v1/events', [
            'event_name' => 'user.created',
            'payload' => [
                'user_id' => '2f88a1af-2235-4ef4-9e11-b5ef6eca9fd1',
                'email' => 'user@example.com',
            ],
            'metadata' => [
                'source' => 'api',
            ],
        ]);

    $response->assertAccepted()
        ->assertJsonPath('data.event_name', 'user.created')
        ->assertJsonPath('data.status', 'queued')
        ->assertJsonPath('meta.duplicate', false);

    $event = $this->events->findByIdempotencyKey('evt-user-created-001');

    expect($event)->not->toBeNull()
        ->and($event?->eventName)->toBe('user.created')
        ->and($event?->status)->toBe(EventStatus::QUEUED);

    expect($this->publisher->published)->toHaveCount(1);
});

it('rejects unsupported event types', function (): void {
    $response = $this
        ->withHeaders(eventIngestHeaders('evt-invalid-001'))
        ->postJson('/api/v1/events', [
            'event_name' => 'user.deleted',
            'payload' => [
                'user_id' => '123',
            ],
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['event_name']);

    expect($this->publisher->published)->toBeEmpty();
});

it('returns the existing event when the same idempotency key is reused', function (): void {
    $firstResponse = $this
        ->withHeaders(eventIngestHeaders('evt-duplicate-001'))
        ->postJson('/api/v1/events', [
            'event_name' => 'payment.received',
            'payload' => [
                'payment_id' => 'pay-001',
            ],
        ]);

    $eventId = $firstResponse->json('data.id');

    $secondResponse = $this
        ->withHeaders(eventIngestHeaders('evt-duplicate-001'))
        ->postJson('/api/v1/events', [
            'event_name' => 'payment.received',
            'payload' => [
                'payment_id' => 'pay-001',
            ],
        ]);

    $secondResponse->assertOk()
        ->assertJsonPath('data.id', $eventId)
        ->assertJsonPath('meta.duplicate', true);

    expect($this->publisher->published)->toHaveCount(1);
});

it('returns conflict when the same idempotency key is reused with a different payload', function (): void {
    $this
        ->withHeaders(eventIngestHeaders('evt-conflict-001'))
        ->postJson('/api/v1/events', [
            'event_name' => 'invoice.generated',
            'payload' => [
                'invoice_id' => 'inv-001',
            ],
        ])
        ->assertAccepted();

    $response = $this
        ->withHeaders(eventIngestHeaders('evt-conflict-001'))
        ->postJson('/api/v1/events', [
            'event_name' => 'invoice.generated',
            'payload' => [
                'invoice_id' => 'inv-002',
            ],
        ]);

    $response->assertConflict()
        ->assertJsonPath('message', 'A chave de idempotencia ja foi utilizada com um payload diferente.');

    expect($this->publisher->published)->toHaveCount(1);
});

it('marks the event as publish_failed when rabbitmq publishing fails', function (): void {
    $this->publisher->shouldFail = true;

    $response = $this
        ->withHeaders(eventIngestHeaders('evt-publish-failed-001'))
        ->postJson('/api/v1/events', [
            'event_name' => 'notification.requested',
            'payload' => [
                'notification_id' => 'not-001',
            ],
        ]);

    $response->assertStatus(503)
        ->assertJsonPath('message', 'Falha ao publicar o evento no RabbitMQ.')
        ->assertJsonPath('data.status', 'publish_failed');

    $event = $this->events->findByIdempotencyKey('evt-publish-failed-001');

    expect($event)->not->toBeNull()
        ->and($event?->status)->toBe(EventStatus::PUBLISH_FAILED);
});

it('shows a stored event by id', function (): void {
    $storeResponse = $this
        ->withHeaders(eventIngestHeaders('evt-show-001'))
        ->postJson('/api/v1/events', [
            'event_name' => 'user.created',
            'payload' => [
                'user_id' => 'show-001',
            ],
        ]);

    $eventId = $storeResponse->json('data.id');

    $this->withHeaders(eventOperationsHeaders())
        ->getJson("/api/v1/events/{$eventId}")
        ->assertOk()
        ->assertJsonPath('data.id', $eventId)
        ->assertJsonPath('data.event_name', 'user.created')
        ->assertJsonPath('data.status', 'queued');
});
