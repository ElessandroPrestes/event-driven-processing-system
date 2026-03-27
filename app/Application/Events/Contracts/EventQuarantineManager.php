<?php

namespace App\Application\Events\Contracts;

use App\Application\Events\DataTransferObjects\QuarantineInspectionData;
use App\Application\Events\DataTransferObjects\QuarantineReplayResultData;

interface EventQuarantineManager
{
    public function inspect(int $limit): QuarantineInspectionData;

    public function replay(int $limit): QuarantineReplayResultData;
}
