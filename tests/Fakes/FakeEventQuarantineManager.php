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

    public function inspect(int $limit): QuarantineInspectionData
    {
        $this->inspectedLimit = $limit;

        return new QuarantineInspectionData(
            depth: $this->depth === 0 ? count($this->messages) : $this->depth,
            limit: $limit,
            messages: array_slice($this->messages, 0, $limit),
        );
    }

    public function replay(int $limit): QuarantineReplayResultData
    {
        $this->replayedLimit = $limit;

        if ($this->replayFailure !== null) {
            return new QuarantineReplayResultData(
                requested: $limit,
                replayedCount: 0,
                remainingDepth: $this->depth === 0 ? count($this->messages) : $this->depth,
                messages: [],
                stoppedReason: $this->replayFailure,
            );
        }

        $messages = $this->replayMessages !== []
            ? array_slice($this->replayMessages, 0, $limit)
            : array_slice($this->messages, 0, $limit);

        return new QuarantineReplayResultData(
            requested: $limit,
            replayedCount: count($messages),
            remainingDepth: max(0, ($this->depth === 0 ? count($this->messages) : $this->depth) - count($messages)),
            messages: $messages,
        );
    }
}
