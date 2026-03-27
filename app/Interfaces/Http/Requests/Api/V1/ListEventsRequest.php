<?php

namespace App\Interfaces\Http\Requests\Api\V1;

use App\Domain\Events\DataTransferObjects\EventListCriteriaData;
use App\Domain\Events\Enums\EventStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListEventsRequest extends FormRequest
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
        $maxPerPage = max((int) config('event_pipeline.api.pagination.events.max_per_page', 100), 1);

        return [
            'status' => ['sometimes', 'array'],
            'status.*' => ['string', Rule::enum(EventStatus::class)],
            'event_name' => ['sometimes', 'string', Rule::in(config('event_pipeline.supported_events'))],
            'trace_id' => ['sometimes', 'string', 'max:128'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', "max:{$maxPerPage}"],
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

    public function currentPage(): int
    {
        return (int) $this->validated('page', 1);
    }

    public function perPage(): int
    {
        $defaultPerPage = max((int) config('event_pipeline.api.pagination.events.default_per_page', 20), 1);

        return (int) $this->validated('per_page', $defaultPerPage);
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
