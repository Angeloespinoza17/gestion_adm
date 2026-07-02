<?php

namespace App\Http\Controllers\ApoyoProfesional;

use App\Http\Controllers\Controller;
use App\Services\ApoyoProfesional\ApoyoProfesionalAccessService;
use App\Services\ApoyoProfesional\ApoyoProfesionalDashboardService;
use Illuminate\Http\JsonResponse;

class ApoyoProfesionalDashboardController extends Controller
{
    public function __construct(
        private readonly ApoyoProfesionalAccessService $accessService,
        private readonly ApoyoProfesionalDashboardService $dashboardService,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        abort_unless($this->accessService->canViewModule(request()->user()), 403);

        return response()->json($this->dashboardService->build(request()->user()));
    }
}
