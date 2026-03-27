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

it('paginates the event listing and returns navigation metadata', function (): void {
    foreach (range(1, 3) as $index) {
        $this->withHeaders(eventIngestHeaders(sprintf('evt-pagination-%03d', $index)))
            ->postJson('/api/v1/events', [
                'event_name' => 'user.created',
                'payload' => [
                    'user_id' => sprintf('pagination-%03d', $index),
                ],
            ])
            ->assertAccepted();
    }

    $this->withHeaders(eventOperationsHeaders())
        ->getJson('/api/v1/events?page=1&per_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.count', 2)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.has_more_pages', true);

    $this->withHeaders(eventOperationsHeaders())
        ->getJson('/api/v1/events?page=2&per_page=2')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.count', 1)
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.has_more_pages', false);
});

it('keeps filters when paginating the event listing', function (): void {
    foreach (range(1, 2) as $index) {
        $this->withHeaders(eventIngestHeaders(sprintf('evt-pagination-filter-%03d', $index), null, 'trace-pagination-001'))
            ->postJson('/api/v1/events', [
                'event_name' => 'payment.received',
                'payload' => [
                    'payment_id' => sprintf('payment-pagination-%03d', $index),
                ],
            ])
            ->assertAccepted();
    }

    $this->withHeaders(eventIngestHeaders('evt-pagination-filter-003', null, 'trace-pagination-002'))
        ->postJson('/api/v1/events', [
            'event_name' => 'user.created',
            'payload' => [
                'user_id' => 'pagination-filter-003',
            ],
        ])
        ->assertAccepted();

    $this->withHeaders(eventOperationsHeaders())
        ->getJson('/api/v1/events?trace_id=trace-pagination-001&event_name=payment.received&page=1&per_page=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.count', 1)
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('data.0.trace_id', 'trace-pagination-001')
        ->assertJsonPath('data.0.event_name', 'payment.received');
});

it('validates the maximum page size for the event listing', function (): void {
    config()->set('event_pipeline.api.pagination.events.max_per_page', 2);

    $this->withHeaders(eventOperationsHeaders())
        ->getJson('/api/v1/events?per_page=3')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['per_page']);
});
