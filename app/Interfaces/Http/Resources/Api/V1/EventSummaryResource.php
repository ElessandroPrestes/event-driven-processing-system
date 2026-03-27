<?php

namespace App\Interfaces\Http\Resources\Api\V1;

use App\Application\Events\DataTransferObjects\EventSummaryData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EventSummaryData
 */
final class EventSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var EventSummaryData $summary */
        $summary = $this->resource;

        return [
            'total' => $summary->total,
            'pending' => $summary->pending,
            'failed' => $summary->failed,
            'retryable' => $summary->retryable,
            'by_status' => $summary->byStatus,
            'last_received_at' => $summary->lastReceivedAt?->toIso8601String(),
            'last_processed_at' => $summary->lastProcessedAt?->toIso8601String(),
            'oldest_pending_at' => $summary->oldestPendingAt?->toIso8601String(),
        ];
    }
}
