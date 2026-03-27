<?php

namespace App\Infrastructure\Messaging\RabbitMq;

use App\Domain\Events\Contracts\EventPublisher;
use App\Domain\Events\DataTransferObjects\StoredEventData;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

final class RabbitMqEventPublisher implements EventPublisher
{
    public function __construct(
        private readonly RabbitMqTopologyManager $topology,
    ) {}

    public function publish(StoredEventData $event): void
    {
        $connection = new AMQPStreamConnection(
            host: (string) config('event_pipeline.rabbitmq.host'),
            port: (int) config('event_pipeline.rabbitmq.port'),
            user: (string) config('event_pipeline.rabbitmq.user'),
            password: (string) config('event_pipeline.rabbitmq.password'),
            vhost: (string) config('event_pipeline.rabbitmq.vhost'),
        );

        $channel = $connection->channel();

        try {
            $this->topology->declare($channel);

            $message = new AMQPMessage(
                json_encode([
                    'id' => $event->id,
                    'event_name' => $event->eventName,
                    'payload' => $event->payload,
                    'metadata' => $event->metadata,
                    'status' => $event->status->value,
                    'occurred_at' => $event->occurredAt?->toIso8601String(),
                    'queued_at' => $event->queuedAt?->toIso8601String(),
                    'created_at' => $event->createdAt->toIso8601String(),
                ], JSON_THROW_ON_ERROR),
                [
                    'content_type' => 'application/json',
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                    'message_id' => $event->id,
                    'type' => $event->eventName,
                    'timestamp' => time(),
                    'application_headers' => new AMQPTable([
                        'idempotency_key' => $event->idempotencyKey,
                    ]),
                ],
            );

            $channel->basic_publish(
                msg: $message,
                exchange: (string) config('event_pipeline.rabbitmq.exchange'),
                routing_key: $event->eventName,
            );
        } finally {
            $channel->close();
            $connection->close();
        }
    }
}
