<?php

namespace App\Console\Commands;

use App\Application\Events\Actions\ProcessQueuedEventAction;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqTopologyManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

final class ConsumeEventsCommand extends Command
{
    protected $signature = 'events:consume
        {--once : Consome apenas uma mensagem e encerra}
        {--max-attempts= : Numero maximo de tentativas por evento}
        {--idle-timeout= : Timeout de espera entre leituras do RabbitMQ}';

    protected $description = 'Consome eventos do RabbitMQ e executa o processamento assincrono';

    public function handle(ProcessQueuedEventAction $action, RabbitMqTopologyManager $topology): int
    {
        $connection = new AMQPStreamConnection(
            host: (string) config('event_pipeline.rabbitmq.host'),
            port: (int) config('event_pipeline.rabbitmq.port'),
            user: (string) config('event_pipeline.rabbitmq.user'),
            password: (string) config('event_pipeline.rabbitmq.password'),
            vhost: (string) config('event_pipeline.rabbitmq.vhost'),
        );

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
                    $this->processMessage($message, $action, $maxAttempts);
                }

                return self::SUCCESS;
            }

            $channel->basic_consume(
                queue: $queue,
                callback: fn (AMQPMessage $message) => $this->processMessage($message, $action, $maxAttempts),
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

    private function processMessage(AMQPMessage $message, ProcessQueuedEventAction $action, int $maxAttempts): void
    {
        $traceId = $this->extractTraceId($message);

        if ($traceId !== null) {
            Log::withContext([
                'trace_id' => $traceId,
            ]);
        }

        $eventId = $this->extractEventId($message);

        try {
            if ($eventId === null) {
                $message->ack();

                return;
            }

            $result = $action->handle($eventId, $maxAttempts);

            if ($result->shouldRequeue) {
                $message->nack(false, true);

                return;
            }

            $message->ack();

            if ($result->event !== null) {
                $this->line(sprintf(
                    '[%s] Evento %s finalizado com status %s',
                    now()->toDateTimeString(),
                    $result->event->id,
                    $result->event->status->value,
                ));
            }
        } finally {
            if ($traceId !== null) {
                Log::withoutContext(['trace_id']);
            }
        }
    }

    private function extractEventId(AMQPMessage $message): ?string
    {
        $messageId = $message->get('message_id');

        if (is_string($messageId) && $messageId !== '') {
            return $messageId;
        }

        $payload = json_decode($message->getBody(), true);

        if (! is_array($payload)) {
            return null;
        }

        $eventId = $payload['id'] ?? null;

        return is_string($eventId) && $eventId !== '' ? $eventId : null;
    }

    private function extractTraceId(AMQPMessage $message): ?string
    {
        if ($message->has('application_headers')) {
            $headers = $message->get('application_headers');

            if ($headers instanceof AMQPTable) {
                $nativeHeaders = $headers->getNativeData();
                $traceId = $nativeHeaders['trace_id'] ?? null;

                if (is_string($traceId) && $traceId !== '') {
                    return $traceId;
                }
            }
        }

        $payload = json_decode($message->getBody(), true);

        if (! is_array($payload)) {
            return null;
        }

        $traceId = $payload['trace_id'] ?? null;

        return is_string($traceId) && $traceId !== '' ? $traceId : null;
    }
}
