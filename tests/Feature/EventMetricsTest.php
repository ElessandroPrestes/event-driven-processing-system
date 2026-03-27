<?php

use App\Application\Events\Actions\ProcessQueuedEventAction;
use App\Application\Events\Services\EventProcessorRegistry;
use App\Domain\Events\Contracts\EventHistoryRepository;
use App\Domain\Events\Contracts\EventPublisher;
use App\Domain\Events\Contracts\EventRepository;
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

it('rejects metrics export when the operations api key is missing', function (): void {
    $this->get('/api/v1/metrics')
        ->assertUnauthorized()
        ->assertJsonPath('message', 'A chave de API informada e invalida.');
});

it('exports operational metrics in prometheus format', function (): void {
    $processedEventId = $this
        ->withHeaders(eventIngestHeaders('evt-metrics-001'))
        ->postJson('/api/v1/events', [
            'event_name' => 'user.created',
            'payload' => [
                'user_id' => 'metrics-001',
            ],
        ])
        ->assertAccepted()
        ->json('data.id');

    app(ProcessQueuedEventAction::class)->handle($processedEventId, 3);

    $this->publisher->shouldFail = true;

    $this
        ->withHeaders(eventIngestHeaders('evt-metrics-002'))
        ->postJson('/api/v1/events', [
            'event_name' => 'payment.received',
            'payload' => [
                'payment_id' => 'metrics-002',
            ],
        ])
        ->assertStatus(503);

    $this->publisher->shouldFail = false;

    $this
        ->withHeaders(eventIngestHeaders('evt-metrics-003'))
        ->postJson('/api/v1/events', [
            'event_name' => 'notification.requested',
            'payload' => [
                'notification_id' => 'metrics-003',
            ],
        ])
        ->assertAccepted();

    app()->instance(
        EventProcessorRegistry::class,
        new EventProcessorRegistry([new FailingEventProcessor('invoice.generated', 'Falha definitiva.')]),
    );

    $failedProcessingEventId = $this
        ->withHeaders(eventIngestHeaders('evt-metrics-004'))
        ->postJson('/api/v1/events', [
            'event_name' => 'invoice.generated',
            'payload' => [
                'invoice_id' => 'metrics-004',
            ],
        ])
        ->assertAccepted()
        ->json('data.id');

    app(ProcessQueuedEventAction::class)->handle($failedProcessingEventId, 1);

    $response = $this->withHeaders(eventOperationsHeaders())
        ->get('/api/v1/metrics')
        ->assertOk();

    expect((string) $response->headers->get('content-type'))
        ->toStartWith('text/plain; version=0.0.4');

    $content = $response->getContent();

    expect($content)
        ->toContain('# HELP eventflow_events_total Current number of persisted events by status and event name.')
        ->toContain('eventflow_events_total{event_name="all",status="processed"} 1')
        ->toContain('eventflow_events_total{event_name="all",status="publish_failed"} 1')
        ->toContain('eventflow_events_total{event_name="all",status="processing_failed"} 1')
        ->toContain('eventflow_events_total{event_name="all",status="queued"} 1')
        ->toContain('eventflow_events_total{event_name="user.created",status="processed"} 1')
        ->toContain('eventflow_events_total{event_name="payment.received",status="publish_failed"} 1')
        ->toContain('eventflow_events_total{event_name="notification.requested",status="queued"} 1')
        ->toContain('eventflow_events_total{event_name="invoice.generated",status="processing_failed"} 1')
        ->toContain('eventflow_events_pending_total{event_name="all"} 1')
        ->toContain('eventflow_events_failed_total{event_name="all"} 2')
        ->toContain('eventflow_events_retryable_total{event_name="all"} 2')
        ->toContain('eventflow_events_last_received_timestamp_seconds{event_name="all"} ')
        ->toContain('eventflow_events_last_processed_timestamp_seconds{event_name="all"} ')
        ->toContain('eventflow_events_oldest_pending_timestamp_seconds{event_name="all"} ');
});
