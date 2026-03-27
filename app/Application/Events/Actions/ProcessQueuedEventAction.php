<?php

namespace App\Application\Events\Actions;

use App\Application\Events\DataTransferObjects\ProcessEventResult;
use App\Application\Events\Services\EventProcessorRegistry;
use App\Domain\Events\Contracts\EventRepository;
use App\Domain\Events\Enums\EventStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProcessQueuedEventAction
{
    public function __construct(
        private readonly EventRepository $events,
        private readonly EventProcessorRegistry $processors,
    ) {}

    public function handle(string $eventId, int $maxAttempts): ProcessEventResult
    {
        $event = $this->events->findById($eventId);

        if ($event === null) {
            Log::warning('event.processing_skipped', [
                'event_id' => $eventId,
                'reason' => 'not_found',
            ]);

            return ProcessEventResult::skipped();
        }

        if (in_array($event->status, [EventStatus::PROCESSED, EventStatus::PROCESSING_FAILED], true)) {
            Log::info('event.processing_skipped', [
                'event_id' => $event->id,
                'event_name' => $event->eventName,
                'status' => $event->status->value,
            ]);

            return ProcessEventResult::skipped();
        }

        $processingEvent = $this->events->markAsProcessing($event->id, CarbonImmutable::now());

        Log::info('event.consumed', [
            'event_id' => $processingEvent->id,
            'event_name' => $processingEvent->eventName,
            'status' => $processingEvent->status->value,
            'processing_attempts' => $processingEvent->processingAttempts,
        ]);

        try {
            $processingResult = $this->processors
                ->for($processingEvent->eventName)
                ->process($processingEvent);

            $processedEvent = $this->events->markAsProcessed(
                $processingEvent->id,
                CarbonImmutable::now(),
                $processingResult,
            );

            Log::info('event.processed', [
                'event_id' => $processedEvent->id,
                'event_name' => $processedEvent->eventName,
                'status' => $processedEvent->status->value,
                'processed_at' => $processedEvent->processedAt?->toIso8601String(),
            ]);

            return ProcessEventResult::processed($processedEvent);
        } catch (Throwable $exception) {
            if ($processingEvent->processingAttempts < $maxAttempts) {
                $requeuedEvent = $this->events->markAsQueued(
                    $processingEvent->id,
                    CarbonImmutable::now(),
                    $exception->getMessage(),
                );

                Log::warning('event.requeued', [
                    'event_id' => $requeuedEvent->id,
                    'event_name' => $requeuedEvent->eventName,
                    'status' => $requeuedEvent->status->value,
                    'processing_attempts' => $processingEvent->processingAttempts,
                    'error' => $exception->getMessage(),
                ]);

                return ProcessEventResult::retry($requeuedEvent);
            }

            $failedEvent = $this->events->markAsProcessingFailed($processingEvent->id, $exception->getMessage());

            Log::error('event.processing_failed', [
                'event_id' => $failedEvent->id,
                'event_name' => $failedEvent->eventName,
                'status' => $failedEvent->status->value,
                'processing_attempts' => $failedEvent->processingAttempts,
                'error' => $exception->getMessage(),
            ]);

            return ProcessEventResult::failed($failedEvent);
        }
    }
}
