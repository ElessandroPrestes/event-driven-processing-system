<?php

namespace App\Application\Health\DataTransferObjects;

use Carbon\CarbonImmutable;

final readonly class ComponentHealthData
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public string $name,
        public string $status,
        public CarbonImmutable $observedAt,
        public ?string $message = null,
        public array $details = [],
    ) {}

    public function healthy(): bool
    {
        return $this->status === 'ok';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'status' => $this->status,
            'observed_at' => $this->observedAt->toIso8601String(),
        ];

        if ($this->message !== null) {
            $payload['message'] = $this->message;
        }

        if ($this->details !== []) {
            $payload['details'] = $this->details;
        }

        return $payload;
    }
}
