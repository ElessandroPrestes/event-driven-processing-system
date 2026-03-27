<?php

namespace App\Interfaces\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class ReplayQuarantinedMessagesRequest extends FormRequest
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
        return [
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function limit(): int
    {
        return (int) $this->validated('limit', 1);
    }
}
