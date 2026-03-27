<?php

namespace App\Domain\Events\Enums;

enum EventStatus: string
{
    case RECEIVED = 'received';
    case QUEUED = 'queued';
    case PROCESSING = 'processing';
    case PROCESSED = 'processed';
    case PROCESSING_FAILED = 'processing_failed';
    case PUBLISH_FAILED = 'publish_failed';
}
