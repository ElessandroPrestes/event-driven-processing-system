<?php

use App\Interfaces\Http\Controllers\Web\ShowApiDocumentationController;
use App\Interfaces\Http\Controllers\Web\ShowOpenApiSpecificationController;
use Illuminate\Support\Facades\Route;

Route::get('/docs', ShowApiDocumentationController::class)
    ->name('docs.index');

Route::get('/docs/openapi.yaml', ShowOpenApiSpecificationController::class)
    ->name('docs.openapi');

Route::redirect('/', '/api/v1/health');
