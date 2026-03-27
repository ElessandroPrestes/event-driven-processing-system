<?php

namespace App\Application\Events\DataTransferObjects;

use App\Domain\Events\Enums\EventStatus;

final readonly class QuarantinedMessageData
{
    /**
     * @param  array<string, mixed>|null  $body
     * @param  array<string, mixed>  $headers
     * @param  array<int, array<string, mixed>>|null  $deadLetterHistory
     */
    public function __construct(
        public ?string $messageId,
        public ?string $eventId,
        public ?string $traceId,
        public ?string $eventName,
        public string $exchange,
        public string $routingKey,
        public ?array $body,
        public string $rawBody,
        public array $headers,
        public ?array $deadLetterHistory,
        public ?string $deadLetterReason,
        public ?EventStatus $persistedEventStatus,
        public ?string $replayStrategy = null,
    ) {}

    public function withReplayStrategy(string $replayStrategy, ?EventStatus $persistedEventStatus = null): self
    {
        return new self(
            messageId: $this->messageId,
            eventId: $this->eventId,
            traceId: $this->traceId,
            eventName: $this->eventName,
            exchange: $this->exchange,
            routingKey: $this->routingKey,
            body: $this->body,
            rawBody: $this->rawBody,
            headers: $this->headers,
            deadLetterHistory: $this->deadLetterHistory,
            deadLetterReason: $this->deadLetterReason,
            persistedEventStatus: $persistedEventStatus ?? $this->persistedEventStatus,
            replayStrategy: $replayStrategy,
        );
    }
}
