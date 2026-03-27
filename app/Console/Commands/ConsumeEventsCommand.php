<?php

namespace App\Console\Commands;

use App\Infrastructure\Messaging\RabbitMq\Contracts\AmqpConnectionFactory;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqMessageHandler;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqTopologyManager;
use Illuminate\Console\Command;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;

final class ConsumeEventsCommand extends Command
{
    protected $signature = 'events:consume
        {--once : Consome apenas uma mensagem e encerra}
        {--max-attempts= : Numero maximo de tentativas por evento}
        {--idle-timeout= : Timeout de espera entre leituras do RabbitMQ}';

    protected $description = 'Consome eventos do RabbitMQ e executa o processamento assincrono';

    public function handle(
        RabbitMqMessageHandler $handler,
        AmqpConnectionFactory $connections,
        RabbitMqTopologyManager $topology,
    ): int {
        $connection = $connections->make();
        $channel = $connection->channel();
        $queue = (string) config('event_pipeline.rabbitmq.queue');
        $maxAttempts = (int) ($this->option('max-attempts') ?: config('event_pipeline.consumer.max_attempts'));
        $idleTimeout = (int) ($this->option('idle-timeout') ?: config('event_pipeline.consumer.idle_timeout'));

        try {
            $topology->declare($channel);
            $channel->basic_qos(0, 1, false);

            if ((bool) $this->option('once')) {
                $message = $channel->basic_get($queue, false);

                if ($message instanceof AMQPMessage) {
                    $this->processMessage($message, $handler, $maxAttempts);
                }

                return self::SUCCESS;
            }

            $channel->basic_consume(
                queue: $queue,
                callback: fn (AMQPMessage $message) => $this->processMessage($message, $handler, $maxAttempts),
                no_ack: false,
            );

            while ($channel->is_consuming()) {
                try {
                    $channel->wait(null, false, $idleTimeout);
                } catch (AMQPTimeoutException) {
                    continue;
                }
            }

            return self::SUCCESS;
        } finally {
            $channel->close();
            $connection->close();
        }
    }

    private function processMessage(AMQPMessage $message, RabbitMqMessageHandler $handler, int $maxAttempts): void
    {
        $result = $handler->handle($message, $maxAttempts);

        if ($result->event !== null) {
            $this->line(sprintf(
                '[%s] Evento %s finalizado com status %s',
                now()->toDateTimeString(),
                $result->event->id,
                $result->event->status->value,
            ));
        }
    }
}
