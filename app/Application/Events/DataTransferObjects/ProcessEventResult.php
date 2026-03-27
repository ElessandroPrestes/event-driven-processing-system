<?php

namespace App\Application\Events\DataTransferObjects;

use App\Domain\Events\DataTransferObjects\StoredEventData;

final readonly class ProcessEventResult
{
    public function __construct(
        public ?StoredEventData $event,
        public bool $shouldRequeue,
        public bool $skipped,
    ) {}

    public static function processed(StoredEventData $event): self
    {
        return new self($event, false, false);
    }

    public static function retry(StoredEventData $event): self
    {
        return new self($event, true, false);
    }

    public static function failed(StoredEventData $event): self
    {
        return new self($event, false, false);
    }

    public static function skipped(): self
    {
        return new self(null, false, true);
    }
}
