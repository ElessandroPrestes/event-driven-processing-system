<?php

namespace App\Infrastructure\Messaging\RabbitMq;

use App\Application\Events\DataTransferObjects\QuarantinedMessageData;
use PhpAmqpLib\Message\AMQPMessage;

final class QuarantineReplayBatch
{
    /**
     * @param  array<int, string>  $targetMessageIds
     * @param  array<string, bool>  $pendingMessageIds
     * @param  array<int, QuarantinedMessageData>  $replayed
     * @param  array<int, AMQPMessage>  $deferredMessages
     */
    public function __construct(
        public readonly int $requested,
        public readonly array $targetMessageIds,
        public readonly array $pendingMessageIds = [],
        public readonly array $replayed = [],
        public readonly array $deferredMessages = [],
        public readonly ?string $stoppedReason = null,
    ) {}

    /**
     * @param  array<int, string>  $targetMessageIds
     */
    public static function start(int $requested, array $targetMessageIds): self
    {
        /** @var array<string, bool> $pendingMessageIds */
        $pendingMessageIds = array_fill_keys($targetMessageIds, true);

        return new self(
            requested: $requested,
            targetMessageIds: $targetMessageIds,
            pendingMessageIds: $pendingMessageIds,
        );
    }

    public function iterations(int $queueDepth): int
    {
        return $this->targetMessageIds === []
            ? min($this->requested, $queueDepth)
            : $queueDepth;
    }

    public function isTargeted(): bool
    {
        return $this->targetMessageIds !== [];
    }

    public function shouldStop(): bool
    {
        return $this->stoppedReason !== null
            || ($this->isTargeted() && $this->pendingMessageIds === []);
    }

    public function shouldDefer(?string $messageId): bool
    {
        if ($this->pendingMessageIds === []) {
            return false;
        }

        if ($messageId === null) {
            return true;
        }

        return ! array_key_exists($messageId, $this->pendingMessageIds);
    }

    public function withDeferredMessage(AMQPMessage $message): self
    {
        return new self(
            requested: $this->requested,
            targetMessageIds: $this->targetMessageIds,
            pendingMessageIds: $this->pendingMessageIds,
            replayed: $this->replayed,
            deferredMessages: [...$this->deferredMessages, $message],
            stoppedReason: $this->stoppedReason,
        );
    }

    public function withReplayedMessage(QuarantinedMessageData $message): self
    {
        $pendingMessageIds = $this->pendingMessageIds;

        if ($message->messageId !== null) {
            unset($pendingMessageIds[$message->messageId]);
        }

        return new self(
            requested: $this->requested,
            targetMessageIds: $this->targetMessageIds,
            pendingMessageIds: $pendingMessageIds,
            replayed: [...$this->replayed, $message],
            deferredMessages: $this->deferredMessages,
            stoppedReason: $this->stoppedReason,
        );
    }

    public function withStoppedReason(string $stoppedReason): self
    {
        return new self(
            requested: $this->requested,
            targetMessageIds: $this->targetMessageIds,
            pendingMessageIds: $this->pendingMessageIds,
            replayed: $this->replayed,
            deferredMessages: $this->deferredMessages,
            stoppedReason: $stoppedReason,
        );
    }

    /**
     * @return array<int, string>
     */
    public function missingMessageIds(): array
    {
        return array_keys($this->pendingMessageIds);
    }
}
