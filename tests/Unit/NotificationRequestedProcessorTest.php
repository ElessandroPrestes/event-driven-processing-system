<?php

use App\Application\Events\Processors\NotificationRequestedProcessor;
use App\Domain\Events\Enums\EventStatus;

it('returns the supported event name for notification requests', function (): void {
    expect((new NotificationRequestedProcessor)->eventName())->toBe('notification.requested');
});

it('processes notification requests with the required notification id', function (): void {
    $result = (new NotificationRequestedProcessor)->process(
        storedEvent('notification.requested', [
            'notification_id' => 'notif-001',
        ], EventStatus::QUEUED),
    );

    expect($result)->toMatchArray([
        'resource' => 'notification',
        'resource_id' => 'notif-001',
        'summary' => 'Solicitacao de notificacao preparada para entrega.',
    ]);
});

it('rejects notification requests when the notification id is missing', function (): void {
    expect(fn () => (new NotificationRequestedProcessor)->process(
        storedEvent('notification.requested', [], EventStatus::QUEUED),
    ))->toThrow(RuntimeException::class, 'Campo "notification_id" obrigatorio para o processamento do evento notification.requested.');
});
