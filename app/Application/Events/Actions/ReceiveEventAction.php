<?php

namespace App\Application\Events\Actions;

use App\Application\Events\DataTransferObjects\ReceiveEventResult;
use App\Application\Events\Exceptions\EventPublicationException;
use App\Application\Events\Exceptions\IdempotencyConflictException;
use App\Domain\Events\Contracts\EventPublisher;
use App\Domain\Events\Contracts\EventRepository;
use App\Domain\Events\DataTransferObjects\EventPayloadData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ReceiveEventAction
{
    public function __construct(
        private readonly EventRepository $events,
        private readonly EventPublisher $publisher,
    ) {}

    public function handle(EventPayloadData $payload): ReceiveEventResult
    {
        $existingEvent = $this->events->findByIdempotencyKey($payload->idempotencyKey);

        if ($existingEvent !== null) {
            if ($existingEvent->contentHash !== $payload->contentHash()) {
                throw new IdempotencyConflictException;
            }

            return ReceiveEventResult::duplicate($existingEvent);
        }

        $event = $this->events->create($payload);

        Log::info('event.received', [
            'event_id' => $event->id,
            'event_name' => $event->eventName,
            'status' => $event->status->value,
            'idempotency_key' => $event->idempotencyKey,
        ]);

        try {
            $this->publisher->publish($event);
        } catch (Throwable $exception) {
            $failedEvent = $this->events->markAsPublishFailed($event->id, $exception->getMessage());

            Log::error('event.publish_failed', [
                'event_id' => $failedEvent->id,
                'event_name' => $failedEvent->eventName,
                'status' => $failedEvent->status->value,
                'error' => $exception->getMessage(),
            ]);

            throw new EventPublicationException($failedEvent, $exception);
        }

        $queuedEvent = $this->events->markAsQueued($event->id, CarbonImmutable::now());

        Log::info('event.enqueued', [
            'event_id' => $queuedEvent->id,
            'event_name' => $queuedEvent->eventName,
            'status' => $queuedEvent->status->value,
            'queued_at' => $queuedEvent->queuedAt?->toIso8601String(),
        ]);

        return ReceiveEventResult::queued($queuedEvent);
    }
}
