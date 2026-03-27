<?php

namespace App\Infrastructure\Messaging\RabbitMq;

use App\Domain\Events\DataTransferObjects\StoredEventData;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

final class RabbitMqEventMessageFactory
{
    /**
     * @param  array<string, mixed>  $properties
     * @param  array<string, mixed>  $applicationHeaders
     */
    public function make(StoredEventData $event, array $properties = [], array $applicationHeaders = []): AMQPMessage
    {
        $properties = array_merge([
            'content_type' => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'message_id' => $event->id,
            'type' => $event->eventName,
            'timestamp' => time(),
        ], $properties);
        $properties['application_headers'] = new AMQPTable(array_merge([
            'idempotency_key' => $event->idempotencyKey,
            'trace_id' => $event->traceId,
        ], $applicationHeaders));

        return new AMQPMessage(
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
            $properties,
        );
    }
}
