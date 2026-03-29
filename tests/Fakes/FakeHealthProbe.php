<?php

namespace Tests\Fakes;

use App\Application\Health\Contracts\HealthProbe;
use App\Application\Health\DataTransferObjects\ComponentHealthData;
use Carbon\CarbonImmutable;

final readonly class FakeHealthProbe implements HealthProbe
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        private string $name,
        private string $status = 'ok',
        private ?string $message = null,
        private array $details = [],
        private ?CarbonImmutable $observedAt = null,
    ) {}

    public function check(): ComponentHealthData
    {
        return new ComponentHealthData(
            name: $this->name,
            status: $this->status,
            observedAt: $this->observedAt ?? CarbonImmutable::now(),
            message: $this->message,
            details: $this->details,
        );
    }
}
