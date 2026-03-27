<?php

namespace App\Interfaces\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class ListEventHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxPerPage = max((int) config('event_pipeline.api.pagination.event_history.max_per_page', 100), 1);

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
        $defaultPerPage = max((int) config('event_pipeline.api.pagination.event_history.default_per_page', 20), 1);

        return (int) $this->validated('per_page', $defaultPerPage);
    }
}
