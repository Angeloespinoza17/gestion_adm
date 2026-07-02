<?php

namespace App\Http\Controllers\Pme;

use App\Http\Controllers\Controller;
use App\Services\Pme\PmeAccessService;
use App\Services\Pme\PmeDashboardService;
use Illuminate\Http\JsonResponse;

class PmeDashboardController extends Controller
{
    public function __construct(
        private readonly PmeAccessService $accessService,
        private readonly PmeDashboardService $dashboardService,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        abort_unless($this->accessService->canViewModule(request()->user()), 403);

        return response()->json($this->dashboardService->build());
    }
}
