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
    $this->events = new InMemoryEventRepository;
    $this->publisher = new FakeEventPublisher;
    $this->history = new InMemoryEventHistoryRepository;

    app()->instance(EventRepository::class, $this->events);
    app()->instance(EventPublisher::class, $this->publisher);
    app()->instance(EventHistoryRepository::class, $this->history);
});

it('records the lifecycle history of a successfully processed event', function (): void {
    $eventId = $this
        ->withHeaders(['Idempotency-Key' => 'evt-history-001'])
        ->postJson('/api/v1/events', [
            'event_name' => 'user.created',
            'payload' => [
                'user_id' => 'history-001',
            ],
        ])
        ->assertAccepted()
        ->json('data.id');

    app(ProcessQueuedEventAction::class)->handle($eventId, 3);

    $this->getJson("/api/v1/events/{$eventId}/history")
        ->assertOk()
        ->assertJsonPath('meta.count', 4)
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
        ->withHeaders(['Idempotency-Key' => 'evt-history-002'])
        ->postJson('/api/v1/events', [
            'event_name' => 'invoice.generated',
            'payload' => [
                'invoice_id' => 'history-002',
            ],
        ])
        ->assertAccepted()
        ->json('data.id');

    app(ProcessQueuedEventAction::class)->handle($eventId, 1);

    $this->postJson("/api/v1/events/{$eventId}/retry")
        ->assertAccepted();

    $response = $this->getJson("/api/v1/events/{$eventId}/history");

    $response->assertOk()
        ->assertJsonPath('meta.count', 6)
        ->assertJsonPath('data.3.action', 'processing_failed')
        ->assertJsonPath('data.4.action', 'retry_requested')
        ->assertJsonPath('data.4.source', 'api')
        ->assertJsonPath('data.5.action', 'retry_enqueued')
        ->assertJsonPath('data.5.from_status', 'processing_failed')
        ->assertJsonPath('data.5.to_status', 'queued');
});
