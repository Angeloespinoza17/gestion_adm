<?php

namespace App\Http\Controllers\Informatica;

use App\Http\Controllers\Controller;
use App\Services\Informatica\InformaticaAccessService;
use App\Services\Informatica\InformaticaDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InformaticaDashboardController extends Controller
{
    public function __construct(
        private readonly InformaticaDashboardService $dashboardService,
        private readonly InformaticaAccessService $accessService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewDashboard($request->user()), 403);

        return response()->json($this->dashboardService->build());
    }
}
