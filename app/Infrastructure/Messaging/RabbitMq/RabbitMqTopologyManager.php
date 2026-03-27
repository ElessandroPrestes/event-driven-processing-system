<?php

namespace App\Infrastructure\Messaging\RabbitMq;

use PhpAmqpLib\Channel\AMQPChannel;

final class RabbitMqTopologyManager
{
    public function declare(AMQPChannel $channel): void
    {
        $exchange = (string) config('event_pipeline.rabbitmq.exchange');
        $exchangeType = (string) config('event_pipeline.rabbitmq.exchange_type');
        $queue = (string) config('event_pipeline.rabbitmq.queue');
        $durable = (bool) config('event_pipeline.rabbitmq.durable');

        $channel->exchange_declare($exchange, $exchangeType, false, $durable, false);
        $channel->queue_declare($queue, false, $durable, false, false);
        $channel->queue_bind($queue, $exchange, '#');
    }
}
