<?php

namespace App\Application\Events\Exceptions;

use RuntimeException;

final class IdempotencyConflictException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Conflito de idempotencia.');
    }
}
