<?php

use App\Application\Events\Actions\RetryEventAction;
use App\Application\Events\Exceptions\EventRetryDispatchException;
use App\Application\Events\Exceptions\EventRetryNotAllowedException;
use App\Application\Events\Services\EventHistoryRecorder;
use App\Domain\Events\DataTransferObjects\EventPayloadData;
use App\Domain\Events\DataTransferObjects\StoredEventData;
use App\Domain\Events\Enums\EventStatus;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Fakes\FakeEventPublisher;
use Tests\Fakes\InMemoryEventHistoryRepository;
use Tests\Fakes\InMemoryEventRepository;

it('throws not found when the event does not exist', function (): void {
    [$action] = makeRetryActionContext();

    expect(fn () => $action->handle('evt-missing'))
        ->toThrow(NotFoundHttpException::class, 'Evento nao encontrado.');
});

it('rejects retries for non retryable statuses', function (): void {
    [$action, $events] = makeRetryActionContext();
    $event = storeEventWithStatus($events, EventStatus::QUEUED);

    try {
        $action->handle($event->id);
        test()->fail('Expected the action to reject the retry.');
    } catch (EventRetryNotAllowedException $exception) {
        expect($exception->event()->id)->toBe($event->id)
            ->and($exception->event()->status)->toBe(EventStatus::QUEUED);
    }
});

it('requeues a publish_failed event and records the retry history', function (): void {
    [$action, $events, $publisher, $history] = makeRetryActionContext();
    $event = storeEventWithStatus($events, EventStatus::PUBLISH_FAILED);

    $queuedEvent = $action->handle($event->id);
    $entries = $history->listForEvent($event->id);

    expect($publisher->published)->toHaveCount(1)
        ->and($publisher->published[0]->id)->toBe($event->id)
        ->and($queuedEvent->status)->toBe(EventStatus::QUEUED)
        ->and($queuedEvent->failureReason)->toBeNull()
        ->and($entries)->toHaveCount(2)
        ->and($entries[0]->action)->toBe('retry_requested')
        ->and($entries[1]->action)->toBe('retry_enqueued');
});

it('marks a retry dispatch failure back to processing_failed and records the failure', function (): void {
    [$action, $events, $publisher, $history] = makeRetryActionContext();
    $publisher->shouldFail = true;

    $event = storeEventWithStatus($events, EventStatus::PROCESSING_FAILED);

    try {
        $action->handle($event->id);
        test()->fail('Expected the retry dispatch to fail.');
    } catch (EventRetryDispatchException $exception) {
        $failedEvent = $exception->event();
        $entries = $history->listForEvent($event->id);

        expect($failedEvent->status)->toBe(EventStatus::PROCESSING_FAILED)
            ->and($failedEvent->failureReason)->toBe('Falha ao reenfileirar manualmente: RabbitMQ indisponivel.')
            ->and($exception->getPrevious()?->getMessage())->toBe('RabbitMQ indisponivel.')
            ->and($entries)->toHaveCount(2)
            ->and($entries[0]->action)->toBe('retry_requested')
            ->and($entries[1]->action)->toBe('retry_enqueue_failed');
    }
});

/**
 * @return array{0: RetryEventAction, 1: InMemoryEventRepository, 2: FakeEventPublisher, 3: InMemoryEventHistoryRepository}
 */
function makeRetryActionContext(): array
{
    $events = new InMemoryEventRepository;
    $publisher = new FakeEventPublisher;
    $history = new InMemoryEventHistoryRepository;

    return [
        new RetryEventAction(
            $events,
            $publisher,
            new EventHistoryRecorder($history),
        ),
        $events,
        $publisher,
        $history,
    ];
}

function storeEventWithStatus(InMemoryEventRepository $events, EventStatus $status): StoredEventData
{
    $event = $events->create(new EventPayloadData(
        eventName: 'notification.requested',
        payload: [
            'notification_id' => sprintf('retry-%s', uniqid('', true)),
        ],
        metadata: null,
        idempotencyKey: sprintf('idem-%s', uniqid('', true)),
        occurredAt: CarbonImmutable::now(),
        traceId: sprintf('trace-%s', uniqid('', true)),
    ));

    if ($status === EventStatus::PUBLISH_FAILED) {
        return $events->markAsPublishFailed($event->id, 'Falha inicial.');
    }

    if ($status === EventStatus::PROCESSING_FAILED) {
        $queued = $events->markAsQueued($event->id, CarbonImmutable::now());
        $processing = $events->markAsProcessing($queued->id, CarbonImmutable::now());

        return $events->markAsProcessingFailed($processing->id, 'Falha definitiva.');
    }

    if ($status === EventStatus::QUEUED) {
        return $events->markAsQueued($event->id, CarbonImmutable::now());
    }

    return $event;
}
