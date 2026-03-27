<?php

namespace App\Application\Events\Contracts;

use App\Domain\Events\DataTransferObjects\StoredEventData;

interface EventRetryScheduler
{
    public function schedule(StoredEventData $event, int $delayInMilliseconds): void;
}
