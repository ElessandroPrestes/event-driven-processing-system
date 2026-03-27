<?php

use App\Application\Events\Actions\ProcessQueuedEventAction;
use App\Application\Events\Services\EventProcessorRegistry;
use App\Domain\Events\Contracts\EventHistoryRepository;
use App\Domain\Events\Contracts\EventPublisher;
use App\Domain\Events\Contracts\EventRepository;
use App\Domain\Events\Enums\EventStatus;
use Tests\Fakes\FailingEventProcessor;
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

it('processes a queued event and stores the processing result', function (): void {
    $eventId = $this
        ->withHeaders(eventIngestHeaders('evt-process-001'))
        ->postJson('/api/v1/events', [
            'event_name' => 'user.created',
            'payload' => [
                'user_id' => 'user-001',
            ],
        ])
        ->assertAccepted()
        ->json('data.id');

    $result = app(ProcessQueuedEventAction::class)->handle($eventId, 3);

    expect($result->shouldRequeue)->toBeFalse()
        ->and($result->skipped)->toBeFalse()
        ->and($result->event?->status)->toBe(EventStatus::PROCESSED)
        ->and($result->event?->processingAttempts)->toBe(1)
        ->and($result->event?->processingResult)->toMatchArray([
            'resource' => 'user',
            'resource_id' => 'user-001',
        ]);
});

it('requeues the event when processing fails before reaching the retry limit', function (): void {
    app()->instance(
        EventProcessorRegistry::class,
        new EventProcessorRegistry([new FailingEventProcessor('payment.received', 'Falha transitória.')]),
    );

    $eventId = $this
        ->withHeaders(eventIngestHeaders('evt-retry-001'))
        ->postJson('/api/v1/events', [
            'event_name' => 'payment.received',
            'payload' => [
                'payment_id' => 'pay-001',
            ],
        ])
        ->assertAccepted()
        ->json('data.id');

    $result = app(ProcessQueuedEventAction::class)->handle($eventId, 3);

    expect($result->shouldRequeue)->toBeTrue()
        ->and($result->event?->status)->toBe(EventStatus::QUEUED)
        ->and($result->event?->processingAttempts)->toBe(1)
        ->and($result->event?->failureReason)->toBe('Falha transitória.');
});

it('marks the event as processing_failed after reaching the retry limit', function (): void {
    app()->instance(
        EventProcessorRegistry::class,
        new EventProcessorRegistry([new FailingEventProcessor('notification.requested', 'Falha definitiva.')]),
    );

    $eventId = $this
        ->withHeaders(eventIngestHeaders('evt-fail-001'))
        ->postJson('/api/v1/events', [
            'event_name' => 'notification.requested',
            'payload' => [
                'notification_id' => 'not-001',
            ],
        ])
        ->assertAccepted()
        ->json('data.id');

    app(ProcessQueuedEventAction::class)->handle($eventId, 2);
    $result = app(ProcessQueuedEventAction::class)->handle($eventId, 2);

    expect($result->shouldRequeue)->toBeFalse()
        ->and($result->event?->status)->toBe(EventStatus::PROCESSING_FAILED)
        ->and($result->event?->processingAttempts)->toBe(2)
        ->and($result->event?->failureReason)->toBe('Falha definitiva.');
});

it('skips a second processing attempt for an already processed event', function (): void {
    $eventId = $this
        ->withHeaders(eventIngestHeaders('evt-skip-001'))
        ->postJson('/api/v1/events', [
            'event_name' => 'invoice.generated',
            'payload' => [
                'invoice_id' => 'inv-001',
            ],
        ])
        ->assertAccepted()
        ->json('data.id');

    app(ProcessQueuedEventAction::class)->handle($eventId, 3);
    $result = app(ProcessQueuedEventAction::class)->handle($eventId, 3);

    expect($result->skipped)->toBeTrue();
});

it('lists processed events through the api', function (): void {
    $firstEventId = $this
        ->withHeaders(eventIngestHeaders('evt-list-001'))
        ->postJson('/api/v1/events', [
            'event_name' => 'user.created',
            'payload' => [
                'user_id' => 'user-list-001',
            ],
        ])
        ->assertAccepted()
        ->json('data.id');

    $secondEventId = $this
        ->withHeaders(eventIngestHeaders('evt-list-002'))
        ->postJson('/api/v1/events', [
            'event_name' => 'payment.received',
            'payload' => [
                'payment_id' => 'payment-list-001',
            ],
        ])
        ->assertAccepted()
        ->json('data.id');

    app(ProcessQueuedEventAction::class)->handle($firstEventId, 3);
    app(ProcessQueuedEventAction::class)->handle($secondEventId, 3);

    $response = $this
        ->withHeaders(eventOperationsHeaders())
        ->getJson('/api/v1/events?status=processed');

    $response->assertOk()
        ->assertJsonPath('meta.count', 2)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.status', 'processed');
});
