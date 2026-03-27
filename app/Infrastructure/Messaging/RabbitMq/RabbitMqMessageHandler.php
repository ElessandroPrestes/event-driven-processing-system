<?php

namespace App\Infrastructure\Messaging\RabbitMq;

use App\Application\Events\Actions\ProcessQueuedEventAction;
use App\Application\Events\Contracts\EventRetryScheduler;
use App\Application\Events\DataTransferObjects\ProcessEventResult;
use App\Application\Events\Services\EventHistoryRecorder;
use App\Application\Events\Services\EventRetryDelayCalculator;
use App\Domain\Events\Enums\EventStatus;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Throwable;

final class RabbitMqMessageHandler
{
    private const string SKIP_REASON_MISSING_EVENT_ID = 'missing_event_id';

    private const string DEAD_LETTER_REASON_PROCESSING_FAILED_MAX_ATTEMPTS = 'processing_failed_max_attempts';

    public function __construct(
        private readonly ProcessQueuedEventAction $processEvent,
        private readonly EventRetryScheduler $retryScheduler,
        private readonly EventRetryDelayCalculator $retryDelay,
        private readonly EventHistoryRecorder $history,
    ) {}

    public function handle(AMQPMessage $message, int $maxAttempts): ProcessEventResult
    {
        $traceId = $this->extractTraceId($message);

        if ($traceId !== null) {
            Log::withContext([
                'trace_id' => $traceId,
            ]);
        }

        try {
            $eventId = $this->extractEventId($message);

            if ($eventId === null) {
                $this->deadLetter($message, self::SKIP_REASON_MISSING_EVENT_ID);

                return ProcessEventResult::skipped(self::SKIP_REASON_MISSING_EVENT_ID);
            }

            $result = $this->processEvent->handle($eventId, $maxAttempts);

            if ($result->shouldRetry) {
                $this->scheduleRetry($message, $result);

                return $result;
            }

            if ($this->shouldDeadLetterSkippedResult($result)) {
                $this->deadLetter($message, ProcessEventResult::SKIP_REASON_NOT_FOUND, $eventId);

                return $result;
            }

            if ($this->shouldDeadLetterFailedResult($result)) {
                $this->recordDeadLetterHistory($result);
                $this->deadLetter($message, self::DEAD_LETTER_REASON_PROCESSING_FAILED_MAX_ATTEMPTS, $result->event?->id);

                return $result;
            }

            $message->ack();

            return $result;
        } finally {
            if ($traceId !== null) {
                Log::withoutContext(['trace_id']);
            }
        }
    }

    private function scheduleRetry(AMQPMessage $message, ProcessEventResult $result): void
    {
        if ($result->event === null) {
            $message->nack(true);

            return;
        }

        $delayInMilliseconds = $this->retryDelay->forAttempt($result->event->processingAttempts);

        try {
            $this->retryScheduler->schedule($result->event, $delayInMilliseconds);

            $this->history->record(
                event: $result->event,
                action: 'retry_scheduled',
                source: 'worker',
                fromStatus: $result->event->status,
                context: [
                    'delay_ms' => $delayInMilliseconds,
                    'processing_attempts' => $result->event->processingAttempts,
                ],
            );

            Log::info('event.retry_scheduled', [
                'event_id' => $result->event->id,
                'event_name' => $result->event->eventName,
                'status' => $result->event->status->value,
                'processing_attempts' => $result->event->processingAttempts,
                'delay_ms' => $delayInMilliseconds,
            ]);

            $message->ack();
        } catch (Throwable $exception) {
            Log::error('event.retry_schedule_failed', [
                'event_id' => $result->event->id,
                'event_name' => $result->event->eventName,
                'status' => $result->event->status->value,
                'processing_attempts' => $result->event->processingAttempts,
                'delay_ms' => $delayInMilliseconds,
                'error' => $exception->getMessage(),
            ]);

            $message->nack(true);
        }
    }

    private function shouldDeadLetterSkippedResult(ProcessEventResult $result): bool
    {
        return $result->skipped && $result->skipReason === ProcessEventResult::SKIP_REASON_NOT_FOUND;
    }

    private function shouldDeadLetterFailedResult(ProcessEventResult $result): bool
    {
        return $result->event?->status === EventStatus::PROCESSING_FAILED;
    }

    private function recordDeadLetterHistory(ProcessEventResult $result): void
    {
        if ($result->event === null) {
            return;
        }

        $this->history->record(
            event: $result->event,
            action: 'dead_lettered',
            source: 'worker',
            fromStatus: $result->event->status,
            context: [
                'reason' => self::DEAD_LETTER_REASON_PROCESSING_FAILED_MAX_ATTEMPTS,
                'processing_attempts' => $result->event->processingAttempts,
                'failure_reason' => $result->event->failureReason,
            ],
        );
    }

    private function deadLetter(AMQPMessage $message, string $reason, ?string $eventId = null): void
    {
        Log::warning('event.dead_lettered', [
            'event_id' => $eventId,
            'reason' => $reason,
            'exchange' => $message->getExchange(),
            'routing_key' => $message->getRoutingKey(),
        ]);

        $message->nack();
    }

    private function extractEventId(AMQPMessage $message): ?string
    {
        if ($message->has('message_id')) {
            $messageId = $message->get('message_id');

            if (is_string($messageId) && $messageId !== '') {
                return $messageId;
            }
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
