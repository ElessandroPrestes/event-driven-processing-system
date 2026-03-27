<?php

namespace App\Infrastructure\Messaging\RabbitMq;

use App\Domain\Events\Contracts\EventPublisher;
use App\Domain\Events\DataTransferObjects\StoredEventData;

final class RabbitMqEventPublisher implements EventPublisher
{
    public function __construct(
        private readonly RabbitMqConnectionFactory $connections,
        private readonly RabbitMqEventMessageFactory $messages,
        private readonly RabbitMqTopologyManager $topology,
    ) {}

    public function publish(StoredEventData $event): void
    {
        $connection = $this->connections->make();
        $channel = $connection->channel();

        try {
            $this->topology->declare($channel);

            $channel->basic_publish(
                msg: $this->messages->make($event),
                exchange: (string) config('event_pipeline.rabbitmq.exchange'),
                routing_key: $event->eventName,
            );
        } finally {
            $channel->close();
            $connection->close();
        }
    }
}
