<?php

namespace App\Domain\Events\Enums;

enum EventStatus: string
{
    case RECEIVED = 'received';
    case QUEUED = 'queued';
    case PUBLISH_FAILED = 'publish_failed';
}
