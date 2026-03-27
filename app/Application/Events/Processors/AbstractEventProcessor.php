<?php

namespace App\Application\Events\Processors;

use App\Application\Events\Contracts\EventProcessor;
use App\Domain\Events\DataTransferObjects\StoredEventData;
use RuntimeException;

abstract class AbstractEventProcessor implements EventProcessor
{
    protected function requireString(StoredEventData $event, string $key): string
    {
        $value = $event->payload[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException(sprintf(
                'Campo "%s" obrigatorio para o processamento do evento %s.',
                $key,
                $event->eventName,
            ));
        }

        return $value;
    }
}
