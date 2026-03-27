<?php

namespace App\Interfaces\Http\Requests\Api\V1;

use App\Interfaces\Http\Requests\Api\V1\Concerns\InteractsWithPagination;
use Illuminate\Foundation\Http\FormRequest;

final class ListEventHistoryRequest extends FormRequest
{
    use InteractsWithPagination;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->paginationRules();
    }

    protected function paginationConfigPath(string $suffix = ''): string
    {
        return "event_pipeline.api.pagination.event_history{$suffix}";
    }
}
