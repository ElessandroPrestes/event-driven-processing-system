<?php

namespace App\Application\Events\Contracts;

use App\Application\Events\DataTransferObjects\QuarantineInspectionData;
use App\Application\Events\DataTransferObjects\QuarantineReplayResultData;

interface EventQuarantineManager
{
    public function inspect(int $limit): QuarantineInspectionData;

    /**
     * @param  array<int, string>  $messageIds
     */
    public function replay(int $limit, array $messageIds = []): QuarantineReplayResultData;
}
