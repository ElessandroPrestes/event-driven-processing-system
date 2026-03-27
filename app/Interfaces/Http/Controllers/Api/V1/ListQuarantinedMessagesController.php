<?php

namespace App\Interfaces\Http\Controllers\Api\V1;

use App\Application\Events\Actions\InspectQuarantineAction;
use App\Http\Controllers\Controller;
use App\Interfaces\Http\Requests\Api\V1\ListQuarantinedMessagesRequest;
use App\Interfaces\Http\Resources\Api\V1\QuarantinedMessageResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

final class ListQuarantinedMessagesController extends Controller
{
    public function __invoke(ListQuarantinedMessagesRequest $request, InspectQuarantineAction $action): JsonResponse
    {
        $inspection = $action->handle($request->limit());

        return response()->json([
            'data' => QuarantinedMessageResource::collection(Collection::make($inspection->messages))->resolve($request),
            'meta' => [
                'count' => count($inspection->messages),
                'limit' => $inspection->limit,
                'depth' => $inspection->depth,
            ],
        ]);
    }
}
