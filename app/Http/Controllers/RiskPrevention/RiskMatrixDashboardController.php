<?php

namespace App\Http\Controllers\RiskPrevention;

use App\Http\Controllers\Controller;
use App\Services\RiskPrevention\RiskMatrixStatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RiskMatrixDashboardController extends Controller
{
    public function __invoke(Request $request, RiskMatrixStatisticsService $statistics): JsonResponse
    {
        abort_unless($request->user()->hasPermission('risk-matrix.view'), 403);

        return response()->json(['data' => $statistics->dashboard()]);
    }
}
