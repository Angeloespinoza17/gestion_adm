<?php

namespace App\Http\Controllers;

use App\Http\Requests\MaintenanceReportRequest;
use App\Services\Maintenance\MaintenanceReportService;
use Illuminate\Http\JsonResponse;

class MaintenanceReportController extends Controller
{
    public function __construct(
        private readonly MaintenanceReportService $reportService,
    ) {
    }

    public function __invoke(MaintenanceReportRequest $request): JsonResponse
    {
        return response()->json($this->reportService->build($request->validated()));
    }
}
