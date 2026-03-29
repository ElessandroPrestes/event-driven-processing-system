<?php

namespace App\Infrastructure\Messaging\RabbitMq;

use App\Infrastructure\Messaging\RabbitMq\Contracts\AmqpConnectionFactory;
use PhpAmqpLib\Connection\AMQPStreamConnection;

final class RabbitMqConnectionFactory implements AmqpConnectionFactory
{
    public function make(): AMQPStreamConnection
    {
        return new AMQPStreamConnection(
            host: (string) config('event_pipeline.rabbitmq.host'),
            port: (int) config('event_pipeline.rabbitmq.port'),
            user: (string) config('event_pipeline.rabbitmq.user'),
            password: (string) config('event_pipeline.rabbitmq.password'),
            vhost: (string) config('event_pipeline.rabbitmq.vhost'),
            connection_timeout: (float) config('event_pipeline.rabbitmq.connection_timeout', 3.0),
            read_write_timeout: (float) config('event_pipeline.rabbitmq.read_write_timeout', 3.0),
            heartbeat: (int) config('event_pipeline.rabbitmq.heartbeat', 0),
            channel_rpc_timeout: (float) config('event_pipeline.rabbitmq.channel_rpc_timeout', 0.0),
        );
    }
}
