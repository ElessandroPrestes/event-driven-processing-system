<?php

namespace App\Providers;

use App\Application\Events\Contracts\EventQuarantineManager;
use App\Application\Events\Contracts\EventRetryScheduler;
use App\Application\Events\Processors\InvoiceGeneratedProcessor;
use App\Application\Events\Processors\NotificationRequestedProcessor;
use App\Application\Events\Processors\PaymentReceivedProcessor;
use App\Application\Events\Processors\UserCreatedProcessor;
use App\Application\Events\Services\EventHistoryRecorder;
use App\Application\Events\Services\EventProcessorRegistry;
use App\Application\Events\Services\EventRetryDelayCalculator;
use App\Application\Health\Services\HealthProbeRegistry;
use App\Domain\Events\Contracts\EventHistoryRepository;
use App\Domain\Events\Contracts\EventPublisher;
use App\Domain\Events\Contracts\EventRepository;
use App\Infrastructure\Health\DatabaseHealthProbe;
use App\Infrastructure\Health\RabbitMqHealthProbe;
use App\Infrastructure\Health\RedisHealthProbe;
use App\Infrastructure\Messaging\RabbitMq\Contracts\AmqpConnectionFactory;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqConnectionFactory;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqDelayedRetryScheduler;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqEventPublisher;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqEventQuarantine;
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
        $this->app->bind(AmqpConnectionFactory::class, RabbitMqConnectionFactory::class);
        $this->app->bind(EventPublisher::class, RabbitMqEventPublisher::class);
        $this->app->bind(EventRetryScheduler::class, RabbitMqDelayedRetryScheduler::class);
        $this->app->bind(EventQuarantineManager::class, RabbitMqEventQuarantine::class);
        $this->app->bind(EventRepository::class, EloquentEventRepository::class);
        $this->app->bind(EventHistoryRepository::class, EloquentEventHistoryRepository::class);
        $this->app->singleton(EventHistoryRecorder::class, EventHistoryRecorder::class);
        $this->app->singleton(EventRetryDelayCalculator::class, EventRetryDelayCalculator::class);
        $this->app->singleton(HealthProbeRegistry::class, function ($app): HealthProbeRegistry {
            return new HealthProbeRegistry([
                $app->make(DatabaseHealthProbe::class),
                $app->make(RedisHealthProbe::class),
                $app->make(RabbitMqHealthProbe::class),
            ]);
        });
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
