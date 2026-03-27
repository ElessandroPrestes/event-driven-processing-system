<?php

namespace App\Application\Events\Actions;

use App\Domain\Events\Contracts\EventRepository;
use App\Domain\Events\DataTransferObjects\EventListCriteriaData;
use App\Domain\Events\DataTransferObjects\PaginatedEventsData;

final class ListEventsAction
{
    public function __construct(
        private readonly EventRepository $events,
    ) {}

    public function handle(EventListCriteriaData $criteria): PaginatedEventsData
    {
        return $this->events->paginate($criteria);
    }
}
