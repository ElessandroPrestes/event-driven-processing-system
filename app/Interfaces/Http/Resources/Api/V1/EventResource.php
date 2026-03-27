<?php

namespace App\Interfaces\Http\Resources\Api\V1;

use App\Domain\Events\DataTransferObjects\StoredEventData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StoredEventData
 */
final class EventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var StoredEventData $event */
        $event = $this->resource;

        return [
            'id' => $event->id,
            'event_name' => $event->eventName,
            'status' => $event->status->value,
            'idempotency_key' => $event->idempotencyKey,
            'payload' => $event->payload,
            'metadata' => $event->metadata,
            'occurred_at' => $event->occurredAt?->toIso8601String(),
            'queued_at' => $event->queuedAt?->toIso8601String(),
            'consumed_at' => $event->consumedAt?->toIso8601String(),
            'processed_at' => $event->processedAt?->toIso8601String(),
            'processing_attempts' => $event->processingAttempts,
            'processing_result' => $event->processingResult,
            'failure_reason' => $event->failureReason,
            'created_at' => $event->createdAt->toIso8601String(),
            'updated_at' => $event->updatedAt->toIso8601String(),
        ];
    }
}
