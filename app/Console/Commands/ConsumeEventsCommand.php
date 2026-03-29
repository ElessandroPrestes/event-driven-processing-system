<?php

namespace App\Console\Commands;

use App\Application\Health\Services\WorkerHeartbeatRecorder;
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
        WorkerHeartbeatRecorder $heartbeats,
    ): int {
        $connection = $connections->make();
        $channel = $connection->channel();
        $queue = (string) config('event_pipeline.rabbitmq.queue');
        $maxAttempts = (int) ($this->option('max-attempts') ?: config('event_pipeline.consumer.max_attempts'));
        $idleTimeout = (int) ($this->option('idle-timeout') ?: config('event_pipeline.consumer.idle_timeout'));
        $workerName = (string) config('event_pipeline.health.workers.processing_worker_name', 'worker');
        $heartbeatContext = [
            'command' => (string) $this->getName(),
            'queue' => $queue,
        ];

        try {
            $topology->declare($channel);
            $channel->basic_qos(0, 1, false);
            $heartbeats->record($workerName, $heartbeatContext);

            if ((bool) $this->option('once')) {
                $message = $channel->basic_get($queue, false);

                if ($message instanceof AMQPMessage) {
                    $this->processMessage($message, $handler, $maxAttempts, $heartbeats, $workerName, $heartbeatContext);
                }

                return self::SUCCESS;
            }

            $channel->basic_consume(
                queue: $queue,
                callback: fn (AMQPMessage $message) => $this->processMessage($message, $handler, $maxAttempts, $heartbeats, $workerName, $heartbeatContext),
                no_ack: false,
            );

            while ($channel->is_consuming()) {
                try {
                    $channel->wait(null, false, $idleTimeout);
                } catch (AMQPTimeoutException) {
                    $heartbeats->record($workerName, $heartbeatContext);

                    continue;
                }
            }

            return self::SUCCESS;
        } finally {
            $channel->close();
            $connection->close();
        }
    }

    /**
     * @param  array<string, mixed>  $heartbeatContext
     */
    private function processMessage(
        AMQPMessage $message,
        RabbitMqMessageHandler $handler,
        int $maxAttempts,
        WorkerHeartbeatRecorder $heartbeats,
        string $workerName,
        array $heartbeatContext,
    ): void {
        $heartbeats->record($workerName, $heartbeatContext);
        $result = $handler->handle($message, $maxAttempts);
        $heartbeats->record($workerName, $heartbeatContext);

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
