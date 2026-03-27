<?php

namespace App\Interfaces\Http\Controllers\Api\V1;

use App\Application\Events\Actions\ListEventHistoryAction;
use App\Http\Controllers\Controller;
use App\Interfaces\Http\Requests\Api\V1\ListEventHistoryRequest;
use App\Interfaces\Http\Resources\Api\V1\EventHistoryEntryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

final class ListEventHistoryController extends Controller
{
    public function __invoke(
        string $eventId,
        ListEventHistoryRequest $request,
        ListEventHistoryAction $action,
    ): JsonResponse {
        $entries = $action->handle($eventId, $request->currentPage(), $request->perPage());

        return response()->json([
            'data' => EventHistoryEntryResource::collection(Collection::make($entries->items))->resolve($request),
            'meta' => [
                'count' => $entries->count(),
                'current_page' => $entries->currentPage,
                'per_page' => $entries->perPage,
                'total' => $entries->total,
                'last_page' => $entries->lastPage,
                'has_more_pages' => $entries->hasMorePages(),
            ],
        ]);
    }
}
