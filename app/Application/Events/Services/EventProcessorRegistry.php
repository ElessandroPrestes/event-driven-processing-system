<?php

namespace App\Application\Events\Services;

use App\Application\Events\Contracts\EventProcessor;
use RuntimeException;

final class EventProcessorRegistry
{
    /**
     * @param  array<int, EventProcessor>  $processors
     */
    public function __construct(
        private readonly array $processors,
    ) {}

    public function for(string $eventName): EventProcessor
    {
        foreach ($this->processors as $processor) {
            if ($processor->eventName() === $eventName) {
                return $processor;
            }
        }

        throw new RuntimeException(sprintf('Nenhum processor registrado para o evento %s.', $eventName));
    }
}
