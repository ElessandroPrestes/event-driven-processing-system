<?php

namespace App\Application\Events\Processors;

use App\Domain\Events\DataTransferObjects\StoredEventData;

final class NotificationRequestedProcessor extends AbstractEventProcessor
{
    public function eventName(): string
    {
        return 'notification.requested';
    }

    /**
     * @return array<string, mixed>
     */
    public function process(StoredEventData $event): array
    {
        return [
            'resource' => 'notification',
            'resource_id' => $this->requireString($event, 'notification_id'),
            'summary' => 'Solicitacao de notificacao preparada para entrega.',
        ];
    }
}
