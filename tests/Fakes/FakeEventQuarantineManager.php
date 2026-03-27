<?php

namespace Tests\Fakes;

use App\Application\Events\Contracts\EventQuarantineManager;
use App\Application\Events\DataTransferObjects\QuarantinedMessageData;
use App\Application\Events\DataTransferObjects\QuarantineInspectionData;
use App\Application\Events\DataTransferObjects\QuarantineReplayResultData;

final class FakeEventQuarantineManager implements EventQuarantineManager
{
    /**
     * @var list<QuarantinedMessageData>
     */
    public array $messages = [];

    /**
     * @var list<QuarantinedMessageData>
     */
    public array $replayMessages = [];

    public int $depth = 0;

    public ?string $replayFailure = null;

    public ?int $inspectedLimit = null;

    public ?int $replayedLimit = null;

    /**
     * @var array<int, string>
     */
    public array $replayedMessageIds = [];

    /**
     * @var array<int, string>
     */
    public array $missingReplayMessageIds = [];

    public function inspect(int $limit): QuarantineInspectionData
    {
        $this->inspectedLimit = $limit;

        return new QuarantineInspectionData(
            depth: $this->depth === 0 ? count($this->messages) : $this->depth,
            limit: $limit,
            messages: array_slice($this->messages, 0, $limit),
        );
    }

    public function replay(int $limit, array $messageIds = []): QuarantineReplayResultData
    {
        $this->replayedLimit = $limit;
        $this->replayedMessageIds = $messageIds;

        if ($this->replayFailure !== null) {
            return new QuarantineReplayResultData(
                requested: $limit,
                replayedCount: 0,
                remainingDepth: $this->depth === 0 ? count($this->messages) : $this->depth,
                messages: [],
                stoppedReason: $this->replayFailure,
            );
        }

        $availableMessages = $this->replayMessages !== [] ? $this->replayMessages : $this->messages;

        if ($messageIds !== []) {
            $messages = array_values(array_filter(
                $availableMessages,
                static fn (QuarantinedMessageData $message): bool => $message->messageId !== null
                    && in_array($message->messageId, $messageIds, true),
            ));
        } else {
            $messages = array_slice($availableMessages, 0, $limit);
        }

        return new QuarantineReplayResultData(
            requested: $limit,
            replayedCount: count($messages),
            remainingDepth: max(0, ($this->depth === 0 ? count($this->messages) : $this->depth) - count($messages)),
            messages: $messages,
            missingMessageIds: $this->missingReplayMessageIds,
        );
    }
}
