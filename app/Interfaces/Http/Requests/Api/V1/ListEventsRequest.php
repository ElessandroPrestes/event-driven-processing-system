<?php

namespace App\Interfaces\Http\Requests\Api\V1;

use App\Domain\Events\DataTransferObjects\EventListCriteriaData;
use App\Domain\Events\Enums\EventStatus;
use App\Interfaces\Http\Requests\Api\V1\Concerns\InteractsWithPagination;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListEventsRequest extends FormRequest
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
        return [
            'status' => ['sometimes', 'array'],
            'status.*' => ['string', Rule::enum(EventStatus::class)],
            'event_name' => ['sometimes', 'string', Rule::in(config('event_pipeline.supported_events'))],
            'trace_id' => ['sometimes', 'string', 'max:128'],
            ...$this->paginationRules(),
        ];
    }

    protected function prepareForValidation(): void
    {
        $status = $this->query('status');

        if (is_string($status)) {
            $this->merge([
                'status' => array_values(array_filter(explode(',', $status))),
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    public function statuses(): array
    {
        /** @var array<int, string> $statuses */
        $statuses = $this->validated('status', []);

        return $statuses;
    }

    public function eventName(): ?string
    {
        $eventName = $this->validated('event_name');

        return is_string($eventName) ? $eventName : null;
    }

    public function traceId(): ?string
    {
        $traceId = $this->validated('trace_id');

        return is_string($traceId) ? $traceId : null;
    }

    protected function paginationConfigPath(string $suffix = ''): string
    {
        return "event_pipeline.api.pagination.events{$suffix}";
    }

    public function criteria(): EventListCriteriaData
    {
        return new EventListCriteriaData(
            statuses: $this->statuses(),
            eventName: $this->eventName(),
            traceId: $this->traceId(),
            page: $this->currentPage(),
            perPage: $this->perPage(),
        );
    }
}
