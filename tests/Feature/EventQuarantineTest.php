<?php

use App\Application\Events\Contracts\EventQuarantineManager;
use App\Application\Events\DataTransferObjects\QuarantinedMessageData;
use App\Domain\Events\Enums\EventStatus;
use Tests\Fakes\FakeEventQuarantineManager;

beforeEach(function (): void {
    configureEventPipelineApiKeys();

    $this->quarantine = new FakeEventQuarantineManager;
    app()->instance(EventQuarantineManager::class, $this->quarantine);
});

it('lists quarantined messages through the api', function (): void {
    $this->quarantine->depth = 3;
    $this->quarantine->messages = [
        makeQuarantinedMessage(
            messageId: 'evt-quarantine-001',
            eventId: 'evt-quarantine-001',
            eventName: 'notification.requested',
            traceId: 'trace-quarantine-001',
            persistedEventStatus: EventStatus::PROCESSING_FAILED,
        ),
        makeQuarantinedMessage(
            messageId: 'raw-quarantine-002',
            eventId: null,
            eventName: null,
            traceId: null,
            rawBody: 'not-json',
            body: null,
            persistedEventStatus: null,
        ),
    ];

    $this->withHeaders(eventOperationsHeaders())
        ->getJson('/api/v1/quarantine?limit=2')
        ->assertOk()
        ->assertJsonPath('meta.count', 2)
        ->assertJsonPath('meta.limit', 2)
        ->assertJsonPath('meta.depth', 3)
        ->assertJsonPath('data.0.message_id', 'evt-quarantine-001')
        ->assertJsonPath('data.0.persisted_event_status', 'processing_failed')
        ->assertJsonPath('data.1.raw_body', 'not-json');

    expect($this->quarantine->inspectedLimit)->toBe(2);
});

it('replays quarantined messages through the api', function (): void {
    $this->quarantine->depth = 2;
    $this->quarantine->replayMessages = [
        makeQuarantinedMessage(
            messageId: 'evt-quarantine-replay-001',
            eventId: 'evt-quarantine-replay-001',
            eventName: 'payment.received',
            traceId: 'trace-quarantine-replay-001',
            persistedEventStatus: EventStatus::QUEUED,
            replayStrategy: 'stored_event',
        ),
    ];

    $this->withHeaders(eventOperationsHeaders())
        ->postJson('/api/v1/quarantine/replay', [
            'limit' => 1,
        ])
        ->assertAccepted()
        ->assertJsonPath('data.replayed_count', 1)
        ->assertJsonPath('data.requested', 1)
        ->assertJsonPath('data.remaining_depth', 1)
        ->assertJsonPath('data.stopped_reason', null)
        ->assertJsonPath('data.messages.0.message_id', 'evt-quarantine-replay-001')
        ->assertJsonPath('data.messages.0.replay_strategy', 'stored_event');

    expect($this->quarantine->replayedLimit)->toBe(1);
});

it('replays targeted quarantined messages by message id through the api', function (): void {
    $this->quarantine->depth = 3;
    $this->quarantine->replayMessages = [
        makeQuarantinedMessage(
            messageId: 'evt-quarantine-replay-010',
            eventId: 'evt-quarantine-replay-010',
            eventName: 'payment.received',
            traceId: 'trace-quarantine-replay-010',
            persistedEventStatus: EventStatus::QUEUED,
            replayStrategy: 'stored_event',
        ),
        makeQuarantinedMessage(
            messageId: 'evt-quarantine-replay-011',
            eventId: 'evt-quarantine-replay-011',
            eventName: 'notification.requested',
            traceId: 'trace-quarantine-replay-011',
            persistedEventStatus: EventStatus::QUEUED,
            replayStrategy: 'stored_event',
        ),
    ];

    $this->withHeaders(eventOperationsHeaders())
        ->postJson('/api/v1/quarantine/replay', [
            'message_ids' => ['evt-quarantine-replay-011'],
        ])
        ->assertAccepted()
        ->assertJsonPath('data.replayed_count', 1)
        ->assertJsonPath('data.requested', 1)
        ->assertJsonPath('data.messages.0.message_id', 'evt-quarantine-replay-011')
        ->assertJsonPath('data.missing_message_ids', []);

    expect($this->quarantine->replayedLimit)->toBe(1)
        ->and($this->quarantine->replayedMessageIds)->toBe(['evt-quarantine-replay-011']);
});

it('validates mutually exclusive replay payloads for quarantine', function (): void {
    $this->withHeaders(eventOperationsHeaders())
        ->postJson('/api/v1/quarantine/replay', [
            'limit' => 1,
            'message_ids' => ['evt-quarantine-replay-099'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['limit', 'message_ids']);
});

it('returns service unavailable when quarantine replay fails before replaying any message', function (): void {
    $this->quarantine->depth = 4;
    $this->quarantine->replayFailure = 'Falha ao republicar a mensagem da quarentena.';

    $this->withHeaders(eventOperationsHeaders())
        ->postJson('/api/v1/quarantine/replay', [
            'limit' => 2,
        ])
        ->assertStatus(503)
        ->assertJsonPath('data.replayed_count', 0)
        ->assertJsonPath('data.requested', 2)
        ->assertJsonPath('data.remaining_depth', 4)
        ->assertJsonPath('data.stopped_reason', 'Falha ao republicar a mensagem da quarentena.');
});

function makeQuarantinedMessage(
    ?string $messageId,
    ?string $eventId,
    ?string $eventName,
    ?string $traceId,
    ?EventStatus $persistedEventStatus,
    ?string $rawBody = null,
    ?array $body = null,
    ?string $replayStrategy = null,
): QuarantinedMessageData {
    return new QuarantinedMessageData(
        messageId: $messageId,
        eventId: $eventId,
        traceId: $traceId,
        eventName: $eventName,
        exchange: 'eventflow.events.dlx',
        routingKey: 'eventflow.processing.dead',
        body: $body ?? [
            'id' => $eventId,
            'trace_id' => $traceId,
            'event_name' => $eventName,
        ],
        rawBody: $rawBody ?? json_encode([
            'id' => $eventId,
            'trace_id' => $traceId,
            'event_name' => $eventName,
        ], JSON_THROW_ON_ERROR),
        headers: [
            'trace_id' => $traceId,
        ],
        deadLetterHistory: [
            [
                'reason' => 'rejected',
                'queue' => 'eventflow.processing',
            ],
        ],
        deadLetterReason: 'rejected',
        persistedEventStatus: $persistedEventStatus,
        replayStrategy: $replayStrategy,
    );
}
