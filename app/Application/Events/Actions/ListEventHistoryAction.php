<?php

namespace App\Application\Events\Actions;

use App\Domain\Events\Contracts\EventHistoryRepository;
use App\Domain\Events\Contracts\EventRepository;
use App\Domain\Events\DataTransferObjects\EventHistoryEntryData;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ListEventHistoryAction
{
    public function __construct(
        private readonly EventRepository $events,
        private readonly EventHistoryRepository $history,
    ) {}

    /**
     * @return array<int, EventHistoryEntryData>
     */
    public function handle(string $eventId): array
    {
        if ($this->events->findById($eventId) === null) {
            throw new NotFoundHttpException('Evento nao encontrado.');
        }

        return $this->history->listForEvent($eventId);
    }
}
