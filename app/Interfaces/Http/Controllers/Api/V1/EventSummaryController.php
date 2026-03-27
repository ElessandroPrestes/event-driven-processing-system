<?php

namespace App\Interfaces\Http\Controllers\Api\V1;

use App\Application\Events\Actions\GetEventSummaryAction;
use App\Http\Controllers\Controller;
use App\Interfaces\Http\Requests\Api\V1\EventSummaryRequest;
use App\Interfaces\Http\Resources\Api\V1\EventSummaryResource;
use Illuminate\Http\JsonResponse;

final class EventSummaryController extends Controller
{
    public function __invoke(EventSummaryRequest $request, GetEventSummaryAction $action): JsonResponse
    {
        return response()->json([
            'data' => EventSummaryResource::make($action->handle($request->eventName()))->resolve($request),
        ]);
    }
}
