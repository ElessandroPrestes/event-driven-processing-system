<?php

namespace App\Application\Health\Contracts;

use App\Application\Health\DataTransferObjects\ComponentHealthData;

interface HealthProbe
{
    public function check(): ComponentHealthData;
}
