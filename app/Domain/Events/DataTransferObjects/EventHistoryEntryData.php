<?php

namespace App\Domain\Events\DataTransferObjects;

use App\Domain\Events\Enums\EventStatus;
use Carbon\CarbonImmutable;

final readonly class EventHistoryEntryData
{
    /**
     * @param  array<string, mixed>|null  $context
     */
    public function __construct(
        public string $id,
        public string $eventId,
        public string $action,
        public string $source,
        public ?EventStatus $fromStatus,
        public ?EventStatus $toStatus,
        public ?array $context,
        public CarbonImmutable $createdAt,
    ) {}
}
