<?php

use App\Application\Events\Actions\ProcessQueuedEventAction;
use App\Application\Events\Processors\UserCreatedProcessor;
use App\Application\Events\Services\EventHistoryRecorder;
use App\Application\Events\Services\EventProcessorRegistry;
use App\Application\Events\Services\EventRetryDelayCalculator;
use App\Console\Commands\ConsumeEventsCommand;
use App\Domain\Events\DataTransferObjects\EventPayloadData;
use App\Domain\Events\DataTransferObjects\StoredEventData;
use App\Infrastructure\Messaging\RabbitMq\Contracts\AmqpConnectionFactory;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqMessageHandler;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Tests\Fakes\FakeEventRetryScheduler;
use Tests\Fakes\InMemoryEventHistoryRepository;
use Tests\Fakes\InMemoryEventRepository;

afterEach(function (): void {
    Mockery::close();

    if (isset($this->heartbeatDir) && is_string($this->heartbeatDir)) {
        File::deleteDirectory($this->heartbeatDir);
    }
});

it('consumes a single message when running in once mode', function (): void {
    config()->set('event_pipeline.rabbitmq.queue', 'eventflow.processing');
    config()->set('event_pipeline.consumer.max_attempts', 3);
    config()->set('event_pipeline.consumer.idle_timeout', 5);
    config()->set('event_pipeline.health.workers.processing_worker_name', 'worker');
    $this->heartbeatDir = storage_path('framework/testing/heartbeats/consume-events-once-'.uniqid('', true));
    config()->set('event_pipeline.health.workers.heartbeat_dir', $this->heartbeatDir);

    [$handler, $events] = makeCommandHandler();
    $event = queueCommandEvent($events, 'user.created', [
        'user_id' => 'worker-command-001',
    ]);
    $connections = Mockery::mock(AmqpConnectionFactory::class);
    $connection = Mockery::mock(AMQPStreamConnection::class);
    $channel = Mockery::mock(AMQPChannel::class)->shouldIgnoreMissing();
    $message = makeCommandMessage($event, $channel);

    $connections->shouldReceive('make')->once()->andReturn($connection);
    $connection->shouldReceive('channel')->once()->andReturn($channel);
    $channel->shouldReceive('basic_qos')->once()->with(0, 1, false);
    $channel->shouldReceive('basic_get')->once()->with('eventflow.processing', false)->andReturn($message);
    $channel->shouldReceive('basic_ack')->once()->with(1, false);
    $channel->shouldReceive('close')->once();
    $connection->shouldReceive('close')->once();

    app()->instance(AmqpConnectionFactory::class, $connections);
    app()->instance(RabbitMqMessageHandler::class, $handler);

    $this->artisan('events:consume --once')
        ->expectsOutputToContain(sprintf('Evento %s finalizado com status processed', $event->id))
        ->assertExitCode(ConsumeEventsCommand::SUCCESS);

    expect($this->heartbeatDir.'/worker.json')->toBeFile();
});

it('keeps waiting when the consumer loop times out between messages', function (): void {
    config()->set('event_pipeline.rabbitmq.queue', 'eventflow.processing');
    config()->set('event_pipeline.consumer.max_attempts', 7);
    config()->set('event_pipeline.consumer.idle_timeout', 9);
    config()->set('event_pipeline.health.workers.processing_worker_name', 'worker');
    $this->heartbeatDir = storage_path('framework/testing/heartbeats/consume-events-timeout-'.uniqid('', true));
    config()->set('event_pipeline.health.workers.heartbeat_dir', $this->heartbeatDir);

    [$handler] = makeCommandHandler();
    $connections = Mockery::mock(AmqpConnectionFactory::class);
    $connection = Mockery::mock(AMQPStreamConnection::class);
    $channel = Mockery::mock(AMQPChannel::class)->shouldIgnoreMissing();

    $connections->shouldReceive('make')->once()->andReturn($connection);
    $connection->shouldReceive('channel')->once()->andReturn($channel);
    $channel->shouldReceive('basic_qos')->once()->with(0, 1, false);
    $channel->shouldReceive('basic_consume')
        ->once()
        ->withArgs(function (...$arguments): bool {
            return $arguments[0] === 'eventflow.processing'
                && $arguments[3] === false
                && is_callable($arguments[6] ?? null);
        });
    $channel->shouldReceive('is_consuming')->andReturn(true, false);
    $channel->shouldReceive('wait')->once()->with(null, false, 9)->andThrow(new AMQPTimeoutException('Idle timeout.'));
    $channel->shouldReceive('close')->once();
    $connection->shouldReceive('close')->once();

    app()->instance(AmqpConnectionFactory::class, $connections);
    app()->instance(RabbitMqMessageHandler::class, $handler);

    $this->artisan('events:consume --idle-timeout=9')
        ->assertExitCode(ConsumeEventsCommand::SUCCESS);

    expect($this->heartbeatDir.'/worker.json')->toBeFile();
});

/**
 * @return array{0: RabbitMqMessageHandler, 1: InMemoryEventRepository}
 */
function makeCommandHandler(): array
{
    $events = new InMemoryEventRepository;
    $history = new InMemoryEventHistoryRepository;
    $scheduler = new FakeEventRetryScheduler;
    $historyRecorder = new EventHistoryRecorder($history);

    config()->set('event_pipeline.consumer.retry_base_delay_ms', 5000);
    config()->set('event_pipeline.consumer.retry_multiplier', 2.0);
    config()->set('event_pipeline.consumer.retry_max_delay_ms', 60000);

    return [
        new RabbitMqMessageHandler(
            new ProcessQueuedEventAction(
                $events,
                new EventProcessorRegistry([new UserCreatedProcessor]),
                $historyRecorder,
            ),
            $scheduler,
            new EventRetryDelayCalculator,
            $historyRecorder,
        ),
        $events,
    ];
}

function queueCommandEvent(InMemoryEventRepository $events, string $eventName, array $payload): StoredEventData
{
    $event = $events->create(new EventPayloadData(
        eventName: $eventName,
        payload: $payload,
        metadata: null,
        idempotencyKey: sprintf('idempotency-%s', uniqid('', true)),
        occurredAt: CarbonImmutable::now(),
        traceId: sprintf('trace-%s', uniqid('', true)),
    ));

    return $events->markAsQueued($event->id, CarbonImmutable::now());
}

function makeCommandMessage(StoredEventData $event, AMQPChannel $channel): AMQPMessage
{
    $message = new AMQPMessage(json_encode([
        'id' => $event->id,
        'trace_id' => $event->traceId,
    ], JSON_THROW_ON_ERROR), [
        'application_headers' => new AMQPTable([
            'trace_id' => $event->traceId,
        ]),
        'message_id' => $event->id,
    ]);

    $message->setChannel($channel);
    $message->setDeliveryInfo(1, false, 'eventflow.events', $event->eventName);

    return $message;
}
