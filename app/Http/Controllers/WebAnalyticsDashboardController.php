<?php

namespace App\Http\Controllers;

use App\Http\Requests\WebAnalyticsDashboardRequest;
use App\Services\PublicSite\WebAnalyticsDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class WebAnalyticsDashboardController extends Controller
{
    public function index(
        WebAnalyticsDashboardRequest $request,
        WebAnalyticsDashboardService $analytics,
    ): JsonResponse {
        $filters = $request->validated();

        return response()->json($analytics->overview(
            Carbon::createFromFormat('Y-m-d', $filters['from'])->startOfDay(),
            Carbon::createFromFormat('Y-m-d', $filters['to'])->startOfDay(),
            $filters['content_type'] ?? null,
        ));
    }
}
