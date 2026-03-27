<?php

namespace App\Domain\Events\DataTransferObjects;

final readonly class EventListCriteriaData
{
    /**
     * @param  array<int, string>  $statuses
     */
    public function __construct(
        public array $statuses = [],
        public ?string $eventName = null,
        public ?string $traceId = null,
        public int $page = 1,
        public int $perPage = 20,
    ) {}
}
