<?php

namespace App\Domain\Events\Contracts;

use App\Domain\Events\DataTransferObjects\EventHistoryEntryData;
use App\Domain\Events\DataTransferObjects\PaginatedEventHistoryData;
use App\Domain\Events\Enums\EventStatus;

interface EventHistoryRepository
{
    /**
     * @param  array<string, mixed>|null  $context
     */
    public function record(
        string $eventId,
        string $action,
        string $source,
        ?EventStatus $fromStatus,
        ?EventStatus $toStatus,
        ?array $context = null,
    ): EventHistoryEntryData;

    /**
     * @return array<int, EventHistoryEntryData>
     */
    public function listForEvent(string $eventId): array;

    public function paginateForEvent(string $eventId, int $page = 1, int $perPage = 20): PaginatedEventHistoryData;
}
