<?php

namespace App\Application\Health\Services;

use App\Application\Health\Contracts\HealthProbe;

final readonly class HealthProbeRegistry
{
    /**
     * @param  array<int, HealthProbe>  $probes
     */
    public function __construct(
        private array $probes,
    ) {}

    /**
     * @return array<int, HealthProbe>
     */
    public function all(): array
    {
        return $this->probes;
    }
}
