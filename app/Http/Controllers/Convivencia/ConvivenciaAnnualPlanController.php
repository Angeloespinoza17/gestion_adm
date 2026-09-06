<?php

namespace App\Http\Controllers\Convivencia;

use App\Http\Controllers\Controller;
use App\Models\Convivencia\ConvivenciaCatalogItem;
use App\Models\Convivencia\ConvivenciaPlan;
use App\Models\Convivencia\ConvivenciaPlanAction;
use App\Models\Convivencia\ConvivenciaPlanActivity;
use App\Services\Convivencia\ConvivenciaAccessService;
use App\Services\Convivencia\ConvivenciaAnnualPlanWorkspaceService;
use App\Services\Convivencia\ConvivenciaPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConvivenciaAnnualPlanController extends Controller
{
    public function __construct(
        private readonly ConvivenciaAnnualPlanWorkspaceService $workspace,
        private readonly ConvivenciaAccessService $accessService,
        private readonly ConvivenciaPlanService $planService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ConvivenciaPlan::class);
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'between:2020,2100'],
        ]);
        $year = (int) ($validated['year'] ?? today()->year);
        $user = $request->user();
        $plan = $this->workspace->loadByYear($year, $user);
        $years = $this->accessService->applyPlanVisibility(ConvivenciaPlan::query(), $user)
            ->whereNotNull('calendar_year')
            ->orderByDesc('calendar_year')
            ->pluck('calendar_year')
            ->map(fn ($value) => (int) $value)
            ->values();

        return response()->json([
            'selected_year' => $year,
            'available_years' => $years,
            'data' => $plan ? $this->workspace->serializePlan($plan) : null,
            'status_options' => [
                'plans' => ConvivenciaPlan::STATUS_OPTIONS,
                'actions' => ConvivenciaPlanAction::STATUS_OPTIONS,
                'activities' => ConvivenciaPlanActivity::STATUS_OPTIONS,
                'action_types' => ConvivenciaPlanAction::TYPE_OPTIONS,
                'activity_types' => ConvivenciaCatalogItem::query()
                    ->where('group', ConvivenciaPlanActivity::TYPE_GROUP)
                    ->where('active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(['id', 'code', 'name', 'color', 'metadata']),
            ],
            'capabilities' => [
                'can_view' => true,
                'can_manage' => $this->accessService->canManagePlans($user),
                'can_export' => $this->accessService->canExportReports($user),
            ],
        ]);
    }

    public function cloneToYear(Request $request, ConvivenciaPlan $plan): JsonResponse
    {
        $this->authorize('update', $plan);
        $payload = $request->validate([
            'revision' => ['required', 'integer', 'min:1'],
            'target_year' => ['required', 'integer', 'between:2020,2100'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
        ]);

        $cloned = $this->planService->cloneToYear(
            $plan,
            (int) $payload['target_year'],
            isset($payload['academic_year_id']) ? (int) $payload['academic_year_id'] : null,
            $request->user(),
            (int) $payload['revision'],
        );

        return response()->json([
            'message' => "Plan {$cloned->calendar_year} creado como borrador y vinculado al plan {$plan->calendar_year}.",
            'data' => $this->workspace->serializePlan($this->workspace->loadPlan($cloned, $request->user())),
        ], 201);
    }
}
