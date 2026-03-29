<?php

namespace App\Interfaces\Http\Controllers\Api\V1;

use App\Application\Health\Actions\GetHealthStatusAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class HealthCheckController extends Controller
{
    public function __invoke(GetHealthStatusAction $action): JsonResponse
    {
        $payload = $action->handle();

        return response()->json([
            'data' => $payload,
        ], $payload['ready'] ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
