<?php

namespace App\Infrastructure\Health;

use App\Application\Health\Contracts\HealthProbe;
use App\Application\Health\DataTransferObjects\ComponentHealthData;
use App\Infrastructure\Messaging\RabbitMq\Contracts\AmqpConnectionFactory;
use Carbon\CarbonImmutable;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Throwable;

final readonly class RabbitMqHealthProbe implements HealthProbe
{
    public function __construct(
        private AmqpConnectionFactory $connections,
    ) {}

    public function check(): ComponentHealthData
    {
        $observedAt = CarbonImmutable::now();
        $connection = null;
        $channel = null;

        try {
            $connection = $this->connections->make();
            $channel = $connection->channel();

            return new ComponentHealthData(
                name: 'rabbitmq',
                status: 'ok',
                observedAt: $observedAt,
            );
        } catch (Throwable) {
            return new ComponentHealthData(
                name: 'rabbitmq',
                status: 'degraded',
                observedAt: $observedAt,
                message: 'RabbitMQ connectivity check failed.',
            );
        } finally {
            $this->closeChannel($channel);
            $this->closeConnection($connection);
        }
    }

    private function closeChannel(?AMQPChannel $channel): void
    {
        if ($channel === null) {
            return;
        }

        try {
            $channel->close();
        } catch (Throwable) {
            // Ignore cleanup errors during health probing.
        }
    }

    private function closeConnection(?AMQPStreamConnection $connection): void
    {
        if ($connection === null) {
            return;
        }

        try {
            $connection->close();
        } catch (Throwable) {
            // Ignore cleanup errors during health probing.
        }
    }
}
