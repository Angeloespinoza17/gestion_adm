<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Http\Requests\Students\PromoteStudentsRequest;
use App\Services\Students\StudentPromotionService;
use Illuminate\Http\JsonResponse;

class StudentPromotionController extends Controller
{
    public function __construct(
        private readonly StudentPromotionService $studentPromotionService,
    ) {
    }

    public function store(PromoteStudentsRequest $request): JsonResponse
    {
        $summary = $this->studentPromotionService->promote($request->validated(), $request->user());

        return response()->json([
            'message' => 'Promoción anual ejecutada correctamente.',
            'data' => $summary,
        ]);
    }
}
