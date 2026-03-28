<?php

namespace App\Infrastructure\Messaging\RabbitMq;

use App\Application\Events\Actions\ReceiveEventAction;
use App\Application\Events\Exceptions\EventPublicationException;
use App\Application\Events\Exceptions\IdempotencyConflictException;
use App\Domain\Events\DataTransferObjects\StoredEventData;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

final class RabbitMqInboundEventMessageHandler
{
    public function __construct(
        private readonly ReceiveEventAction $receiveEvent,
        private readonly RabbitMqInboundEventPayloadFactory $payloads,
    ) {}

    public function handle(AMQPMessage $message): ?StoredEventData
    {
        $traceId = $this->payloads->traceIdFromMessage($message);

        if ($traceId !== null) {
            Log::withContext([
                'trace_id' => $traceId,
            ]);
        }

        try {
            $payload = $this->payloads->fromMessage($message);

            if ($traceId === null) {
                $traceId = $payload->traceId;

                Log::withContext([
                    'trace_id' => $traceId,
                ]);
            }

            try {
                $result = $this->receiveEvent->handle($payload, 'amqp');

                Log::info('event.ingested_from_rabbitmq', [
                    'event_id' => $result->event->id,
                    'event_name' => $result->event->eventName,
                    'status' => $result->event->status->value,
                    'duplicate' => $result->duplicate,
                    'exchange' => $message->getExchange(),
                    'routing_key' => $message->getRoutingKey(),
                ]);

                $message->ack();

                return $result->event;
            } catch (IdempotencyConflictException $exception) {
                Log::warning('event.rabbitmq_ingest_conflict', [
                    'event_name' => $payload->eventName,
                    'idempotency_key' => $payload->idempotencyKey,
                    'exchange' => $message->getExchange(),
                    'routing_key' => $message->getRoutingKey(),
                    'error' => $exception->getMessage(),
                ]);

                $message->nack();

                return null;
            } catch (EventPublicationException $exception) {
                $failedEvent = $exception->event();

                Log::error('event.rabbitmq_ingest_publish_failed', [
                    'event_id' => $failedEvent->id,
                    'event_name' => $failedEvent->eventName,
                    'status' => $failedEvent->status->value,
                    'failure_reason' => $failedEvent->failureReason,
                    'exchange' => $message->getExchange(),
                    'routing_key' => $message->getRoutingKey(),
                ]);

                $message->ack();

                return $failedEvent;
            }
        } catch (ValidationException $exception) {
            Log::warning('event.rabbitmq_ingest_validation_failed', [
                'exchange' => $message->getExchange(),
                'routing_key' => $message->getRoutingKey(),
                'errors' => $exception->errors(),
            ]);

            $message->nack();

            return null;
        } catch (Throwable $exception) {
            Log::warning('event.rabbitmq_ingest_rejected', [
                'exchange' => $message->getExchange(),
                'routing_key' => $message->getRoutingKey(),
                'error' => $exception->getMessage(),
            ]);

            $message->nack();

            return null;
        } finally {
            if ($traceId !== null) {
                Log::withoutContext(['trace_id']);
            }
        }
    }
}
