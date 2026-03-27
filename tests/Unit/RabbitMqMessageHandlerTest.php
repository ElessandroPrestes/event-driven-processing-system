<?php

use App\Application\Events\Actions\ProcessQueuedEventAction;
use App\Application\Events\Processors\UserCreatedProcessor;
use App\Application\Events\Services\EventHistoryRecorder;
use App\Application\Events\Services\EventProcessorRegistry;
use App\Application\Events\Services\EventRetryDelayCalculator;
use App\Domain\Events\DataTransferObjects\EventPayloadData;
use App\Domain\Events\DataTransferObjects\StoredEventData;
use App\Domain\Events\Enums\EventStatus;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqMessageHandler;
use Carbon\CarbonImmutable;
use Mockery\MockInterface;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Tests\Fakes\FailingEventProcessor;
use Tests\Fakes\FakeEventRetryScheduler;
use Tests\Fakes\InMemoryEventHistoryRepository;
use Tests\Fakes\InMemoryEventRepository;

afterEach(function (): void {
    Mockery::close();
});

it('acknowledges successfully processed messages', function (): void {
    [$handler, $events] = makeRabbitMqMessageHandler([
        new UserCreatedProcessor,
    ]);

    $event = queueEvent($events, 'user.created', [
        'user_id' => 'worker-success-001',
    ]);

    [$message, $channel] = makeIncomingMessage(
        messageId: $event->id,
        body: [
            'id' => $event->id,
            'trace_id' => $event->traceId,
        ],
        routingKey: $event->eventName,
    );

    $channel->shouldReceive('basic_ack')
        ->once()
        ->with(1, false);

    $result = $handler->handle($message, 3);

    expect($result->shouldRetry)->toBeFalse()
        ->and($result->event?->status)->toBe(EventStatus::PROCESSED)
        ->and($result->event?->processingAttempts)->toBe(1);
});

it('schedules transient processing failures with progressive delay and acknowledges the original message', function (): void {
    [$handler, $events, $history, $scheduler] = makeRabbitMqMessageHandler([
        new FailingEventProcessor('payment.received', 'Falha transitória.'),
    ]);

    $event = queueEvent($events, 'payment.received', [
        'payment_id' => 'worker-retry-001',
    ]);

    [$message, $channel] = makeIncomingMessage(
        messageId: $event->id,
        body: [
            'id' => $event->id,
            'trace_id' => $event->traceId,
        ],
        routingKey: $event->eventName,
    );

    $channel->shouldReceive('basic_ack')
        ->once()
        ->with(1, false);

    $result = $handler->handle($message, 3);
    $entries = $history->listForEvent($event->id);

    expect($result->shouldRetry)->toBeTrue()
        ->and($result->event?->status)->toBe(EventStatus::QUEUED)
        ->and($result->event?->failureReason)->toBe('Falha transitória.')
        ->and($scheduler->scheduled)->toHaveCount(1)
        ->and($scheduler->scheduled[0]['event']->id)->toBe($event->id)
        ->and($scheduler->scheduled[0]['delay_ms'])->toBe(5000)
        ->and($entries)->toHaveCount(3)
        ->and($entries[2]->action)->toBe('retry_scheduled')
        ->and($entries[2]->context)->toMatchArray([
            'delay_ms' => 5000,
            'processing_attempts' => 1,
        ]);
});

it('falls back to broker requeue when delayed retry scheduling fails', function (): void {
    [$handler, $events, $history, $scheduler] = makeRabbitMqMessageHandler([
        new FailingEventProcessor('payment.received', 'Falha transitória.'),
    ]);

    $scheduler->shouldFail = true;

    $event = queueEvent($events, 'payment.received', [
        'payment_id' => 'worker-retry-fallback-001',
    ]);

    [$message, $channel] = makeIncomingMessage(
        messageId: $event->id,
        body: [
            'id' => $event->id,
            'trace_id' => $event->traceId,
        ],
        routingKey: $event->eventName,
    );

    $channel->shouldReceive('basic_nack')
        ->once()
        ->with(1, false, true);

    $result = $handler->handle($message, 3);
    $entries = $history->listForEvent($event->id);

    expect($result->shouldRetry)->toBeTrue()
        ->and($scheduler->scheduled)->toHaveCount(0)
        ->and($entries)->toHaveCount(2);
});

