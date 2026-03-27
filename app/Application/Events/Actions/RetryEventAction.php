<?php

namespace App\Application\Events\Actions;

use App\Application\Events\Exceptions\EventRetryDispatchException;
use App\Application\Events\Exceptions\EventRetryNotAllowedException;
use App\Domain\Events\Contracts\EventPublisher;
use App\Domain\Events\Contracts\EventRepository;
use App\Domain\Events\DataTransferObjects\StoredEventData;
use App\Domain\Events\Enums\EventStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class RetryEventAction
{
    public function __construct(
        private readonly EventRepository $events,
        private readonly EventPublisher $publisher,
    ) {}

    public function handle(string $eventId): StoredEventData
    {
        $event = $this->events->findById($eventId);

        if ($event === null) {
            throw new NotFoundHttpException('Evento nao encontrado.');
        }

        if (! in_array($event->status, [EventStatus::PUBLISH_FAILED, EventStatus::PROCESSING_FAILED], true)) {
            throw new EventRetryNotAllowedException($event);
        }

        Log::info('event.retry_requested', [
            'event_id' => $event->id,
            'event_name' => $event->eventName,
            'status' => $event->status->value,
            'processing_attempts' => $event->processingAttempts,
        ]);

        try {
            $this->publisher->publish($event);
        } catch (Throwable $exception) {
            $failedEvent = $this->markRetryFailure($event, $exception->getMessage());

            Log::error('event.retry_enqueue_failed', [
                'event_id' => $failedEvent->id,
                'event_name' => $failedEvent->eventName,
                'status' => $failedEvent->status->value,
                'error' => $exception->getMessage(),
            ]);

            throw new EventRetryDispatchException($failedEvent, $exception);
        }

        $queuedEvent = $this->events->markAsQueued($event->id, CarbonImmutable::now());

        Log::info('event.retry_enqueued', [
            'event_id' => $queuedEvent->id,
            'event_name' => $queuedEvent->eventName,
            'previous_status' => $event->status->value,
            'status' => $queuedEvent->status->value,
            'queued_at' => $queuedEvent->queuedAt?->toIso8601String(),
        ]);

        return $queuedEvent;
    }

    private function markRetryFailure(StoredEventData $event, string $errorMessage): StoredEventData
    {
        $failureReason = sprintf('Falha ao reenfileirar manualmente: %s', $errorMessage);

        return match ($event->status) {
            EventStatus::PUBLISH_FAILED => $this->events->markAsPublishFailed($event->id, $failureReason),
            EventStatus::PROCESSING_FAILED => $this->events->markAsProcessingFailed($event->id, $failureReason),
            default => $event,
        };
    }
}
