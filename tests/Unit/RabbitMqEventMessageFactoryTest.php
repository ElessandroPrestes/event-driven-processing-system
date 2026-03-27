<?php

use App\Domain\Events\DataTransferObjects\StoredEventData;
use App\Domain\Events\Enums\EventStatus;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqEventMessageFactory;
use Carbon\CarbonImmutable;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

it('builds a persistent rabbitmq message with shared payload and headers', function (): void {
    $factory = new RabbitMqEventMessageFactory;
    $event = makeStoredRabbitMqEventData();

    $message = $factory->make(
        $event,
        properties: [
            'expiration' => '5000',
        ],
        applicationHeaders: [
            'retry_delay_ms' => 5000,
            'processing_attempts' => $event->processingAttempts,
        ],
    );

    $headers = $message->get('application_headers');

    expect($message->getBody())->toBe(json_encode([
        'id' => $event->id,
        'trace_id' => $event->traceId,
        'event_name' => $event->eventName,
        'payload' => $event->payload,
        'metadata' => $event->metadata,
        'status' => $event->status->value,
        'occurred_at' => $event->occurredAt?->toIso8601String(),
        'queued_at' => $event->queuedAt?->toIso8601String(),
        'created_at' => $event->createdAt->toIso8601String(),
    ], JSON_THROW_ON_ERROR))
        ->and($message->get('content_type'))->toBe('application/json')
        ->and($message->get('delivery_mode'))->toBe(AMQPMessage::DELIVERY_MODE_PERSISTENT)
        ->and($message->get('message_id'))->toBe($event->id)
        ->and($message->get('type'))->toBe($event->eventName)
        ->and($message->get('expiration'))->toBe('5000')
        ->and($headers)->toBeInstanceOf(AMQPTable::class)
        ->and($headers->getNativeData())->toMatchArray([
            'idempotency_key' => $event->idempotencyKey,
            'trace_id' => $event->traceId,
            'retry_delay_ms' => 5000,
            'processing_attempts' => $event->processingAttempts,
        ]);
});

function makeStoredRabbitMqEventData(): StoredEventData
{
    $occurredAt = CarbonImmutable::parse('2026-03-27T12:00:00Z');
    $queuedAt = $occurredAt->addSecond();
    $createdAt = $occurredAt->addSeconds(2);

    return new StoredEventData(
        id: 'evt-rabbitmq-message-001',
        traceId: 'trace-rabbitmq-message-001',
        eventName: 'payment.received',
        payload: [
            'payment_id' => 'pay-001',
        ],
        metadata: [
            'source' => 'unit-test',
        ],
        status: EventStatus::QUEUED,
        idempotencyKey: 'idem-rabbitmq-message-001',
        contentHash: 'hash-rabbitmq-message-001',
        occurredAt: $occurredAt,
        queuedAt: $queuedAt,
        consumedAt: null,
        processedAt: null,
        processingAttempts: 2,
        processingResult: null,
        failureReason: null,
        createdAt: $createdAt,
        updatedAt: $createdAt,
    );
}
