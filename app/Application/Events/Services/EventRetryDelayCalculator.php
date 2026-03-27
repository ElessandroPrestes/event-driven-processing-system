<?php

namespace App\Application\Events\Services;

final class EventRetryDelayCalculator
{
    public function forAttempt(int $attempt): int
    {
        $baseDelay = max(1, (int) config('event_pipeline.consumer.retry_base_delay_ms', 5000));
        $multiplier = max(1.0, (float) config('event_pipeline.consumer.retry_multiplier', 2.0));
        $maxDelay = max($baseDelay, (int) config('event_pipeline.consumer.retry_max_delay_ms', 60000));
        $attemptIndex = max(0, $attempt - 1);
        $delay = (int) round($baseDelay * ($multiplier ** $attemptIndex));

        return min($delay, $maxDelay);
    }
}
