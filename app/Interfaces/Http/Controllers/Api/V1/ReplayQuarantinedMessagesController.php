<?php

namespace App\Interfaces\Http\Controllers\Api\V1;

use App\Application\Events\Actions\ReplayQuarantineAction;
use App\Http\Controllers\Controller;
use App\Interfaces\Http\Requests\Api\V1\ReplayQuarantinedMessagesRequest;
use App\Interfaces\Http\Resources\Api\V1\QuarantinedMessageResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

final class ReplayQuarantinedMessagesController extends Controller
{
    public function __invoke(ReplayQuarantinedMessagesRequest $request, ReplayQuarantineAction $action): JsonResponse
    {
        $result = $action->handle($request->limit());
        $status = $result->failed() ? Response::HTTP_SERVICE_UNAVAILABLE : Response::HTTP_ACCEPTED;

        return response()->json([
            'data' => [
                'messages' => QuarantinedMessageResource::collection(Collection::make($result->messages))->resolve($request),
                'replayed_count' => $result->replayedCount,
                'requested' => $result->requested,
                'remaining_depth' => $result->remainingDepth,
                'stopped_reason' => $result->stoppedReason,
            ],
        ], $status);
    }
}
