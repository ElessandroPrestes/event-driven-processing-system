<?php

namespace App\Interfaces\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

final class ShowOpenApiSpecificationController extends Controller
{
    public function __invoke(): Response
    {
        return response(
            content: file_get_contents(base_path('docs/openapi.yaml')) ?: '',
            status: Response::HTTP_OK,
            headers: [
                'Content-Type' => 'application/yaml; charset=UTF-8',
                'Content-Disposition' => 'inline; filename="openapi.yaml"',
            ],
        );
    }
}
