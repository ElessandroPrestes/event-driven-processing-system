<?php

namespace Tests\Fakes;

use App\Application\Events\Contracts\EventRetryScheduler;
use App\Domain\Events\DataTransferObjects\StoredEventData;
use RuntimeException;

final class FakeEventRetryScheduler implements EventRetryScheduler
{
    /**
     * @var list<array{event: StoredEventData, delay_ms: int}>
     */
    public array $scheduled = [];

    public bool $shouldFail = false;

    public function schedule(StoredEventData $event, int $delayInMilliseconds): void
    {
        if ($this->shouldFail) {
            throw new RuntimeException('Falha ao agendar retry no RabbitMQ.');
        }

        $this->scheduled[] = [
            'event' => $event,
            'delay_ms' => $delayInMilliseconds,
        ];
    }
}
