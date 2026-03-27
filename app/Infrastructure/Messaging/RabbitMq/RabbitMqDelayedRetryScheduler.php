<?php

namespace App\Infrastructure\Messaging\RabbitMq;

use App\Application\Events\Contracts\EventRetryScheduler;
use App\Domain\Events\DataTransferObjects\StoredEventData;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

final class RabbitMqDelayedRetryScheduler implements EventRetryScheduler
{
    public function __construct(
        private readonly RabbitMqTopologyManager $topology,
    ) {}

    public function schedule(StoredEventData $event, int $delayInMilliseconds): void
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

            $channel->basic_publish(
                msg: new AMQPMessage(
                    json_encode([
                        'id' => $event->id,
                        'trace_id' => $event->traceId,
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
                        'expiration' => (string) $delayInMilliseconds,
                        'application_headers' => new AMQPTable([
                            'idempotency_key' => $event->idempotencyKey,
                            'trace_id' => $event->traceId,
                            'retry_delay_ms' => $delayInMilliseconds,
                            'processing_attempts' => $event->processingAttempts,
                        ]),
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
