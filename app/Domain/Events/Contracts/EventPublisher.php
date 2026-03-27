<?php

namespace App\Domain\Events\Contracts;

use App\Domain\Events\DataTransferObjects\StoredEventData;

interface EventPublisher
{
    public function publish(StoredEventData $event): void;
}
