<?php

namespace App\Infrastructure\Messaging\RabbitMq\Contracts;

use PhpAmqpLib\Connection\AMQPStreamConnection;

interface AmqpConnectionFactory
{
    public function make(): AMQPStreamConnection;
}
