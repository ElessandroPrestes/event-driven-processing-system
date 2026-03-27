<?php

namespace App\Interfaces\Http\Controllers\Api\V1;

use App\Application\Events\Actions\ReceiveEventAction;
use App\Http\Controllers\Controller;
use App\Interfaces\Http\Requests\Api\V1\StoreEventRequest;
use App\Interfaces\Http\Resources\Api\V1\EventResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class StoreEventController extends Controller
{
    public function __invoke(StoreEventRequest $request, ReceiveEventAction $action): JsonResponse
    {
        $result = $action->handle($request->toEventPayloadData());

        return response()->json([
            'data' => EventResource::make($result->event)->resolve($request),
            'meta' => [
                'duplicate' => $result->duplicate,
            ],
        ], $result->duplicate ? Response::HTTP_OK : Response::HTTP_ACCEPTED);
    }
}
