<?php

use App\Infrastructure\Messaging\RabbitMq\RabbitMqTopologyManager;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Wire\AMQPTable;

afterEach(function (): void {
    Mockery::close();
});

it('declares the processing topology with retry and dead-letter queues', function (): void {
    config()->set('event_pipeline.rabbitmq.ingest.binding_key', '');

    $channel = Mockery::mock(AMQPChannel::class);

    $channel->shouldReceive('exchange_declare')
        ->once()
        ->with('eventflow.events', 'topic', false, true, false);

    $channel->shouldReceive('exchange_declare')
        ->once()
        ->with('eventflow.events.ingest', 'topic', false, true, false);

    $channel->shouldReceive('exchange_declare')
        ->once()
        ->with('eventflow.events.ingest.dlx', 'direct', false, true, false);

    $channel->shouldReceive('exchange_declare')
        ->once()
        ->with('eventflow.events.retry', 'direct', false, true, false);

    $channel->shouldReceive('exchange_declare')
        ->once()
        ->with('eventflow.events.dlx', 'direct', false, true, false);

    $channel->shouldReceive('queue_declare')
        ->once()
        ->with(
            'eventflow.ingest',
            false,
            true,
            false,
            false,
            false,
            Mockery::on(function (mixed $arguments): bool {
                return $arguments instanceof AMQPTable
                    && $arguments->getNativeData() === [
                        'x-dead-letter-exchange' => 'eventflow.events.ingest.dlx',
                        'x-dead-letter-routing-key' => 'eventflow.ingest.dead',
                    ];
            }),
        );

    $channel->shouldReceive('queue_declare')
        ->once()
        ->with('eventflow.ingest.dead', false, true, false, false);

    $channel->shouldReceive('queue_declare')
        ->once()
        ->with(
            'eventflow.processing',
            false,
            true,
            false,
            false,
            false,
            Mockery::on(function (mixed $arguments): bool {
                return $arguments instanceof AMQPTable
                    && $arguments->getNativeData() === [
                        'x-dead-letter-exchange' => 'eventflow.events.dlx',
                        'x-dead-letter-routing-key' => 'eventflow.processing.dead',
                    ];
            }),
        );

    $channel->shouldReceive('queue_declare')
        ->once()
        ->with(
            'eventflow.processing.retry',
            false,
            true,
            false,
            false,
            false,
            Mockery::on(function (mixed $arguments): bool {
                return $arguments instanceof AMQPTable
                    && $arguments->getNativeData() === [
                        'x-dead-letter-exchange' => 'eventflow.events',
                        'x-dead-letter-routing-key' => 'eventflow.processing.ready',
                    ];
            }),
        );

    $channel->shouldReceive('queue_declare')
        ->once()
        ->with('eventflow.processing.dead', false, true, false, false);

    $channel->shouldReceive('queue_bind')
        ->once()
        ->with('eventflow.ingest', 'eventflow.events.ingest', '#');

    $channel->shouldReceive('queue_bind')
        ->once()
        ->with('eventflow.ingest.dead', 'eventflow.events.ingest.dlx', 'eventflow.ingest.dead');

    $channel->shouldReceive('queue_bind')
        ->once()
        ->with('eventflow.processing', 'eventflow.events', '#');

    $channel->shouldReceive('queue_bind')
        ->once()
        ->with('eventflow.processing.retry', 'eventflow.events.retry', 'eventflow.processing.retry');

    $channel->shouldReceive('queue_bind')
        ->once()
        ->with('eventflow.processing.dead', 'eventflow.events.dlx', 'eventflow.processing.dead');

    (new RabbitMqTopologyManager)->declare($channel);
});
