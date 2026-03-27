<?php

namespace App\Interfaces\Http\Controllers\Api\V1;

use App\Application\Events\Actions\ListEventHistoryAction;
use App\Http\Controllers\Controller;
use App\Interfaces\Http\Resources\Api\V1\EventHistoryEntryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class ListEventHistoryController extends Controller
{
    public function __invoke(string $eventId, Request $request, ListEventHistoryAction $action): JsonResponse
    {
        $entries = $action->handle($eventId);

        return response()->json([
            'data' => EventHistoryEntryResource::collection(Collection::make($entries))->resolve($request),
            'meta' => [
                'count' => count($entries),
            ],
        ]);
    }
}
