<?php

namespace App\Application\Events\Exceptions;

use App\Domain\Events\DataTransferObjects\StoredEventData;
use RuntimeException;
use Throwable;

final class EventPublicationException extends RuntimeException
{
    public function __construct(
        private readonly StoredEventData $event,
        Throwable $previous,
    ) {
        parent::__construct('Falha ao publicar o evento.', previous: $previous);
    }

    public function event(): StoredEventData
    {
        return $this->event;
    }
}
