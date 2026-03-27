<?php

use App\Application\Events\Exceptions\EventRetryDispatchException;

it('keeps the failed event and previous exception when retry dispatching fails', function (): void {
    $event = storedEvent('payment.received', [
        'payment_id' => 'retry-dispatch-001',
    ]);
    $previous = new RuntimeException('RabbitMQ indisponivel.');
    $exception = new EventRetryDispatchException($event, $previous);

    expect($exception->getMessage())->toBe('Falha ao reenfileirar o evento.')
        ->and($exception->event())->toBe($event)
        ->and($exception->getPrevious())->toBe($previous);
});
