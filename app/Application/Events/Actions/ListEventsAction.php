<?php

namespace App\Application\Events\Actions;

use App\Domain\Events\Contracts\EventRepository;
use App\Domain\Events\DataTransferObjects\StoredEventData;

final class ListEventsAction
{
    public function __construct(
        private readonly EventRepository $events,
    ) {}

    /**
     * @param  array<int, string>  $statuses
     * @return array<int, StoredEventData>
     */
    public function handle(array $statuses = [], ?string $eventName = null): array
    {
        return $this->events->list($statuses, $eventName);
    }
}
