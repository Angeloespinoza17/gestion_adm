<?php

namespace App\Http\Controllers\Orientation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Orientation\SaveOrientationPlanRequest;
use App\Models\Orientation\OrientationPlan;
use App\Services\Orientation\OrientationWorkspaceService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrientationPlanController extends Controller
{
    public function __construct(
        private readonly OrientationWorkspaceService $workspace,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'between:2020,2100'],
        ]);
        $year = (int) ($validated['year'] ?? today()->year);
        $plan = $this->workspace->loadPlan($year);
        $user = $request->user();

        return response()->json([
            'selected_year' => $year,
            'available_years' => OrientationPlan::query()->orderByDesc('year')->pluck('year')->map(fn ($value) => (int) $value)->values(),
            'data' => $plan ? $this->workspace->serializePlan($plan) : null,
            'responsible_users' => $this->workspace->responsibleUsers(),
            'status_options' => $this->workspace->statusOptions(),
            'capabilities' => [
                'can_view' => (bool) $user?->hasPermission('orientation.view'),
                'can_manage_plan' => (bool) $user?->hasPermission('orientation.manage_plan'),
                'can_manage_execution' => (bool) $user?->hasPermission('orientation.manage_execution'),
                'can_manage_evidence' => (bool) $user?->hasPermission('orientation.manage_evidence'),
            ],
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
    }

    public function store(SaveOrientationPlanRequest $request): JsonResponse
    {
        try {
            $plan = DB::transaction(function () use ($request): OrientationPlan {
                $payload = $request->validated();
                $payload['created_by'] = $request->user()?->id;
                $payload['updated_by'] = $request->user()?->id;

                return OrientationPlan::query()->create($payload);
            });
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                throw ValidationException::withMessages([
                    'year' => ['Ya existe un Plan de Orientación para este año.'],
                ]);
            }

            throw $exception;
        }

        return response()->json([
            'message' => "Plan de Orientación {$plan->year} creado correctamente.",
            'data' => $this->workspace->serializePlan($this->workspace->loadPlan((int) $plan->year)),
        ], 201);
    }

    public function update(SaveOrientationPlanRequest $request, OrientationPlan $plan): JsonResponse
    {
        $payload = $request->validated();
        $payload['updated_by'] = $request->user()?->id;
        $plan->update($payload);

        return response()->json([
            'message' => 'Plan anual actualizado correctamente.',
            'data' => $this->workspace->serializePlan($this->workspace->loadPlan((int) $plan->year)),
        ]);
    }
}
