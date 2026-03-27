<?php

use App\Interfaces\Http\Controllers\Api\V1\EventSummaryController;
use App\Interfaces\Http\Controllers\Api\V1\HealthCheckController;
use App\Interfaces\Http\Controllers\Api\V1\ListEventHistoryController;
use App\Interfaces\Http\Controllers\Api\V1\ListEventsController;
use App\Interfaces\Http\Controllers\Api\V1\RetryEventController;
use App\Interfaces\Http\Controllers\Api\V1\ShowEventController;
use App\Interfaces\Http\Controllers\Api\V1\StoreEventController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('event.pipeline.trace')->group(function (): void {
    Route::get('/health', HealthCheckController::class)
        ->name('api.v1.health');

    Route::post('/events', StoreEventController::class)
        ->middleware('event.pipeline.auth:ingest')
        ->name('api.v1.events.store');

    Route::get('/events/summary', EventSummaryController::class)
        ->middleware('event.pipeline.auth:operations')
        ->name('api.v1.events.summary');

    Route::get('/events', ListEventsController::class)
        ->middleware('event.pipeline.auth:operations')
        ->name('api.v1.events.index');

    Route::get('/events/{eventId}/history', ListEventHistoryController::class)
        ->whereUuid('eventId')
        ->middleware('event.pipeline.auth:operations')
        ->name('api.v1.events.history');

    Route::post('/events/{eventId}/retry', RetryEventController::class)
        ->whereUuid('eventId')
        ->middleware('event.pipeline.auth:operations')
        ->name('api.v1.events.retry');

    Route::get('/events/{eventId}', ShowEventController::class)
        ->whereUuid('eventId')
        ->middleware('event.pipeline.auth:operations')
        ->name('api.v1.events.show');
});
