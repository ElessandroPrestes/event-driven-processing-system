<?php

namespace App\Infrastructure\Messaging\RabbitMq;

use App\Application\Events\Contracts\EventRetryScheduler;
use App\Domain\Events\DataTransferObjects\StoredEventData;

final class RabbitMqDelayedRetryScheduler implements EventRetryScheduler
{
    public function __construct(
        private readonly RabbitMqConnectionFactory $connections,
        private readonly RabbitMqEventMessageFactory $messages,
        private readonly RabbitMqTopologyManager $topology,
    ) {}

    public function schedule(StoredEventData $event, int $delayInMilliseconds): void
    {
        $connection = $this->connections->make();
        $channel = $connection->channel();

        try {
            $this->topology->declare($channel);

            $channel->basic_publish(
                msg: $this->messages->make(
                    $event,
                    properties: [
                        'expiration' => (string) $delayInMilliseconds,
                    ],
                    applicationHeaders: [
                        'retry_delay_ms' => $delayInMilliseconds,
                        'processing_attempts' => $event->processingAttempts,
                    ],
                ),
                exchange: (string) config('event_pipeline.rabbitmq.retry_exchange'),
                routing_key: (string) config('event_pipeline.rabbitmq.retry_routing_key'),
            );
        } finally {
            $channel->close();
            $connection->close();
        }
    }
}
