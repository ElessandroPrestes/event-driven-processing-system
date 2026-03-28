<?php

namespace App\Infrastructure\Messaging\RabbitMq;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Wire\AMQPTable;

final class RabbitMqTopologyManager
{
    public function declare(AMQPChannel $channel): void
    {
        $exchange = (string) config('event_pipeline.rabbitmq.exchange');
        $exchangeType = (string) config('event_pipeline.rabbitmq.exchange_type');
        $queue = (string) config('event_pipeline.rabbitmq.queue');
        $ingestExchange = (string) config('event_pipeline.rabbitmq.ingest.exchange');
        $ingestExchangeType = (string) config('event_pipeline.rabbitmq.ingest.exchange_type');
        $ingestQueue = (string) config('event_pipeline.rabbitmq.ingest.queue');
        $ingestBindingKey = (string) config('event_pipeline.rabbitmq.ingest.binding_key');
        $ingestDeadLetterExchange = (string) config('event_pipeline.rabbitmq.ingest.dead_letter_exchange');
        $ingestDeadLetterExchangeType = (string) config('event_pipeline.rabbitmq.ingest.dead_letter_exchange_type');
        $ingestDeadLetterQueue = (string) config('event_pipeline.rabbitmq.ingest.dead_letter_queue');
        $ingestDeadLetterRoutingKey = (string) config('event_pipeline.rabbitmq.ingest.dead_letter_routing_key');
        $retryExchange = (string) config('event_pipeline.rabbitmq.retry_exchange');
        $retryExchangeType = (string) config('event_pipeline.rabbitmq.retry_exchange_type');
        $retryQueue = (string) config('event_pipeline.rabbitmq.retry_queue');
        $retryRoutingKey = (string) config('event_pipeline.rabbitmq.retry_routing_key');
        $retryReturnRoutingKey = (string) config('event_pipeline.rabbitmq.retry_return_routing_key');
        $deadLetterExchange = (string) config('event_pipeline.rabbitmq.dead_letter_exchange');
        $deadLetterExchangeType = (string) config('event_pipeline.rabbitmq.dead_letter_exchange_type');
        $deadLetterQueue = (string) config('event_pipeline.rabbitmq.dead_letter_queue');
        $deadLetterRoutingKey = (string) config('event_pipeline.rabbitmq.dead_letter_routing_key');
        $durable = (bool) config('event_pipeline.rabbitmq.durable');

        $channel->exchange_declare($exchange, $exchangeType, false, $durable, false);
        $channel->exchange_declare($ingestExchange, $ingestExchangeType, false, $durable, false);
        $channel->exchange_declare($ingestDeadLetterExchange, $ingestDeadLetterExchangeType, false, $durable, false);
        $channel->exchange_declare($retryExchange, $retryExchangeType, false, $durable, false);
        $channel->exchange_declare($deadLetterExchange, $deadLetterExchangeType, false, $durable, false);
        $channel->queue_declare(
            $ingestQueue,
            false,
            $durable,
            false,
            false,
            false,
            new AMQPTable([
                'x-dead-letter-exchange' => $ingestDeadLetterExchange,
                'x-dead-letter-routing-key' => $ingestDeadLetterRoutingKey,
            ]),
        );
        $channel->queue_declare($ingestDeadLetterQueue, false, $durable, false, false);
        $channel->queue_declare(
            $queue,
            false,
            $durable,
            false,
            false,
            false,
            new AMQPTable([
                'x-dead-letter-exchange' => $deadLetterExchange,
                'x-dead-letter-routing-key' => $deadLetterRoutingKey,
            ]),
        );
        $channel->queue_declare(
            $retryQueue,
            false,
            $durable,
            false,
            false,
            false,
            new AMQPTable([
                'x-dead-letter-exchange' => $exchange,
                'x-dead-letter-routing-key' => $retryReturnRoutingKey,
            ]),
        );
        $channel->queue_declare($deadLetterQueue, false, $durable, false, false);
        $channel->queue_bind($ingestQueue, $ingestExchange, $ingestBindingKey);
        $channel->queue_bind($ingestDeadLetterQueue, $ingestDeadLetterExchange, $ingestDeadLetterRoutingKey);
        $channel->queue_bind($queue, $exchange, '#');
        $channel->queue_bind($retryQueue, $retryExchange, $retryRoutingKey);
        $channel->queue_bind($deadLetterQueue, $deadLetterExchange, $deadLetterRoutingKey);
    }
}
