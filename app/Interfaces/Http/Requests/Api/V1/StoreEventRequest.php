<?php

namespace App\Interfaces\Http\Requests\Api\V1;

use App\Domain\Events\DataTransferObjects\EventPayloadData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        return [
            'event_name' => ['required', 'string', 'max:120', Rule::in(config('event_pipeline.supported_events'))],
            'payload' => ['required', 'array', 'min:1'],
            'metadata' => ['nullable', 'array'],
            'occurred_at' => ['nullable', 'date'],
            'idempotency_key' => ['required', 'string', 'max:255'],
        ];
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
        /** @var array<string, mixed> $payload */
        $payload = $this->input('payload', []);
        /** @var array<string, mixed>|null $metadata */
        $metadata = $this->input('metadata');
        /** @var string|null $occurredAt */
        $occurredAt = $this->input('occurred_at');

        return new EventPayloadData(
            eventName: $this->string('event_name')->toString(),
            payload: $payload,
            metadata: $metadata,
            idempotencyKey: $this->string('idempotency_key')->toString(),
            occurredAt: $occurredAt === null ? null : CarbonImmutable::parse($occurredAt),
        );
    }
}
