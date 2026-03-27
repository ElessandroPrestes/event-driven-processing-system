<?php

namespace App\Application\Events\Actions;

use App\Domain\Events\Contracts\EventRepository;
use App\Domain\Events\DataTransferObjects\StoredEventData;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ShowEventAction
{
    public function __construct(
        private readonly EventRepository $events,
    ) {}

    public function handle(string $eventId): StoredEventData
    {
        $event = $this->events->findById($eventId);

        if ($event === null) {
            throw new NotFoundHttpException('Evento nao encontrado.');
        }

        return $event;
    }
}
