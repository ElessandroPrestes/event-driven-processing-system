<?php

namespace App\Interfaces\Http\Controllers\Api\V1;

use App\Application\Events\Actions\ExportEventMetricsAction;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\Response;

final class EventMetricsController extends Controller
{
    public function __invoke(ExportEventMetricsAction $action): Response
    {
        return response($action->handle(), Response::HTTP_OK, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);
    }
}
