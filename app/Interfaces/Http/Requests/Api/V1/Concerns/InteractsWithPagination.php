<?php

namespace App\Interfaces\Http\Requests\Api\V1\Concerns;

trait InteractsWithPagination
{
    /**
     * @return array<string, mixed>
     */
    protected function paginationRules(): array
    {
        $maxPerPage = max((int) config($this->paginationConfigPath('.max_per_page'), 100), 1);

        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', "max:{$maxPerPage}"],
        ];
    }

    public function currentPage(): int
    {
        return (int) $this->validated('page', 1);
    }

    public function perPage(): int
    {
        $defaultPerPage = max((int) config($this->paginationConfigPath('.default_per_page'), 20), 1);

        return (int) $this->validated('per_page', $defaultPerPage);
    }

    abstract protected function paginationConfigPath(string $suffix = ''): string;
}
