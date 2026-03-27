<?php

namespace App\Providers;

use App\Application\Events\Contracts\EventRetryScheduler;
use App\Application\Events\Processors\InvoiceGeneratedProcessor;
use App\Application\Events\Processors\NotificationRequestedProcessor;
use App\Application\Events\Processors\PaymentReceivedProcessor;
use App\Application\Events\Processors\UserCreatedProcessor;
use App\Application\Events\Services\EventHistoryRecorder;
use App\Application\Events\Services\EventProcessorRegistry;
use App\Application\Events\Services\EventRetryDelayCalculator;
use App\Domain\Events\Contracts\EventHistoryRepository;
use App\Domain\Events\Contracts\EventPublisher;
use App\Domain\Events\Contracts\EventRepository;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqDelayedRetryScheduler;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqEventPublisher;
use App\Infrastructure\Persistence\Repositories\EloquentEventHistoryRepository;
use App\Infrastructure\Persistence\Repositories\EloquentEventRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(EventPublisher::class, RabbitMqEventPublisher::class);
        $this->app->bind(EventRetryScheduler::class, RabbitMqDelayedRetryScheduler::class);
        $this->app->bind(EventRepository::class, EloquentEventRepository::class);
        $this->app->bind(EventHistoryRepository::class, EloquentEventHistoryRepository::class);
        $this->app->singleton(EventHistoryRecorder::class, EventHistoryRecorder::class);
        $this->app->singleton(EventRetryDelayCalculator::class, EventRetryDelayCalculator::class);
        $this->app->singleton(EventProcessorRegistry::class, function ($app): EventProcessorRegistry {
            return new EventProcessorRegistry([
                $app->make(UserCreatedProcessor::class),
                $app->make(PaymentReceivedProcessor::class),
                $app->make(InvoiceGeneratedProcessor::class),
                $app->make(NotificationRequestedProcessor::class),
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
