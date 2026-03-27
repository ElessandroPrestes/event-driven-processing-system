<?php

namespace App\Application\Events\Exceptions;

use App\Domain\Events\DataTransferObjects\StoredEventData;
use RuntimeException;

final class EventRetryNotAllowedException extends RuntimeException
{
    public function __construct(
        private readonly StoredEventData $event,
    ) {
        parent::__construct('O evento informado nao pode ser reenfileirado no estado atual.');
    }

    public function event(): StoredEventData
    {
        return $this->event;
    }
}
