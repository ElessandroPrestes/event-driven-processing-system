<?php

namespace App\Interfaces\Http\Resources\Api\V1;

use App\Domain\Events\DataTransferObjects\EventHistoryEntryData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EventHistoryEntryData
 */
final class EventHistoryEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var EventHistoryEntryData $entry */
        $entry = $this->resource;

        return [
            'id' => $entry->id,
            'event_id' => $entry->eventId,
            'action' => $entry->action,
            'source' => $entry->source,
            'from_status' => $entry->fromStatus?->value,
            'to_status' => $entry->toStatus?->value,
            'context' => $entry->context,
            'created_at' => $entry->createdAt->toIso8601String(),
        ];
    }
}
