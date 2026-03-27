<?php

namespace App\Interfaces\Http\Controllers\Api\V1;

use App\Application\Events\Actions\RetryEventAction;
use App\Http\Controllers\Controller;
use App\Interfaces\Http\Resources\Api\V1\EventResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RetryEventController extends Controller
{
    public function __invoke(string $eventId, Request $request, RetryEventAction $action): JsonResponse
    {
        return response()->json([
            'data' => EventResource::make($action->handle($eventId))->resolve($request),
        ], Response::HTTP_ACCEPTED);
    }
}
