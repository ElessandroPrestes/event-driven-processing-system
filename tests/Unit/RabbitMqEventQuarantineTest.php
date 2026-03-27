<?php

use App\Application\Events\Services\EventHistoryRecorder;
use App\Domain\Events\DataTransferObjects\EventPayloadData;
use App\Domain\Events\DataTransferObjects\StoredEventData;
use App\Domain\Events\Enums\EventStatus;
use App\Infrastructure\Messaging\RabbitMq\Contracts\AmqpConnectionFactory;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqEventMessageFactory;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqEventQuarantine;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqQuarantinedMessageFactory;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqTopologyManager;
use Carbon\CarbonImmutable;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Tests\Fakes\InMemoryEventHistoryRepository;
use Tests\Fakes\InMemoryEventRepository;

afterEach(function (): void {
    Mockery::close();
});

it('inspects quarantined messages and requeues them back to the dead-letter queue', function (): void {
    config()->set('event_pipeline.rabbitmq.dead_letter_queue', 'eventflow.processing.dead');
    config()->set('event_pipeline.rabbitmq.durable', true);

    $events = new InMemoryEventRepository;
    $history = new EventHistoryRecorder(new InMemoryEventHistoryRepository);
    $persisted = storeQuarantinedEvent($events, EventStatus::PROCESSING_FAILED);
    $channel = Mockery::mock(AMQPChannel::class)->shouldIgnoreMissing();
    $messageOne = brokerMessage(
        body: json_encode(['id' => $persisted->id], JSON_THROW_ON_ERROR),
        channel: $channel,
        deliveryTag: 1,
        routingKey: 'notification.requested',
        properties: [
            'message_id' => $persisted->id,
            'type' => 'notification.requested',
            'application_headers' => new AMQPTable([
                'trace_id' => 'trace-quarantine-001',
            ]),
        ],
    );
    $messageTwo = brokerMessage(
        body: '{"raw":true}',
        channel: $channel,
        deliveryTag: 2,
        routingKey: 'notification.requested',
        properties: [
            'application_headers' => new AMQPTable([
                'trace_id' => 'trace-quarantine-002',
                'x-death' => [
                    [
                        'routing-keys' => ['notification.requested'],
                        'reason' => 'rejected',
                    ],
                ],
            ]),
        ],
    );

    $manager = makeRabbitMqEventQuarantine(
        events: $events,
        history: $history,
        configureChannel: function (AMQPChannel $channel) use ($messageOne, $messageTwo): void {
            $channel->shouldReceive('queue_declare')
                ->twice()
                ->with('eventflow.processing.dead', false, true, false, false)
                ->andReturn([null, 0], [null, 2]);
            $channel->shouldReceive('basic_get')
                ->once()
                ->with('eventflow.processing.dead', false)
                ->andReturn($messageOne);
            $channel->shouldReceive('basic_get')
                ->once()
                ->with('eventflow.processing.dead', false)
                ->andReturn($messageTwo);
            $channel->shouldReceive('basic_nack')->once()->with(2, false, true)->ordered();
            $channel->shouldReceive('basic_nack')->once()->with(1, false, true)->ordered();
        },
        channel: $channel,
    );

    $inspection = $manager->inspect(10);

    expect($inspection->depth)->toBe(2)
        ->and($inspection->messages)->toHaveCount(2)
        ->and($inspection->messages[0]->persistedEventStatus)->toBe(EventStatus::PROCESSING_FAILED)
        ->and($inspection->messages[1]->persistedEventStatus)->toBeNull();
});

