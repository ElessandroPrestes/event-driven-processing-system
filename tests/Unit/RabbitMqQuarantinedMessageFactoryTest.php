<?php

use App\Application\Events\DataTransferObjects\QuarantinedMessageData;
use App\Domain\Events\Enums\EventStatus;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqQuarantinedMessageFactory;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

it('extracts a quarantined snapshot from an amqp message', function (): void {
    $factory = new RabbitMqQuarantinedMessageFactory;
    $message = new AMQPMessage(json_encode([
        'id' => 'evt-quarantine-factory-001',
        'trace_id' => 'trace-quarantine-factory-001',
        'event_name' => 'notification.requested',
        'payload' => [
            'notification_id' => 'not-001',
        ],
    ], JSON_THROW_ON_ERROR), [
        'message_id' => 'evt-quarantine-factory-001',
        'type' => 'notification.requested',
        'application_headers' => new AMQPTable([
            'trace_id' => 'trace-quarantine-factory-001',
            'idempotency_key' => 'idem-quarantine-factory-001',
            'x-first-death-reason' => 'rejected',
            'x-death' => [
                [
                    'reason' => 'rejected',
                    'queue' => 'eventflow.processing',
                    'routing-keys' => ['notification.requested'],
                ],
            ],
        ]),
    ]);
    $message->setDeliveryInfo(1, false, 'eventflow.events.dlx', 'eventflow.processing.dead');

    $snapshot = $factory->fromAmqpMessage($message, EventStatus::PROCESSING_FAILED);

    expect($snapshot->messageId)->toBe('evt-quarantine-factory-001')
        ->and($snapshot->eventId)->toBe('evt-quarantine-factory-001')
        ->and($snapshot->traceId)->toBe('trace-quarantine-factory-001')
        ->and($snapshot->eventName)->toBe('notification.requested')
        ->and($snapshot->exchange)->toBe('eventflow.events.dlx')
        ->and($snapshot->routingKey)->toBe('eventflow.processing.dead')
        ->and($snapshot->deadLetterReason)->toBe('rejected')
        ->and($snapshot->persistedEventStatus)->toBe(EventStatus::PROCESSING_FAILED)
        ->and($snapshot->deadLetterHistory)->toHaveCount(1);
});

it('builds a replay message and preserves replayable headers only', function (): void {
    $factory = new RabbitMqQuarantinedMessageFactory;
    $snapshot = new QuarantinedMessageData(
        messageId: 'evt-quarantine-factory-002',
        eventId: null,
        traceId: 'trace-quarantine-factory-002',
        eventName: null,
        exchange: 'eventflow.events.dlx',
        routingKey: 'eventflow.processing.dead',
        body: null,
        rawBody: '{"invalid":true}',
        headers: [
            'trace_id' => 'trace-quarantine-factory-002',
            'custom_header' => 'custom-value',
            'x-first-death-reason' => 'rejected',
        ],
        deadLetterHistory: [
            [
                'reason' => 'rejected',
                'routing-keys' => ['payment.received'],
            ],
        ],
        deadLetterReason: 'rejected',
        persistedEventStatus: null,
    );

    $message = $factory->makeReplayMessage($snapshot, '2026-03-27T12:30:00+00:00');
    $headers = $message->get('application_headers');

    expect($message->getBody())->toBe('{"invalid":true}')
        ->and($message->get('message_id'))->toBe('evt-quarantine-factory-002')
        ->and($headers)->toBeInstanceOf(AMQPTable::class)
        ->and($headers->getNativeData())->toMatchArray([
            'trace_id' => 'trace-quarantine-factory-002',
            'custom_header' => 'custom-value',
            'quarantine_replay_source' => 'api',
            'quarantine_replayed_at' => '2026-03-27T12:30:00+00:00',
        ])
        ->and($headers->getNativeData())->not->toHaveKey('x-first-death-reason')
        ->and($factory->resolveReplayRoutingKey($snapshot))->toBe('payment.received');
});
