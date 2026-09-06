<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWebAnalyticsPageViewRequest;
use App\Http\Requests\UpdateWebAnalyticsEngagementRequest;
use App\Services\PublicSite\WebAnalyticsTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PublicWebAnalyticsController extends Controller
{
    public function store(
        StoreWebAnalyticsPageViewRequest $request,
        WebAnalyticsTrackingService $tracking,
    ): JsonResponse|Response {
        $view = $tracking->recordPageView($request, $request->validated());

        if (! $view) {
            return response()->noContent();
        }

        return response()->json(['visit_id' => $view->public_id], 201);
    }

    public function engagement(
        UpdateWebAnalyticsEngagementRequest $request,
        WebAnalyticsTrackingService $tracking,
    ): Response {
        $tracking->recordEngagement($request, $request->validated());

        return response()->noContent();
    }
}
