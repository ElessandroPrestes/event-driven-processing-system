<?php

namespace App\Application\Events\Support;

use App\Domain\Events\DataTransferObjects\EventPayloadData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class EventPayloadDataFactory
{
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function fromArray(array $attributes, string $traceId): EventPayloadData
    {
        /** @var array<string, mixed> $validated */
        $validated = Validator::make($attributes, $this->rules())->validate();

        return $this->fromValidated($validated, $traceId);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function fromValidated(array $validated, string $traceId): EventPayloadData
    {
        /** @var array<string, mixed> $payload */
        $payload = $validated['payload'] ?? [];
        /** @var array<string, mixed>|null $metadata */
        $metadata = $validated['metadata'] ?? null;
        /** @var string|null $occurredAt */
        $occurredAt = $validated['occurred_at'] ?? null;

        return new EventPayloadData(
            eventName: (string) $validated['event_name'],
            payload: $payload,
            metadata: $metadata,
            idempotencyKey: (string) $validated['idempotency_key'],
            occurredAt: $occurredAt === null ? null : CarbonImmutable::parse($occurredAt),
            traceId: $traceId,
        );
    }
}
