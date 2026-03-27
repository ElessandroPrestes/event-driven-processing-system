<?php

namespace App\Interfaces\Http\Resources\Api\V1;

use App\Application\Events\DataTransferObjects\QuarantinedMessageData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QuarantinedMessageData
 */
final class QuarantinedMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var QuarantinedMessageData $message */
        $message = $this->resource;

        return [
            'message_id' => $message->messageId,
            'event_id' => $message->eventId,
            'trace_id' => $message->traceId,
            'event_name' => $message->eventName,
            'exchange' => $message->exchange,
            'routing_key' => $message->routingKey,
            'body' => $message->body,
            'raw_body' => $message->rawBody,
            'headers' => $message->headers,
            'dead_letter_history' => $message->deadLetterHistory,
            'dead_letter_reason' => $message->deadLetterReason,
            'persisted_event_status' => $message->persistedEventStatus?->value,
            'replay_strategy' => $message->replayStrategy,
        ];
    }
}
