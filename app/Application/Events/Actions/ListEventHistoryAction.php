<?php

namespace App\Application\Events\Actions;

use App\Domain\Events\Contracts\EventHistoryRepository;
use App\Domain\Events\Contracts\EventRepository;
use App\Domain\Events\DataTransferObjects\PaginatedEventHistoryData;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ListEventHistoryAction
{
    public function __construct(
        private readonly EventRepository $events,
        private readonly EventHistoryRepository $history,
    ) {}

    public function handle(string $eventId, int $page = 1, int $perPage = 20): PaginatedEventHistoryData
    {
        if ($this->events->findById($eventId) === null) {
            throw new NotFoundHttpException('Evento nao encontrado.');
        }

        return $this->history->paginateForEvent($eventId, $page, $perPage);
    }
}
