<?php

use App\Application\Events\Actions\ReceiveEventAction;
use App\Application\Events\Services\EventHistoryRecorder;
use App\Application\Events\Support\EventPayloadDataFactory;
use App\Domain\Events\Enums\EventStatus;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqInboundEventMessageHandler;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqInboundEventPayloadFactory;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Tests\Fakes\FakeEventPublisher;
use Tests\Fakes\InMemoryEventHistoryRepository;
use Tests\Fakes\InMemoryEventRepository;

afterEach(function (): void {
    Mockery::close();
});

it('accepts a valid inbound rabbitmq message and enqueues the persisted event', function (): void {
    [$handler, $events, $publisher, $history] = makeRabbitMqInboundHandler();

    [$message, $channel] = makeInboundBrokerMessage(
        body: [
            'payload' => [
                'user_id' => 'amqp-user-001',
                'email' => 'amqp@example.com',
            ],
        ],
        routingKey: 'user.created',
        properties: [
            'message_id' => 'amqp-ingest-001',
            'type' => 'user.created',
            'application_headers' => new AMQPTable([
                'trace_id' => 'trace-amqp-001',
            ]),
        ],
    );

    $channel->shouldReceive('basic_ack')
        ->once()
        ->with(1, false);

    $event = $handler->handle($message);
    $entries = $history->listForEvent($event?->id ?? '');

    expect($event)->not->toBeNull()
        ->and($event?->status)->toBe(EventStatus::QUEUED)
        ->and($event?->eventName)->toBe('user.created')
        ->and($event?->idempotencyKey)->toBe('amqp-ingest-001')
        ->and($event?->traceId)->toBe('trace-amqp-001')
        ->and($events->findByIdempotencyKey('amqp-ingest-001')?->id)->toBe($event?->id)
        ->and($publisher->published)->toHaveCount(1)
        ->and($entries)->toHaveCount(2)
        ->and($entries[0]->source)->toBe('amqp')
        ->and($entries[1]->source)->toBe('amqp');
});

it('acknowledges duplicate inbound rabbitmq messages without republishing', function (): void {
    [$handler, $events, $publisher] = makeRabbitMqInboundHandler();

    [$firstMessage, $firstChannel] = makeInboundBrokerMessage(
        body: [
            'event_name' => 'payment.received',
            'payload' => [
                'payment_id' => 'amqp-duplicate-001',
            ],
            'idempotency_key' => 'amqp-duplicate-001',
        ],
        routingKey: 'payment.received',
    );

    $firstChannel->shouldReceive('basic_ack')
        ->once()
        ->with(1, false);

    $firstEvent = $handler->handle($firstMessage);

    [$secondMessage, $secondChannel] = makeInboundBrokerMessage(
        body: [
            'event_name' => 'payment.received',
            'payload' => [
                'payment_id' => 'amqp-duplicate-001',
            ],
            'idempotency_key' => 'amqp-duplicate-001',
        ],
        routingKey: 'payment.received',
    );

    $secondChannel->shouldReceive('basic_ack')
        ->once()
        ->with(1, false);

    $secondEvent = $handler->handle($secondMessage);

    expect($firstEvent)->not->toBeNull()
        ->and($secondEvent)->not->toBeNull()
        ->and($secondEvent?->id)->toBe($firstEvent?->id)
        ->and($events->findByIdempotencyKey('amqp-duplicate-001')?->id)->toBe($firstEvent?->id)
        ->and($publisher->published)->toHaveCount(1);
});

it('dead letters invalid inbound rabbitmq payloads', function (): void {
    [$handler] = makeRabbitMqInboundHandler();

    [$message, $channel] = makeInboundBrokerMessage(
        body: [
            'event_name' => 'user.deleted',
            'payload' => [
                'user_id' => 'invalid-001',
            ],
            'idempotency_key' => 'invalid-001',
        ],
        routingKey: 'user.deleted',
    );

    $channel->shouldReceive('basic_nack')
        ->once()
        ->with(1, false, false);

    $event = $handler->handle($message);

    expect($event)->toBeNull();
});

it('acknowledges inbound rabbitmq messages when publication to the processing exchange fails', function (): void {
    [$handler, $events, $publisher, $history] = makeRabbitMqInboundHandler();
    $publisher->shouldFail = true;

    [$message, $channel] = makeInboundBrokerMessage(
        body: [
            'event_name' => 'notification.requested',
            'payload' => [
                'notification_id' => 'publish-failed-001',
            ],
            'idempotency_key' => 'publish-failed-001',
            'trace_id' => 'trace-publish-failed-001',
        ],
        routingKey: 'notification.requested',
    );

    $channel->shouldReceive('basic_ack')
        ->once()
        ->with(1, false);

    $event = $handler->handle($message);
    $entries = $history->listForEvent($event?->id ?? '');

    expect($event)->not->toBeNull()
        ->and($event?->status)->toBe(EventStatus::PUBLISH_FAILED)
        ->and($events->findByIdempotencyKey('publish-failed-001')?->status)->toBe(EventStatus::PUBLISH_FAILED)
        ->and($entries)->toHaveCount(2)
        ->and($entries[0]->source)->toBe('amqp')
        ->and($entries[1]->action)->toBe('publish_failed')
        ->and($entries[1]->source)->toBe('amqp');
});

/**
 * @return array{0: RabbitMqInboundEventMessageHandler, 1: InMemoryEventRepository, 2: FakeEventPublisher, 3: InMemoryEventHistoryRepository}
 */
function makeRabbitMqInboundHandler(): array
{
    $events = new InMemoryEventRepository;
    $publisher = new FakeEventPublisher;
    $history = new InMemoryEventHistoryRepository;
    $historyRecorder = new EventHistoryRecorder($history);

    return [
        new RabbitMqInboundEventMessageHandler(
            new ReceiveEventAction($events, $publisher, $historyRecorder),
            new RabbitMqInboundEventPayloadFactory(new EventPayloadDataFactory),
        ),
        $events,
        $publisher,
        $history,
    ];
}

/**
 * @param  array<string, mixed>  $body
 * @param  array<string, mixed>  $properties
 * @return array{0: AMQPMessage, 1: AMQPChannel}
 */
function makeInboundBrokerMessage(array $body, string $routingKey, array $properties = []): array
{
    $message = new AMQPMessage(json_encode($body, JSON_THROW_ON_ERROR), $properties);
    $channel = Mockery::mock(AMQPChannel::class);

    $message->setChannel($channel);
    $message->setDeliveryInfo(1, false, 'eventflow.events.ingest', $routingKey);

    return [$message, $channel];
}
