<?php

use App\Application\Events\Contracts\EventQuarantineManager;
use App\Domain\Events\Contracts\EventHistoryRepository;
use App\Domain\Events\Contracts\EventPublisher;
use App\Domain\Events\Contracts\EventRepository;
use Tests\Fakes\FakeEventPublisher;
use Tests\Fakes\FakeEventQuarantineManager;
use Tests\Fakes\InMemoryEventHistoryRepository;
use Tests\Fakes\InMemoryEventRepository;

beforeEach(function (): void {
    configureEventPipelineApiKeys();

    $this->events = new InMemoryEventRepository;
    $this->publisher = new FakeEventPublisher;
    $this->history = new InMemoryEventHistoryRepository;
    $this->quarantine = new FakeEventQuarantineManager;

    app()->instance(EventRepository::class, $this->events);
    app()->instance(EventPublisher::class, $this->publisher);
    app()->instance(EventHistoryRepository::class, $this->history);
    app()->instance(EventQuarantineManager::class, $this->quarantine);
});

it('rejects event ingestion when the ingest api key is missing', function (): void {
    $this->postJson('/api/v1/events', [
        'event_name' => 'user.created',
        'payload' => [
            'user_id' => 'auth-001',
        ],
    ])
        ->assertUnauthorized()
        ->assertJsonPath('message', 'A chave de API informada e invalida.');
});

it('rejects event ingestion when the ingest api key is invalid', function (): void {
    $this->withHeaders(eventIngestHeaders('evt-auth-001', 'invalid-ingest-key'))
        ->postJson('/api/v1/events', [
            'event_name' => 'user.created',
            'payload' => [
                'user_id' => 'auth-002',
            ],
        ])
        ->assertUnauthorized()
        ->assertJsonPath('message', 'A chave de API informada e invalida.');
});

it('rejects operational endpoints when the operations api key is missing', function (): void {
    $eventId = $this
        ->withHeaders(eventIngestHeaders('evt-auth-002'))
        ->postJson('/api/v1/events', [
            'event_name' => 'user.created',
            'payload' => [
                'user_id' => 'auth-003',
            ],
        ])
        ->assertAccepted()
        ->json('data.id');

    $this->getJson("/api/v1/events/{$eventId}")
        ->assertUnauthorized()
        ->assertJsonPath('message', 'A chave de API informada e invalida.');

    $this->getJson('/api/v1/quarantine')
        ->assertUnauthorized()
        ->assertJsonPath('message', 'A chave de API informada e invalida.');
});

it('rejects operational endpoints when the operations api key is invalid', function (): void {
    $this->withHeaders(eventOperationsHeaders('invalid-operations-key'))
        ->postJson('/api/v1/quarantine/replay', [
            'limit' => 1,
        ])
        ->assertUnauthorized()
        ->assertJsonPath('message', 'A chave de API informada e invalida.');
});
