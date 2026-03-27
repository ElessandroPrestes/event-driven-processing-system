<?php

namespace App\Application\Events\Processors;

use App\Domain\Events\DataTransferObjects\StoredEventData;

final class PaymentReceivedProcessor extends AbstractEventProcessor
{
    public function eventName(): string
    {
        return 'payment.received';
    }

    /**
     * @return array<string, mixed>
     */
    public function process(StoredEventData $event): array
    {
        return [
            'resource' => 'payment',
            'resource_id' => $this->requireString($event, 'payment_id'),
            'summary' => 'Pagamento conciliado para processamento interno.',
        ];
    }
}
