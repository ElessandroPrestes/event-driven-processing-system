<?php

use App\Interfaces\Http\Controllers\Api\V1\HealthCheckController;
use App\Interfaces\Http\Controllers\Api\V1\ShowEventController;
use App\Interfaces\Http\Controllers\Api\V1\StoreEventController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', HealthCheckController::class)
        ->name('api.v1.health');

    Route::post('/events', StoreEventController::class)
        ->name('api.v1.events.store');

    Route::get('/events/{eventId}', ShowEventController::class)
        ->whereUuid('eventId')
        ->name('api.v1.events.show');
});
