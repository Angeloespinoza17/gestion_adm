<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Services\Library\BibliotecaDashboardService;
use Illuminate\Http\JsonResponse;

class BibliotecaDashboardController extends Controller
{
    public function __construct(
        private readonly BibliotecaDashboardService $dashboardService,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        return response()->json($this->dashboardService->build());
    }
}
