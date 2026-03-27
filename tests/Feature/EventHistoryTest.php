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

it('records the lifecycle history of a successfully processed event', function (): void {
    $eventId = $this
        ->withHeaders(eventIngestHeaders('evt-history-001'))
        ->postJson('/api/v1/events', [
            'event_name' => 'user.created',
            'payload' => [
                'user_id' => 'history-001',
            ],
        ])
        ->assertAccepted()
        ->json('data.id');

    app(ProcessQueuedEventAction::class)->handle($eventId, 3);

    $this->withHeaders(eventOperationsHeaders())
        ->getJson("/api/v1/events/{$eventId}/history")
        ->assertOk()
        ->assertJsonPath('meta.count', 4)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.per_page', 20)
        ->assertJsonPath('meta.total', 4)
        ->assertJsonPath('meta.last_page', 1)
        ->assertJsonPath('meta.has_more_pages', false)
        ->assertJsonPath('data.0.action', 'received')
        ->assertJsonPath('data.0.to_status', 'received')
        ->assertJsonPath('data.1.action', 'queued')
        ->assertJsonPath('data.1.to_status', 'queued')
        ->assertJsonPath('data.2.action', 'processing_started')
        ->assertJsonPath('data.2.to_status', 'processing')
        ->assertJsonPath('data.3.action', 'processed')
        ->assertJsonPath('data.3.to_status', 'processed')
        ->assertJsonPath('data.3.context.processing_attempts', 1)
        ->assertJsonPath('data.3.context.processing_result.resource', 'user');
});

it('records manual retry attempts in the event history', function (): void {
    app()->instance(
        EventProcessorRegistry::class,
        new EventProcessorRegistry([new FailingEventProcessor('invoice.generated', 'Falha definitiva.')]),
    );

    $eventId = $this
        ->withHeaders(eventIngestHeaders('evt-history-002'))
        ->postJson('/api/v1/events', [
            'event_name' => 'invoice.generated',
            'payload' => [
                'invoice_id' => 'history-002',
            ],
        ])
        ->assertAccepted()
        ->json('data.id');

    app(ProcessQueuedEventAction::class)->handle($eventId, 1);

    $this->withHeaders(eventOperationsHeaders())
        ->postJson("/api/v1/events/{$eventId}/retry")
        ->assertAccepted();

    $response = $this
        ->withHeaders(eventOperationsHeaders())
        ->getJson("/api/v1/events/{$eventId}/history");

    $response->assertOk()
        ->assertJsonPath('meta.count', 6)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.total', 6)
        ->assertJsonPath('data.3.action', 'processing_failed')
        ->assertJsonPath('data.4.action', 'retry_requested')
        ->assertJsonPath('data.4.source', 'api')
        ->assertJsonPath('data.5.action', 'retry_enqueued')
        ->assertJsonPath('data.5.from_status', 'processing_failed')
        ->assertJsonPath('data.5.to_status', 'queued');
});

it('paginates the event history and keeps chronological ordering', function (): void {
    $eventId = $this
        ->withHeaders(eventIngestHeaders('evt-history-003'))
        ->postJson('/api/v1/events', [
            'event_name' => 'user.created',
            'payload' => [
                'user_id' => 'history-003',
            ],
        ])
        ->assertAccepted()
        ->json('data.id');

    $this->history->record(
        eventId: $eventId,
        action: 'processing_started',
        source: 'worker',
        fromStatus: EventStatus::QUEUED,
        toStatus: EventStatus::PROCESSING,
        context: [
            'attempt' => 1,
        ],
    );

    $this->history->record(
        eventId: $eventId,
        action: 'processing_failed',
        source: 'worker',
        fromStatus: EventStatus::PROCESSING,
        toStatus: EventStatus::PROCESSING_FAILED,
        context: [
            'reason' => 'Falha de teste.',
        ],
    );

    $this->history->record(
        eventId: $eventId,
        action: 'retry_requested',
        source: 'api',
        fromStatus: EventStatus::PROCESSING_FAILED,
        toStatus: EventStatus::PROCESSING_FAILED,
        context: [
            'requested_by' => 'tests',
        ],
    );

    $this->withHeaders(eventOperationsHeaders())
        ->getJson("/api/v1/events/{$eventId}/history?page=2&per_page=2")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.count', 2)
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.total', 5)
        ->assertJsonPath('meta.last_page', 3)
        ->assertJsonPath('meta.has_more_pages', true)
        ->assertJsonPath('data.0.action', 'processing_started')
        ->assertJsonPath('data.1.action', 'processing_failed');
});

it('validates the maximum page size for the event history listing', function (): void {
    config()->set('event_pipeline.api.pagination.event_history.max_per_page', 2);

    $eventId = $this
        ->withHeaders(eventIngestHeaders('evt-history-004'))
        ->postJson('/api/v1/events', [
            'event_name' => 'user.created',
            'payload' => [
                'user_id' => 'history-004',
            ],
        ])
        ->assertAccepted()
        ->json('data.id');

    $this->withHeaders(eventOperationsHeaders())
        ->getJson("/api/v1/events/{$eventId}/history?per_page=3")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['per_page']);
});
