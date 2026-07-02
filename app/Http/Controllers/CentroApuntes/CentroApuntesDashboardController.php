<?php

namespace App\Http\Controllers\CentroApuntes;

use App\Http\Controllers\Controller;
use App\Services\CentroApuntes\CentroApuntesAccessService;
use App\Services\CentroApuntes\CentroApuntesDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CentroApuntesDashboardController extends Controller
{
    public function __construct(
        private readonly CentroApuntesAccessService $accessService,
        private readonly CentroApuntesDashboardService $dashboardService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        return response()->json($this->dashboardService->build());
    }
}
