<?php

namespace App\Application\Events\DataTransferObjects;

final readonly class QuarantineReplayResultData
{
    /**
     * @param  array<int, QuarantinedMessageData>  $messages
     */
    public function __construct(
        public int $requested,
        public int $replayedCount,
        public int $remainingDepth,
        public array $messages,
        public ?string $stoppedReason = null,
    ) {}

    public function failed(): bool
    {
        return $this->replayedCount === 0 && $this->stoppedReason !== null;
    }
}
