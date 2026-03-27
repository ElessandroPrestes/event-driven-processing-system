<?php

namespace App\Interfaces\Http\Controllers\Api\V1;

use App\Application\Events\Actions\ListEventsAction;
use App\Http\Controllers\Controller;
use App\Interfaces\Http\Requests\Api\V1\ListEventsRequest;
use App\Interfaces\Http\Resources\Api\V1\EventResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

final class ListEventsController extends Controller
{
    public function __invoke(ListEventsRequest $request, ListEventsAction $action): JsonResponse
    {
        $events = $action->handle($request->statuses(), $request->eventName());

        return response()->json([
            'data' => EventResource::collection(Collection::make($events))->resolve($request),
            'meta' => [
                'count' => count($events),
            ],
        ]);
    }
}
