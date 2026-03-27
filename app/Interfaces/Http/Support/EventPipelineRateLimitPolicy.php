<?php

namespace App\Interfaces\Http\Support;

use Illuminate\Http\Request;

final readonly class EventPipelineRateLimitPolicy
{
    public function __construct(
        private string $scope,
        private int $maxAttempts,
        private int $decaySeconds,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(string $scope, array $config): ?self
    {
        $maxAttempts = $config['max_attempts'] ?? null;
        $decaySeconds = $config['decay_seconds'] ?? null;

        if (! is_int($maxAttempts) || $maxAttempts < 1 || ! is_int($decaySeconds) || $decaySeconds < 1) {
            return null;
        }

        return new self(
            scope: $scope,
            maxAttempts: $maxAttempts,
            decaySeconds: $decaySeconds,
        );
    }

    public function scope(): string
    {
        return $this->scope;
    }

    public function maxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function decaySeconds(): int
    {
        return $this->decaySeconds;
    }

    public function keyFor(Request $request): string
    {
        return sprintf(
            'event_pipeline:rate_limit:%s:%s',
            $this->scope,
            sha1((string) ($request->ip() ?? 'unknown')),
        );
    }

    /**
     * @return array<string, string>
     */
    public function successHeaders(int $remainingAttempts): array
    {
        return [
            'X-RateLimit-Limit' => (string) $this->maxAttempts,
            'X-RateLimit-Remaining' => (string) max($remainingAttempts, 0),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function limitedHeaders(int $retryAfterSeconds): array
    {
        return [
            'Retry-After' => (string) max($retryAfterSeconds, 1),
            'X-RateLimit-Limit' => (string) $this->maxAttempts,
            'X-RateLimit-Remaining' => '0',
        ];
    }

    /**
     * @return array<string, int|string>
     */
    public function limitedPayload(int $retryAfterSeconds): array
    {
        return [
            'scope' => $this->scope,
            'limit' => $this->maxAttempts,
            'retry_after_seconds' => max($retryAfterSeconds, 1),
        ];
    }
}
