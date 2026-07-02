<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Requests\Library\BibliotecaReportRequest;
use App\Services\Library\BibliotecaReportService;
use Illuminate\Http\JsonResponse;

class BibliotecaReportController extends Controller
{
    public function __construct(
        private readonly BibliotecaReportService $reportService,
    ) {
    }

    public function __invoke(BibliotecaReportRequest $request): JsonResponse
    {
        return response()->json($this->reportService->generate($request->validated()));
    }
}
