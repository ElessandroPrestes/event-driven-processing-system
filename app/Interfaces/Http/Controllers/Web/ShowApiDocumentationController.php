<?php

namespace App\Interfaces\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

final class ShowApiDocumentationController extends Controller
{
    public function __invoke(): View
    {
        return view('docs.index', [
            'openApiUrl' => route('docs.openapi', absolute: false),
        ]);
    }
}
