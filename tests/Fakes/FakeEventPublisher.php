<?php

namespace Tests\Fakes;

use App\Domain\Events\Contracts\EventPublisher;
use App\Domain\Events\DataTransferObjects\StoredEventData;
use RuntimeException;

final class FakeEventPublisher implements EventPublisher
{
    /**
     * @var list<StoredEventData>
     */
    public array $published = [];

    public bool $shouldFail = false;

    public function publish(StoredEventData $event): void
    {
        if ($this->shouldFail) {
            throw new RuntimeException('RabbitMQ indisponivel.');
        }

        $this->published[] = $event;
    }
}
