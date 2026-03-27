<?php

namespace App\Application\Health\Actions;

final class GetHealthStatusAction
{
    /**
     * @return array<string, string>
     */
    public function handle(): array
    {
        return [
            'service' => config('app.name'),
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
