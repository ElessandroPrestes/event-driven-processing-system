<?php

use App\Application\Events\DataTransferObjects\QuarantinedMessageData;
use App\Domain\Events\Enums\EventStatus;

it('creates a copy with the persisted event status filled', function (): void {
    $snapshot = makeQuarantinedMessageData();

    $updated = $snapshot->withPersistedEventStatus(EventStatus::PROCESSING_FAILED);

    expect($updated)->not->toBe($snapshot)
        ->and($updated->messageId)->toBe($snapshot->messageId)
        ->and($updated->persistedEventStatus)->toBe(EventStatus::PROCESSING_FAILED)
        ->and($snapshot->persistedEventStatus)->toBeNull();
});

it('creates a copy with the replay strategy and keeps the stored status when none is provided', function (): void {
    $snapshot = makeQuarantinedMessageData()->withPersistedEventStatus(EventStatus::PUBLISH_FAILED);

    $updated = $snapshot->withReplayStrategy('raw_message');

    expect($updated->replayStrategy)->toBe('raw_message')
        ->and($updated->persistedEventStatus)->toBe(EventStatus::PUBLISH_FAILED);
});

it('allows overriding the persisted status when replaying a quarantined message', function (): void {
    $snapshot = makeQuarantinedMessageData()->withPersistedEventStatus(EventStatus::PROCESSING_FAILED);

    $updated = $snapshot->withReplayStrategy('stored_event', EventStatus::QUEUED);

    expect($updated->replayStrategy)->toBe('stored_event')
        ->and($updated->persistedEventStatus)->toBe(EventStatus::QUEUED);
});

function makeQuarantinedMessageData(): QuarantinedMessageData
{
    return new QuarantinedMessageData(
        messageId: 'msg-001',
        eventId: 'evt-001',
        traceId: 'trace-001',
        eventName: 'notification.requested',
        exchange: 'eventflow.events',
        routingKey: 'notification.requested',
        body: [
            'id' => 'evt-001',
        ],
        rawBody: '{"id":"evt-001"}',
        headers: [
            'trace_id' => 'trace-001',
        ],
        deadLetterHistory: null,
        deadLetterReason: null,
        persistedEventStatus: null,
    );
}
