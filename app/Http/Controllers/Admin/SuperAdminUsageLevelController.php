<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SuperAdminUsageLevelRequest;
use App\Models\User;
use App\Services\Admin\SuperAdminUsageLevelService;
use Illuminate\Http\JsonResponse;

class SuperAdminUsageLevelController extends Controller
{
    public function __construct(
        private readonly SuperAdminUsageLevelService $usageLevelService,
    ) {}

    public function index(SuperAdminUsageLevelRequest $request): JsonResponse
    {
        return response()->json($this->usageLevelService->index($request->validated()));
    }

    public function show(SuperAdminUsageLevelRequest $request, User $user): JsonResponse
    {
        return response()->json($this->usageLevelService->detail($user, $request->validated()));
    }
}
