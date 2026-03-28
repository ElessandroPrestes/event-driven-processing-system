<?php

namespace App\Interfaces\Http\Requests\Api\V1;

use App\Application\Events\Support\EventPayloadDataFactory;
use App\Domain\Events\DataTransferObjects\EventPayloadData;
use Illuminate\Foundation\Http\FormRequest;

final class StoreEventRequest extends FormRequest
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
        return app(EventPayloadDataFactory::class)->rules();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'idempotency_key' => $this->input(
                'idempotency_key',
                $this->header((string) config('event_pipeline.api.idempotency_header'))
            ),
        ]);
    }

    public function toEventPayloadData(): EventPayloadData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return app(EventPayloadDataFactory::class)->fromValidated(
            $validated,
            (string) $this->attributes->get('trace_id'),
        );
    }
}
