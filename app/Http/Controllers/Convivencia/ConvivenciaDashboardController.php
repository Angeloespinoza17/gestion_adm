<?php

namespace App\Http\Controllers\Convivencia;

use App\Http\Controllers\Controller;
use App\Services\Convivencia\ConvivenciaAccessService;
use App\Services\Convivencia\ConvivenciaDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConvivenciaDashboardController extends Controller
{
    public function __construct(
        private readonly ConvivenciaAccessService $accessService,
        private readonly ConvivenciaDashboardService $dashboardService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewDashboard($request->user()), 403);

        return response()->json($this->dashboardService->build($request->user(), $request->all()));
    }
}
