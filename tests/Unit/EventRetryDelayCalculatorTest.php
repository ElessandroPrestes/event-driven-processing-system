<?php

use App\Application\Events\Services\EventRetryDelayCalculator;

it('calculates an exponential retry backoff capped by the configured maximum', function (): void {
    config()->set('event_pipeline.consumer.retry_base_delay_ms', 5000);
    config()->set('event_pipeline.consumer.retry_multiplier', 2.0);
    config()->set('event_pipeline.consumer.retry_max_delay_ms', 20000);

    $calculator = new EventRetryDelayCalculator;

    expect($calculator->forAttempt(1))->toBe(5000)
        ->and($calculator->forAttempt(2))->toBe(10000)
        ->and($calculator->forAttempt(3))->toBe(20000)
        ->and($calculator->forAttempt(4))->toBe(20000);
});
