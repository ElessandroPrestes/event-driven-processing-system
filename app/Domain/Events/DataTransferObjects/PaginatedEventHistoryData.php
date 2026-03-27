<?php

namespace App\Domain\Events\DataTransferObjects;

final readonly class PaginatedEventHistoryData
{
    /**
     * @param  array<int, EventHistoryEntryData>  $items
     */
    public function __construct(
        public array $items,
        public int $currentPage,
        public int $perPage,
        public int $total,
        public int $lastPage,
    ) {}

    public function count(): int
    {
        return count($this->items);
    }

    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage;
    }
}
