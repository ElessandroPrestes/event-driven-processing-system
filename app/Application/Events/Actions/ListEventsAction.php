<?php

namespace App\Application\Events\Actions;

use App\Domain\Events\Contracts\EventRepository;
use App\Domain\Events\DataTransferObjects\PaginatedEventsData;

final class ListEventsAction
{
    public function __construct(
        private readonly EventRepository $events,
    ) {}

    /**
     * @param  array<int, string>  $statuses
     */
    public function handle(
        array $statuses = [],
        ?string $eventName = null,
        ?string $traceId = null,
        int $page = 1,
        int $perPage = 20,
    ): PaginatedEventsData {
        return $this->events->paginate($statuses, $eventName, $traceId, $page, $perPage);
    }
}
