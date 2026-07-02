<?php

namespace App\Http\Controllers\ApoyoProfesional;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApoyoProfesional\ApoyoReportRequest;
use App\Services\ApoyoProfesional\ApoyoProfesionalAccessService;
use App\Services\ApoyoProfesional\ApoyoProfesionalReportService;
use Illuminate\Http\JsonResponse;

class ApoyoProfesionalReportController extends Controller
{
    public function __construct(
        private readonly ApoyoProfesionalAccessService $accessService,
        private readonly ApoyoProfesionalReportService $reportService,
    ) {
    }

    public function __invoke(ApoyoReportRequest $request): JsonResponse
    {
        abort_unless($this->accessService->canViewReports($request->user()) || $this->accessService->canViewModule($request->user()), 403);

        return response()->json(
            $this->reportService->build($request->user(), $request->validated())
        );
    }
}
