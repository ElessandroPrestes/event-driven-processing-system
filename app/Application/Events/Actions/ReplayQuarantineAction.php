<?php

namespace App\Application\Events\Actions;

use App\Application\Events\Contracts\EventQuarantineManager;
use App\Application\Events\DataTransferObjects\QuarantineReplayResultData;

final class ReplayQuarantineAction
{
    public function __construct(
        private readonly EventQuarantineManager $quarantine,
    ) {}

    public function handle(int $limit): QuarantineReplayResultData
    {
        return $this->quarantine->replay($limit);
    }
}
