<?php

namespace App\Providers;

use App\Domain\Events\Contracts\EventPublisher;
use App\Domain\Events\Contracts\EventRepository;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqEventPublisher;
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
        $this->app->bind(EventRepository::class, EloquentEventRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