it('replays stored failed events back to the main exchange and records the history', function (): void {
    config()->set('event_pipeline.rabbitmq.dead_letter_queue', 'eventflow.processing.dead');
    config()->set('event_pipeline.rabbitmq.durable', true);
    config()->set('event_pipeline.rabbitmq.exchange', 'eventflow.events');

    $events = new InMemoryEventRepository;
    $historyRepository = new InMemoryEventHistoryRepository;
    $history = new EventHistoryRecorder($historyRepository);
    $event = storeQuarantinedEvent($events, EventStatus::PROCESSING_FAILED);
    $channel = Mockery::mock(AMQPChannel::class)->shouldIgnoreMissing();
    $message = brokerMessage(
        body: json_encode([
            'id' => $event->id,
            'event_name' => $event->eventName,
            'trace_id' => $event->traceId,
        ], JSON_THROW_ON_ERROR),
        channel: $channel,
        deliveryTag: 1,
        routingKey: 'notification.requested',
        properties: [
            'message_id' => $event->id,
            'type' => 'notification.requested',
            'application_headers' => new AMQPTable([
                'trace_id' => $event->traceId,
            ]),
        ],
    );

    $manager = makeRabbitMqEventQuarantine(
        events: $events,
        history: $history,
        configureChannel: function (AMQPChannel $channel) use ($message, $event): void {
            $channel->shouldReceive('queue_declare')
                ->times(3)
                ->with('eventflow.processing.dead', false, true, false, false)
                ->andReturn([null, 0], [null, 1], [null, 0]);
            $channel->shouldReceive('basic_get')
                ->once()
                ->with('eventflow.processing.dead', false)
                ->andReturn($message);
            $channel->shouldReceive('basic_publish')
                ->once()
                ->withArgs(function (AMQPMessage $replayedMessage, string $exchange, string $routingKey) use ($event): bool {
                    $headers = $replayedMessage->get('application_headers')->getNativeData();

                    return $exchange === 'eventflow.events'
                        && $routingKey === 'notification.requested'
                        && $replayedMessage->get('message_id') === $event->id
                        && ($headers['quarantine_replay_source'] ?? null) === 'api'
                        && is_string($headers['quarantine_replayed_at'] ?? null);
                });
            $channel->shouldReceive('basic_ack')->once()->with(1, false);
        },
        channel: $channel,
    );

    $result = $manager->replay(5);
    $entries = $historyRepository->listForEvent($event->id);

    expect($result->replayedCount)->toBe(1)
        ->and($result->remainingDepth)->toBe(0)
        ->and($result->messages[0]->replayStrategy)->toBe('stored_event')
        ->and($result->messages[0]->persistedEventStatus)->toBe(EventStatus::QUEUED)
        ->and($events->findById($event->id)?->status)->toBe(EventStatus::QUEUED)
        ->and($entries)->toHaveCount(2)
        ->and($entries[0]->action)->toBe('quarantine_replay_requested')
        ->and($entries[1]->action)->toBe('quarantine_replayed');
});

it('replays raw quarantined messages when no retryable stored event exists', function (): void {
    config()->set('event_pipeline.rabbitmq.dead_letter_queue', 'eventflow.processing.dead');
    config()->set('event_pipeline.rabbitmq.durable', true);
    config()->set('event_pipeline.rabbitmq.exchange', 'eventflow.events');

    $events = new InMemoryEventRepository;
    $history = new EventHistoryRecorder(new InMemoryEventHistoryRepository);
    $channel = Mockery::mock(AMQPChannel::class)->shouldIgnoreMissing();
    $message = brokerMessage(
        body: '{"raw":true}',
        channel: $channel,
        deliveryTag: 1,
        routingKey: 'eventflow.processing.dead',
        properties: [
            'application_headers' => new AMQPTable([
                'trace_id' => 'trace-quarantine-raw-001',
                'x-death' => [
                    [
                        'routing-keys' => ['notification.requested'],
                        'reason' => 'rejected',
                    ],
                ],
            ]),
        ],
    );

    $manager = makeRabbitMqEventQuarantine(
        events: $events,
        history: $history,
        configureChannel: function (AMQPChannel $channel) use ($message): void {
            $channel->shouldReceive('queue_declare')
                ->times(3)
                ->with('eventflow.processing.dead', false, true, false, false)
                ->andReturn([null, 0], [null, 1], [null, 0]);
            $channel->shouldReceive('basic_get')
                ->once()
                ->with('eventflow.processing.dead', false)
                ->andReturn($message);
            $channel->shouldReceive('basic_publish')
                ->once()
                ->withArgs(function (AMQPMessage $replayedMessage, string $exchange, string $routingKey): bool {
                    $headers = $replayedMessage->get('application_headers')->getNativeData();

                    return $exchange === 'eventflow.events'
                        && $routingKey === 'notification.requested'
                        && $replayedMessage->getBody() === '{"raw":true}'
                        && ($headers['trace_id'] ?? null) === 'trace-quarantine-raw-001'
                        && ($headers['quarantine_replay_source'] ?? null) === 'api'
                        && ! array_key_exists('x-death', $headers);
                });
            $channel->shouldReceive('basic_ack')->once()->with(1, false);
        },
        channel: $channel,
    );

    $result = $manager->replay(2);

    expect($result->replayedCount)->toBe(1)
        ->and($result->messages[0]->replayStrategy)->toBe('raw_message')
        ->and($result->remainingDepth)->toBe(0);
});