it('dead letters messages after the final processing failure and records the quarantine in history', function (): void {
    [$handler, $events, $history] = makeRabbitMqMessageHandler([
        new FailingEventProcessor('notification.requested', 'Falha definitiva.'),
    ]);

    $event = queueEvent($events, 'notification.requested', [
        'notification_id' => 'worker-dead-letter-001',
    ]);

    [$message, $channel] = makeIncomingMessage(
        messageId: $event->id,
        body: [
            'id' => $event->id,
            'trace_id' => $event->traceId,
        ],
        routingKey: $event->eventName,
    );

    $channel->shouldReceive('basic_nack')
        ->once()
        ->with(1, false, false);

    $result = $handler->handle($message, 1);
    $entries = $history->listForEvent($event->id);

    expect($result->event?->status)->toBe(EventStatus::PROCESSING_FAILED)
        ->and($entries)->toHaveCount(3)
        ->and($entries[2]->action)->toBe('dead_lettered')
        ->and($entries[2]->context)->toMatchArray([
            'reason' => 'processing_failed_max_attempts',
            'processing_attempts' => 1,
            'failure_reason' => 'Falha definitiva.',
        ]);
});

it('dead letters messages when the event id is missing', function (): void {
    [$handler] = makeRabbitMqMessageHandler([
        new UserCreatedProcessor,
    ]);

    [$message, $channel] = makeIncomingMessage(
        messageId: null,
        body: [
            'trace_id' => 'trace-missing-event-id',
        ],
        routingKey: 'user.created',
    );

    $channel->shouldReceive('basic_nack')
        ->once()
        ->with(1, false, false);

    $result = $handler->handle($message, 3);

    expect($result->skipped)->toBeTrue()
        ->and($result->skipReason)->toBe('missing_event_id');
});

it('dead letters messages whose event cannot be found in persistence', function (): void {
    [$handler] = makeRabbitMqMessageHandler([
        new UserCreatedProcessor,
    ]);

    [$message, $channel] = makeIncomingMessage(
        messageId: 'missing-event-001',
        body: [
            'id' => 'missing-event-001',
            'trace_id' => 'trace-missing-persistence',
        ],
        routingKey: 'user.created',
    );

    $channel->shouldReceive('basic_nack')
        ->once()
        ->with(1, false, false);

    $result = $handler->handle($message, 3);

    expect($result->skipped)->toBeTrue()
        ->and($result->skipReason)->toBe('not_found');
});

/**
 * @param  array<int, object>  $processors
 * @return array{0: RabbitMqMessageHandler, 1: InMemoryEventRepository, 2: InMemoryEventHistoryRepository, 3: FakeEventRetryScheduler}
 */
function makeRabbitMqMessageHandler(array $processors): array
{
    $events = new InMemoryEventRepository;
    $history = new InMemoryEventHistoryRepository;
    $scheduler = new FakeEventRetryScheduler;
    $historyRecorder = new EventHistoryRecorder($history);
    $processEvent = new ProcessQueuedEventAction(
        $events,
        new EventProcessorRegistry($processors),
        $historyRecorder,
    );

    config()->set('event_pipeline.consumer.retry_base_delay_ms', 5000);
    config()->set('event_pipeline.consumer.retry_multiplier', 2.0);
    config()->set('event_pipeline.consumer.retry_max_delay_ms', 60000);

    return [
        new RabbitMqMessageHandler(
            $processEvent,
            $scheduler,
            new EventRetryDelayCalculator,
            $historyRecorder,
        ),
        $events,
        $history,
        $scheduler,
    ];
}

function queueEvent(InMemoryEventRepository $events, string $eventName, array $payload): StoredEventData
{
    $event = $events->create(new EventPayloadData(
        eventName: $eventName,
        payload: $payload,
        metadata: null,
        idempotencyKey: sprintf('idempotency-%s', uniqid('', true)),
        occurredAt: CarbonImmutable::now(),
        traceId: sprintf('trace-%s', uniqid('', true)),
    ));

    return $events->markAsQueued($event->id, CarbonImmutable::now());
}

/**
 * @return array{0: AMQPMessage, 1: MockInterface}
 */
function makeIncomingMessage(?string $messageId, array $body, string $routingKey): array
{
    $properties = [
        'application_headers' => new AMQPTable([
            'trace_id' => $body['trace_id'] ?? 'trace-worker-message',
        ]),
    ];

    if ($messageId !== null) {
        $properties['message_id'] = $messageId;
    }

    $message = new AMQPMessage(json_encode($body, JSON_THROW_ON_ERROR), $properties);
    $channel = Mockery::mock(AMQPChannel::class);
    $message->setChannel($channel);
    $message->setDeliveryInfo(1, false, 'eventflow.events', $routingKey);

    return [$message, $channel];
}
