<?php

namespace App\Application\Events\DataTransferObjects;

use App\Domain\Events\DataTransferObjects\StoredEventData;

final readonly class ReceiveEventResult
{
    public function __construct(
        public StoredEventData $event,
        public bool $duplicate,
    ) {}

    public static function queued(StoredEventData $event): self
    {
        return new self($event, false);
    }

    public static function duplicate(StoredEventData $event): self
    {
        return new self($event, true);
    }
}
