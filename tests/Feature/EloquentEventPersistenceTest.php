<?php

use App\Domain\Events\Contracts\EventHistoryRepository;
use App\Domain\Events\Contracts\EventRepository;
use App\Domain\Events\DataTransferObjects\EventListCriteriaData;
use App\Domain\Events\DataTransferObjects\EventPayloadData;
use App\Domain\Events\DataTransferObjects\PaginatedEventsData;
use App\Domain\Events\Enums\EventStatus;
use App\Infrastructure\Persistence\Repositories\EloquentEventHistoryRepository;
use App\Infrastructure\Persistence\Repositories\EloquentEventRepository;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    it('skips eloquent persistence tests when pdo_sqlite is unavailable', function (): void {
        test()->markTestSkipped('pdo_sqlite nao disponivel neste ambiente.');
    });

    return;
}

uses(RefreshDatabase::class);

it('persists and transitions events through the eloquent event repository', function (): void {
    $repository = app(EventRepository::class);

    expect($repository)->toBeInstanceOf(EloquentEventRepository::class);

    $createdEvent = $repository->create(new EventPayloadData(
        eventName: 'user.created',
        payload: [
            'user_id' => 'eloquent-001',
        ],
        metadata: [
            'source' => 'tests',
        ],
        idempotencyKey: 'idem-eloquent-001',
        occurredAt: CarbonImmutable::parse('2026-03-27T10:00:00+00:00'),
        traceId: 'trace-eloquent-001',
    ));

    $publishFailedEvent = $repository->create(new EventPayloadData(
        eventName: 'payment.received',
        payload: [
            'payment_id' => 'eloquent-002',
        ],
        metadata: null,
        idempotencyKey: 'idem-eloquent-002',
        occurredAt: CarbonImmutable::parse('2026-03-27T10:01:00+00:00'),
        traceId: 'trace-eloquent-002',
    ));

    $processingFailedEvent = $repository->create(new EventPayloadData(
        eventName: 'invoice.generated',
        payload: [
            'invoice_id' => 'eloquent-003',
        ],
        metadata: null,
        idempotencyKey: 'idem-eloquent-003',
        occurredAt: CarbonImmutable::parse('2026-03-27T10:02:00+00:00'),
        traceId: 'trace-eloquent-002',
    ));

    $queued = $repository->markAsQueued($createdEvent->id, CarbonImmutable::parse('2026-03-27T10:05:00+00:00'));
    $processing = $repository->markAsProcessing($queued->id, CarbonImmutable::parse('2026-03-27T10:06:00+00:00'));
    $processed = $repository->markAsProcessed($processing->id, CarbonImmutable::parse('2026-03-27T10:07:00+00:00'), [
        'resource' => 'user',
        'resource_id' => 'eloquent-001',
    ]);

    $publishFailed = $repository->markAsPublishFailed($publishFailedEvent->id, 'RabbitMQ indisponivel.');
    $retryCandidate = $repository->markAsQueued($processingFailedEvent->id, CarbonImmutable::parse('2026-03-27T10:08:00+00:00'));
    $processingFailure = $repository->markAsProcessing($retryCandidate->id, CarbonImmutable::parse('2026-03-27T10:09:00+00:00'));
    $processingFailed = $repository->markAsProcessingFailed($processingFailure->id, 'Falha definitiva.');

    $byId = $repository->findById($processed->id);
    $byIdempotencyKey = $repository->findByIdempotencyKey('idem-eloquent-002');
    $failedEvents = $repository->list(new EventListCriteriaData(
        statuses: ['publish_failed'],
    ));
    $invoiceEvents = $repository->list(new EventListCriteriaData(
        eventName: 'invoice.generated',
    ));
    $traceEvents = $repository->list(new EventListCriteriaData(
        traceId: 'trace-eloquent-002',
    ));
    $paginatedEvents = $repository->paginate(new EventListCriteriaData(
        page: 2,
        perPage: 2,
    ));

    expect($byId?->status)->toBe(EventStatus::PROCESSED)
        ->and($byId?->processingResult)->toMatchArray([
            'resource' => 'user',
            'resource_id' => 'eloquent-001',
        ])
        ->and($byId?->processingAttempts)->toBe(1)
        ->and($byIdempotencyKey?->status)->toBe(EventStatus::PUBLISH_FAILED)
        ->and($byIdempotencyKey?->failureReason)->toBe('RabbitMQ indisponivel.')
        ->and($failedEvents)->toHaveCount(1)
        ->and($failedEvents[0]->id)->toBe($publishFailed->id)
        ->and($invoiceEvents)->toHaveCount(1)
        ->and($invoiceEvents[0]->status)->toBe(EventStatus::PROCESSING_FAILED)
        ->and($invoiceEvents[0]->failureReason)->toBe('Falha definitiva.')
        ->and($traceEvents)->toHaveCount(2)
        ->and(collect($traceEvents)->pluck('id')->all())->toEqualCanonicalizing([
            $processingFailed->id,
            $publishFailed->id,
        ])
        ->and($paginatedEvents)->toBeInstanceOf(PaginatedEventsData::class)
        ->and($paginatedEvents->currentPage)->toBe(2)
        ->and($paginatedEvents->perPage)->toBe(2)
        ->and($paginatedEvents->total)->toBe(3)
        ->and($paginatedEvents->lastPage)->toBe(2)
        ->and($paginatedEvents->count())->toBe(1);
});

it('stores and lists event history entries through the eloquent history repository', function (): void {
    $events = app(EventRepository::class);
    $history = app(EventHistoryRepository::class);

    expect($history)->toBeInstanceOf(EloquentEventHistoryRepository::class);

    $event = $events->create(new EventPayloadData(
        eventName: 'notification.requested',
        payload: [
            'notification_id' => 'history-001',
        ],
        metadata: null,
        idempotencyKey: 'idem-history-001',
        occurredAt: CarbonImmutable::parse('2026-03-27T11:00:00+00:00'),
        traceId: 'trace-history-001',
    ));

    $history->record(
        eventId: $event->id,
        action: 'queued',
        source: 'worker',
        fromStatus: EventStatus::RECEIVED,
        toStatus: EventStatus::QUEUED,
        context: [
            'attempt' => 1,
        ],
    );

    $history->record(
        eventId: $event->id,
        action: 'processed',
        source: 'worker',
        fromStatus: EventStatus::PROCESSING,
        toStatus: EventStatus::PROCESSED,
        context: [
            'result' => 'ok',
        ],
    );

    $entries = $history->listForEvent($event->id);

    expect($entries)->toHaveCount(2)
        ->and($entries[0]->action)->toBe('queued')
        ->and($entries[0]->fromStatus)->toBe(EventStatus::RECEIVED)
        ->and($entries[0]->toStatus)->toBe(EventStatus::QUEUED)
        ->and($entries[0]->context)->toMatchArray([
            'attempt' => 1,
        ])
        ->and($entries[1]->action)->toBe('processed')
        ->and($entries[1]->fromStatus)->toBe(EventStatus::PROCESSING)
        ->and($entries[1]->toStatus)->toBe(EventStatus::PROCESSED)
        ->and($entries[1]->context)->toMatchArray([
            'result' => 'ok',
        ]);
});