it('stops the replay batch when republication fails and keeps the message quarantined', function (): void {
    config()->set('event_pipeline.rabbitmq.dead_letter_queue', 'eventflow.processing.dead');
    config()->set('event_pipeline.rabbitmq.durable', true);

    $events = new InMemoryEventRepository;
    $history = new EventHistoryRecorder(new InMemoryEventHistoryRepository);
    $channel = Mockery::mock(AMQPChannel::class)->shouldIgnoreMissing();
    $message = brokerMessage(
        body: '{"raw":true}',
        channel: $channel,
        deliveryTag: 1,
        routingKey: 'eventflow.processing.dead',
        properties: [
            'application_headers' => new AMQPTable([
                'trace_id' => 'trace-quarantine-raw-002',
                'x-death' => [
                    [
                        'routing-keys' => ['notification.requested'],
                        'reason' => 'rejected',
                    ],
                ],
            ]),
        ],
    );

    $manager = makeRabbitMqEventQuarantine(
        events: $events,
        history: $history,
        configureChannel: function (AMQPChannel $channel) use ($message): void {
            $channel->shouldReceive('queue_declare')
                ->times(3)
                ->with('eventflow.processing.dead', false, true, false, false)
                ->andReturn([null, 0], [null, 1], [null, 1]);
            $channel->shouldReceive('basic_get')
                ->once()
                ->with('eventflow.processing.dead', false)
                ->andReturn($message);
            $channel->shouldReceive('basic_publish')
                ->once()
                ->andThrow(new RuntimeException('Falha na republicacao.'));
            $channel->shouldReceive('basic_nack')->once()->with(1, false, true);
        },
        channel: $channel,
    );

    $result = $manager->replay(3);

    expect($result->replayedCount)->toBe(0)
        ->and($result->remainingDepth)->toBe(1)
        ->and($result->stoppedReason)->toBe('Falha na republicacao.');
});

/**
 * @param  callable(AMQPChannel): void  $configureChannel
 */
function makeRabbitMqEventQuarantine(
    InMemoryEventRepository $events,
    EventHistoryRecorder $history,
    callable $configureChannel,
    ?AMQPChannel $channel = null,
): RabbitMqEventQuarantine {
    $connection = Mockery::mock(AMQPStreamConnection::class);
    $channel ??= Mockery::mock(AMQPChannel::class)->shouldIgnoreMissing();
    $connections = Mockery::mock(AmqpConnectionFactory::class);

    $connections->shouldReceive('make')->once()->andReturn($connection);
    $connection->shouldReceive('channel')->once()->andReturn($channel);
    $configureChannel($channel);
    $channel->shouldReceive('close')->once();
    $connection->shouldReceive('close')->once();

    return new RabbitMqEventQuarantine(
        $events,
        $history,
        $connections,
        new RabbitMqEventMessageFactory,
        new RabbitMqQuarantinedMessageFactory,
        new RabbitMqTopologyManager,
    );
}

function brokerMessage(
    string $body,
    AMQPChannel $channel,
    int $deliveryTag,
    string $routingKey,
    array $properties = [],
): AMQPMessage {
    $message = new AMQPMessage($body, $properties);
    $message->setChannel($channel);
    $message->setDeliveryInfo($deliveryTag, false, 'eventflow.events.dlx', $routingKey);

    return $message;
}

function storeQuarantinedEvent(InMemoryEventRepository $events, EventStatus $status): StoredEventData
{
    $event = $events->create(new EventPayloadData(
        eventName: 'notification.requested',
        payload: [
            'notification_id' => sprintf('quarantine-%s', uniqid('', true)),
        ],
        metadata: null,
        idempotencyKey: sprintf('idem-%s', uniqid('', true)),
        occurredAt: CarbonImmutable::now(),
        traceId: sprintf('trace-%s', uniqid('', true)),
    ));

    $queued = $events->markAsQueued($event->id, CarbonImmutable::now());

    if ($status === EventStatus::PUBLISH_FAILED) {
        return $events->markAsPublishFailed($queued->id, 'Falha de publicacao.');
    }

    $processing = $events->markAsProcessing($queued->id, CarbonImmutable::now());

    if ($status === EventStatus::PROCESSING_FAILED) {
        return $events->markAsProcessingFailed($processing->id, 'Falha de processamento.');
    }

    return $processing;
}
