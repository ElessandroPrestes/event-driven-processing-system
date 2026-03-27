<?php

namespace Tests\Fakes;

use App\Application\Events\Contracts\EventProcessor;
use App\Domain\Events\DataTransferObjects\StoredEventData;
use RuntimeException;

final class FailingEventProcessor implements EventProcessor
{
    public function __construct(
        private readonly string $supportedEvent,
        private readonly string $message = 'Falha simulada no processamento.',
    ) {}

    public function eventName(): string
    {
        return $this->supportedEvent;
    }

    /**
     * @return array<string, mixed>
     */
    public function process(StoredEventData $event): array
    {
        throw new RuntimeException($this->message);
    }
}
