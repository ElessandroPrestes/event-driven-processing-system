<?php

namespace App\Application\Events\Contracts;

use App\Domain\Events\DataTransferObjects\StoredEventData;

interface EventProcessor
{
    public function eventName(): string;

    /**
     * @return array<string, mixed>
     */
    public function process(StoredEventData $event): array;
}
