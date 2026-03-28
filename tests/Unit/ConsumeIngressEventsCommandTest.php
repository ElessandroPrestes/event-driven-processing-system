<?php

use App\Application\Events\Actions\ReceiveEventAction;
use App\Application\Events\Services\EventHistoryRecorder;
use App\Application\Events\Support\EventPayloadDataFactory;
use App\Console\Commands\ConsumeIngressEventsCommand;
use App\Infrastructure\Messaging\RabbitMq\Contracts\AmqpConnectionFactory;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqInboundEventMessageHandler;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqInboundEventPayloadFactory;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use Tests\Fakes\FakeEventPublisher;
use Tests\Fakes\InMemoryEventHistoryRepository;
use Tests\Fakes\InMemoryEventRepository;

afterEach(function (): void {
    Mockery::close();
});

it('consumes a single inbound rabbitmq message when running in once mode', function (): void {
    config()->set('event_pipeline.rabbitmq.ingest.queue', 'eventflow.ingest');
    config()->set('event_pipeline.consumer.idle_timeout', 5);

    [$handler] = makeIngressCommandHandler();
    $connections = Mockery::mock(AmqpConnectionFactory::class);
    $connection = Mockery::mock(AMQPStreamConnection::class);
    $channel = Mockery::mock(AMQPChannel::class)->shouldIgnoreMissing();
    $message = new AMQPMessage(json_encode([
        'event_name' => 'user.created',
        'payload' => [
            'user_id' => 'ingest-command-001',
        ],
        'idempotency_key' => 'ingest-command-001',
        'trace_id' => 'trace-ingest-command-001',
    ], JSON_THROW_ON_ERROR));

    $message->setChannel($channel);
    $message->setDeliveryInfo(1, false, 'eventflow.events.ingest', 'user.created');

    $connections->shouldReceive('make')->once()->andReturn($connection);
    $connection->shouldReceive('channel')->once()->andReturn($channel);
    $channel->shouldReceive('basic_qos')->once()->with(0, 1, false);
    $channel->shouldReceive('basic_get')->once()->with('eventflow.ingest', false)->andReturn($message);
    $channel->shouldReceive('basic_ack')->once()->with(1, false);
    $channel->shouldReceive('close')->once();
    $connection->shouldReceive('close')->once();

    app()->instance(AmqpConnectionFactory::class, $connections);
    app()->instance(RabbitMqInboundEventMessageHandler::class, $handler);

    $this->artisan('events:consume-ingest --once')
        ->expectsOutputToContain('recebido via RabbitMQ com status queued')
        ->assertExitCode(ConsumeIngressEventsCommand::SUCCESS);
});

it('keeps waiting when the inbound rabbitmq consumer loop times out between messages', function (): void {
    config()->set('event_pipeline.rabbitmq.ingest.queue', 'eventflow.ingest');
    config()->set('event_pipeline.consumer.idle_timeout', 9);

    [$handler] = makeIngressCommandHandler();
    $connections = Mockery::mock(AmqpConnectionFactory::class);
    $connection = Mockery::mock(AMQPStreamConnection::class);
    $channel = Mockery::mock(AMQPChannel::class)->shouldIgnoreMissing();

    $connections->shouldReceive('make')->once()->andReturn($connection);
    $connection->shouldReceive('channel')->once()->andReturn($channel);
    $channel->shouldReceive('basic_qos')->once()->with(0, 1, false);
    $channel->shouldReceive('basic_consume')
        ->once()
        ->withArgs(function (...$arguments): bool {
            return $arguments[0] === 'eventflow.ingest'
                && $arguments[3] === false
                && is_callable($arguments[6] ?? null);
        });
    $channel->shouldReceive('is_consuming')->andReturn(true, false);
    $channel->shouldReceive('wait')->once()->with(null, false, 9)->andThrow(new AMQPTimeoutException('Idle timeout.'));
    $channel->shouldReceive('close')->once();
    $connection->shouldReceive('close')->once();

    app()->instance(AmqpConnectionFactory::class, $connections);
    app()->instance(RabbitMqInboundEventMessageHandler::class, $handler);

    $this->artisan('events:consume-ingest --idle-timeout=9')
        ->assertExitCode(ConsumeIngressEventsCommand::SUCCESS);
});

/**
 * @return array{0: RabbitMqInboundEventMessageHandler, 1: InMemoryEventRepository, 2: FakeEventPublisher}
 */
function makeIngressCommandHandler(): array
{
    $events = new InMemoryEventRepository;
    $publisher = new FakeEventPublisher;
    $historyRecorder = new EventHistoryRecorder(new InMemoryEventHistoryRepository);

    return [
        new RabbitMqInboundEventMessageHandler(
            new ReceiveEventAction($events, $publisher, $historyRecorder),
            new RabbitMqInboundEventPayloadFactory(new EventPayloadDataFactory),
        ),
        $events,
        $publisher,
    ];
}
