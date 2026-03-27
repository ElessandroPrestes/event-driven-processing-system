<?php

namespace App\Interfaces\Http\Controllers\Api\V1;

use App\Application\Events\Actions\ShowEventAction;
use App\Http\Controllers\Controller;
use App\Interfaces\Http\Resources\Api\V1\EventResource;
use Illuminate\Http\JsonResponse;

final class ShowEventController extends Controller
{
    public function __invoke(string $eventId, ShowEventAction $action): JsonResponse
    {
        return response()->json([
            'data' => EventResource::make($action->handle($eventId))->resolve(),
        ]);
    }
}
