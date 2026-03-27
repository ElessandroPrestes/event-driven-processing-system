<?php

namespace App\Interfaces\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class EventSummaryRequest extends FormRequest
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
            'event_name' => ['sometimes', 'string', Rule::in(config('event_pipeline.supported_events'))],
        ];
    }

    public function eventName(): ?string
    {
        $eventName = $this->validated('event_name');

        return is_string($eventName) ? $eventName : null;
    }
}
