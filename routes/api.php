<?php

use App\Interfaces\Http\Controllers\Api\V1\EventMetricsController;
use App\Interfaces\Http\Controllers\Api\V1\EventSummaryController;
use App\Interfaces\Http\Controllers\Api\V1\HealthCheckController;
use App\Interfaces\Http\Controllers\Api\V1\ListEventHistoryController;
use App\Interfaces\Http\Controllers\Api\V1\ListEventsController;
use App\Interfaces\Http\Controllers\Api\V1\ListQuarantinedMessagesController;
use App\Interfaces\Http\Controllers\Api\V1\ReplayQuarantinedMessagesController;
use App\Interfaces\Http\Controllers\Api\V1\RetryEventController;
use App\Interfaces\Http\Controllers\Api\V1\ShowEventController;
use App\Interfaces\Http\Controllers\Api\V1\StoreEventController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('event.pipeline.trace')->group(function (): void {
    Route::get('/health', HealthCheckController::class)
        ->name('api.v1.health');

    Route::middleware(['event.pipeline.rate_limit:ingest', 'event.pipeline.auth:ingest'])->group(function (): void {
        Route::post('/events', StoreEventController::class)
            ->name('api.v1.events.store');
    });

    Route::middleware(['event.pipeline.rate_limit:operations', 'event.pipeline.auth:operations'])->group(function (): void {
        Route::get('/metrics', EventMetricsController::class)
            ->name('api.v1.metrics');

        Route::get('/events/summary', EventSummaryController::class)
            ->name('api.v1.events.summary');

        Route::get('/events', ListEventsController::class)
            ->name('api.v1.events.index');

        Route::get('/quarantine', ListQuarantinedMessagesController::class)
            ->name('api.v1.quarantine.index');

        Route::post('/quarantine/replay', ReplayQuarantinedMessagesController::class)
            ->name('api.v1.quarantine.replay');

        Route::get('/events/{eventId}/history', ListEventHistoryController::class)
            ->whereUuid('eventId')
            ->name('api.v1.events.history');

        Route::post('/events/{eventId}/retry', RetryEventController::class)
            ->whereUuid('eventId')
            ->name('api.v1.events.retry');

        Route::get('/events/{eventId}', ShowEventController::class)
            ->whereUuid('eventId')
            ->name('api.v1.events.show');
    });
});
