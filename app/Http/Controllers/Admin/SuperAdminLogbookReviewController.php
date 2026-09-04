<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewSuperAdminLogbooksRequest;
use App\Services\Admin\SuperAdminLogbookReviewService;
use Illuminate\Http\JsonResponse;

class SuperAdminLogbookReviewController extends Controller
{
    public function __construct(
        private readonly SuperAdminLogbookReviewService $reviewService,
    ) {}

    public function index(ReviewSuperAdminLogbooksRequest $request): JsonResponse
    {
        return response()->json($this->reviewService->review($request->validated()));
    }
}
