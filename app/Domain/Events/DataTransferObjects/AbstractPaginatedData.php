<?php

namespace App\Domain\Events\DataTransferObjects;

/**
 * @template TItem
 */
abstract readonly class AbstractPaginatedData
{
    /**
     * @param  array<int, TItem>  $items
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

    /**
     * @return array<string, int|bool>
     */
    public function meta(): array
    {
        return [
            'count' => $this->count(),
            'current_page' => $this->currentPage,
            'per_page' => $this->perPage,
            'total' => $this->total,
            'last_page' => $this->lastPage,
            'has_more_pages' => $this->hasMorePages(),
        ];
    }
}
