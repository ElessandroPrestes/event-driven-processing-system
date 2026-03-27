<?php

namespace App\Application\Events\Actions;

use App\Application\Events\Contracts\EventQuarantineManager;
use App\Application\Events\DataTransferObjects\QuarantineInspectionData;

final class InspectQuarantineAction
{
    public function __construct(
        private readonly EventQuarantineManager $quarantine,
    ) {}

    public function handle(int $limit): QuarantineInspectionData
    {
        return $this->quarantine->inspect($limit);
    }
}
