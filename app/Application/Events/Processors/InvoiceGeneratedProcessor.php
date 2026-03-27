<?php

namespace App\Application\Events\Processors;

use App\Domain\Events\DataTransferObjects\StoredEventData;

final class InvoiceGeneratedProcessor extends AbstractEventProcessor
{
    public function eventName(): string
    {
        return 'invoice.generated';
    }

    /**
     * @return array<string, mixed>
     */
    public function process(StoredEventData $event): array
    {
        return [
            'resource' => 'invoice',
            'resource_id' => $this->requireString($event, 'invoice_id'),
            'summary' => 'Fatura registrada como gerada.',
        ];
    }
}
