<?php

namespace App\Http\Controllers;

use App\Services\HomeDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeDashboardController extends Controller
{
    public function __invoke(Request $request, HomeDashboardService $service): JsonResponse
    {
        return response()->json($service->build($request->user()));
    }
}
