<?php

namespace App\Http\Controllers\CentroApuntes;

use App\Http\Controllers\Controller;
use App\Http\Requests\CentroApuntes\CentroApuntesReportRequest;
use App\Services\CentroApuntes\CentroApuntesAccessService;
use App\Services\CentroApuntes\CentroApuntesReportService;
use Illuminate\Http\JsonResponse;

class CentroApuntesReportController extends Controller
{
    public function __construct(
        private readonly CentroApuntesAccessService $accessService,
        private readonly CentroApuntesReportService $reportService,
    ) {
    }

    public function __invoke(CentroApuntesReportRequest $request): JsonResponse
    {
        abort_unless($this->accessService->canViewReports($request->user()), 403);

        return response()->json($this->reportService->build($request->validated()));
    }
}
