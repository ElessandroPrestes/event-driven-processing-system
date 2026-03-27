<?php

namespace App\Interfaces\Http\Controllers\Api\V1;

use App\Application\Health\Actions\GetHealthStatusAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class HealthCheckController extends Controller
{
    public function __invoke(GetHealthStatusAction $action): JsonResponse
    {
        return response()->json([
            'data' => $action->handle(),
        ]);
    }
}
