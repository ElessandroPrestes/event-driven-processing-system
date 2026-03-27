<?php

use App\Application\Events\Actions\ProcessQueuedEventAction;
use App\Application\Events\Services\EventProcessorRegistry;
use App\Domain\Events\Contracts\EventPublisher;
use App\Domain\Events\Contracts\EventRepository;
use App\Domain\Events\Enums\EventStatus;
use Tests\Fakes\FailingEventProcessor;
use Tests\Fakes\FakeEventPublisher;
use Tests\Fakes\InMemoryEventRepository;

beforeEach(function (): void {
    $this->events = new InMemoryEventRepository;
    $this->publisher = new FakeEventPublisher;

    app()->instance(EventRepository::class, $this->events);
    app()->instance(EventPublisher::class, $this->publisher);
});

it('retries a publish_failed event through the api', function (): void {
    $this->publisher->shouldFail = true;

    $eventId = $this
        ->withHeaders(['Idempotency-Key' => 'evt-manual-retry-001'])
        ->postJson('/api/v1/events', [
            'event_name' => 'notification.requested',
            'payload' => [
                'notification_id' => 'retry-001',
            ],
        ])
        ->assertStatus(503)
        ->json('data.id');

    $this->publisher->shouldFail = false;

    $this->postJson("/api/v1/events/{$eventId}/retry")
        ->assertAccepted()
        ->assertJsonPath('data.id', $eventId)
        ->assertJsonPath('data.status', 'queued')
        ->assertJsonPath('data.failure_reason', null);

    expect($this->publisher->published)->toHaveCount(1)
        ->and($this->events->findById($eventId)?->status)->toBe(EventStatus::QUEUED);
});

it('retries a processing_failed event through the api', function (): void {
    app()->instance(
        EventProcessorRegistry::class,
        new EventProcessorRegistry([new FailingEventProcessor('invoice.generated', 'Falha definitiva.')]),
    );

    $eventId = $this
        ->withHeaders(['Idempotency-Key' => 'evt-manual-retry-002'])
        ->postJson('/api/v1/events', [
            'event_name' => 'invoice.generated',
            'payload' => [
                'invoice_id' => 'retry-002',
            ],
        ])
        ->assertAccepted()
        ->json('data.id');

    app(ProcessQueuedEventAction::class)->handle($eventId, 1);

    $this->postJson("/api/v1/events/{$eventId}/retry")
        ->assertAccepted()
        ->assertJsonPath('data.id', $eventId)
        ->assertJsonPath('data.status', 'queued');

    expect($this->publisher->published)->toHaveCount(2)
        ->and($this->events->findById($eventId)?->status)->toBe(EventStatus::QUEUED);
});

it('rejects retry when the event is not in a retryable status', function (): void {
    $eventId = $this
        ->withHeaders(['Idempotency-Key' => 'evt-manual-retry-003'])
        ->postJson('/api/v1/events', [
            'event_name' => 'user.created',
            'payload' => [
                'user_id' => 'retry-003',
            ],
        ])
        ->assertAccepted()
        ->json('data.id');

    $this->postJson("/api/v1/events/{$eventId}/retry")
        ->assertConflict()
        ->assertJsonPath('message', 'O evento informado nao pode ser reenfileirado no estado atual.')
        ->assertJsonPath('data.id', $eventId)
        ->assertJsonPath('data.status', 'queued');
});

it('returns an operational summary for the event pipeline', function (): void {
    $processedEventId = $this
        ->withHeaders(['Idempotency-Key' => 'evt-summary-001'])
        ->postJson('/api/v1/events', [
            'event_name' => 'user.created',
            'payload' => [
                'user_id' => 'summary-001',
            ],
        ])
        ->assertAccepted()
        ->json('data.id');

    app(ProcessQueuedEventAction::class)->handle($processedEventId, 3);

    $this->publisher->shouldFail = true;

    $this
        ->withHeaders(['Idempotency-Key' => 'evt-summary-002'])
        ->postJson('/api/v1/events', [
            'event_name' => 'payment.received',
            'payload' => [
                'payment_id' => 'summary-002',
            ],
        ])
        ->assertStatus(503);

    $this->publisher->shouldFail = false;

    $this
        ->withHeaders(['Idempotency-Key' => 'evt-summary-003'])
        ->postJson('/api/v1/events', [
            'event_name' => 'user.created',
            'payload' => [
                'user_id' => 'summary-003',
            ],
        ])
        ->assertAccepted();

    $this->getJson('/api/v1/events/summary')
        ->assertOk()
        ->assertJsonPath('data.total', 3)
        ->assertJsonPath('data.pending', 1)
        ->assertJsonPath('data.failed', 1)
        ->assertJsonPath('data.retryable', 1)
        ->assertJsonPath('data.by_status.processed', 1)
        ->assertJsonPath('data.by_status.publish_failed', 1)
        ->assertJsonPath('data.by_status.queued', 1);

    $this->getJson('/api/v1/events/summary?event_name=user.created')
        ->assertOk()
        ->assertJsonPath('data.total', 2)
        ->assertJsonPath('data.by_status.processed', 1)
        ->assertJsonPath('data.by_status.queued', 1)
        ->assertJsonPath('data.by_status.publish_failed', 0);
});
