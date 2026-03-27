<?php

use App\Domain\Events\Enums\EventStatus;
use App\Infrastructure\Messaging\RabbitMq\Contracts\AmqpConnectionFactory;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqDelayedRetryScheduler;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqEventMessageFactory;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqEventPublisher;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqTopologyManager;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

afterEach(function (): void {
    Mockery::close();
});

it('publishes events through the main exchange and closes rabbitmq resources', function (): void {
    config()->set('event_pipeline.rabbitmq.exchange', 'eventflow.events');

    $event = storedEvent('user.created', [
        'user_id' => 'publisher-001',
    ]);
    $connection = Mockery::mock(AMQPStreamConnection::class);
    $channel = Mockery::mock(AMQPChannel::class)->shouldIgnoreMissing();
    $connections = Mockery::mock(AmqpConnectionFactory::class);

    $connections->shouldReceive('make')->once()->andReturn($connection);
    $connection->shouldReceive('channel')->once()->andReturn($channel);
    $channel->shouldReceive('basic_publish')
        ->once()
        ->withArgs(function (AMQPMessage $message, string $exchange, string $routingKey) use ($event): bool {
            return $exchange === 'eventflow.events'
                && $routingKey === 'user.created'
                && $message->get('message_id') === $event->id
                && $message->get('type') === $event->eventName;
        });
    $channel->shouldReceive('close')->once();
    $connection->shouldReceive('close')->once();

    (new RabbitMqEventPublisher($connections, new RabbitMqEventMessageFactory, new RabbitMqTopologyManager))->publish($event);
});

it('schedules delayed retries through the retry exchange and closes rabbitmq resources', function (): void {
    config()->set('event_pipeline.rabbitmq.retry_exchange', 'eventflow.events.retry');
    config()->set('event_pipeline.rabbitmq.retry_routing_key', 'eventflow.processing.retry');

    $event = storedEvent('payment.received', [
        'payment_id' => 'retry-001',
    ], EventStatus::QUEUED, [
        'processing_attempts' => 2,
    ]);
    $connection = Mockery::mock(AMQPStreamConnection::class);
    $channel = Mockery::mock(AMQPChannel::class)->shouldIgnoreMissing();
    $connections = Mockery::mock(AmqpConnectionFactory::class);

    $connections->shouldReceive('make')->once()->andReturn($connection);
    $connection->shouldReceive('channel')->once()->andReturn($channel);
    $channel->shouldReceive('basic_publish')
        ->once()
        ->withArgs(function (AMQPMessage $message, string $exchange, string $routingKey) use ($event): bool {
            $headers = $message->get('application_headers')->getNativeData();

            return $exchange === 'eventflow.events.retry'
                && $routingKey === 'eventflow.processing.retry'
                && $message->get('message_id') === $event->id
                && $message->get('expiration') === '5000'
                && ($headers['retry_delay_ms'] ?? null) === 5000
                && ($headers['processing_attempts'] ?? null) === 2;
        });
    $channel->shouldReceive('close')->once();
    $connection->shouldReceive('close')->once();

    (new RabbitMqDelayedRetryScheduler($connections, new RabbitMqEventMessageFactory, new RabbitMqTopologyManager))->schedule($event, 5000);
});
