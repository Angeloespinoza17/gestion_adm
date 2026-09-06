<?php

namespace App\Http\Controllers\Convivencia;

use App\Http\Controllers\Controller;
use App\Http\Requests\Convivencia\SaveConvivenciaPlanActionRequest;
use App\Models\Convivencia\ConvivenciaPlan;
use App\Models\Convivencia\ConvivenciaPlanAction;
use App\Services\Convivencia\ConvivenciaAnnualPlanWorkspaceService;
use App\Services\Convivencia\ConvivenciaPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConvivenciaPlanActionController extends Controller
{
    public function __construct(
        private readonly ConvivenciaPlanService $planService,
        private readonly ConvivenciaAnnualPlanWorkspaceService $workspace,
    ) {}

    public function show(ConvivenciaPlanAction $action): JsonResponse
    {
        $this->authorize('view', $action->plan);

        return response()->json([
            'data' => $this->workspace->serializeActionDetail($this->workspace->loadAction($action, request()->user())),
        ]);
    }

    public function store(SaveConvivenciaPlanActionRequest $request, ConvivenciaPlan $plan): JsonResponse
    {
        $this->authorize('update', $plan);
        $action = DB::transaction(function () use ($request, $plan): ConvivenciaPlanAction {
            $plan = ConvivenciaPlan::query()->lockForUpdate()->findOrFail($plan->id);
            $this->assertCurrentRevision($plan, (int) $request->validated('plan_revision'));
            $payload = $request->safe()->except('change_summary', 'plan_revision');
            $payload = $this->normalizeSchedule($payload);
            $payload['sort_order'] = $payload['sort_order'] ?? ((int) $plan->actions()->max('sort_order') + 1);
            $payload['weight_percent'] = (float) ($payload['weight_percent'] ?? 0);
            $this->planService->assertActionWeightAvailable($plan, $payload['weight_percent']);
            $action = $plan->actions()->create($payload);
            $this->planService->recalculatePlanProgress($plan);
            $this->planService->captureVersion($plan, $request->user(), $request->input('change_summary', 'Acción incorporada al plan.'));

            return $action;
        });

        return response()->json([
            'message' => 'Acción incorporada al plan.',
            'data' => $this->workspace->serializeActionDetail($this->workspace->loadAction($action, $request->user())),
            'plan' => $this->workspace->serializePlan($this->workspace->loadPlan($plan, $request->user())),
        ], 201);
    }

    public function update(SaveConvivenciaPlanActionRequest $request, ConvivenciaPlanAction $action): JsonResponse
    {
        $plan = $action->plan;
        $this->authorize('update', $plan);
        DB::transaction(function () use ($request, $action, $plan): void {
            $plan = ConvivenciaPlan::query()->lockForUpdate()->findOrFail($plan->id);
            $this->assertCurrentRevision($plan, (int) $request->validated('plan_revision'));
            $action = ConvivenciaPlanAction::query()->lockForUpdate()->findOrFail($action->id);
            $payload = $request->safe()->except('change_summary', 'plan_revision');
            $payload = $this->normalizeSchedule($payload);
            $weight = (float) ($payload['weight_percent'] ?? $action->weight_percent);
            $this->planService->assertActionWeightAvailable($plan, $weight, $action->id);
            $action->fill($payload)->save();
            $this->planService->recalculatePlanProgress($plan);
            $this->planService->captureVersion($plan, $request->user(), $request->input('change_summary', 'Acción actualizada.'));
        });

        return response()->json([
            'message' => 'Acción actualizada correctamente.',
            'data' => $this->workspace->serializeActionDetail($this->workspace->loadAction($action, $request->user())),
            'plan' => $this->workspace->serializePlan($this->workspace->loadPlan($plan, $request->user())),
        ]);
    }

    public function destroy(Request $request, ConvivenciaPlanAction $action): JsonResponse
    {
        $plan = $action->plan;
        $this->authorize('update', $plan);
        $payload = $request->validate(['plan_revision' => ['required', 'integer', 'min:1']]);
        DB::transaction(function () use ($action, $plan, $payload): void {
            $plan = ConvivenciaPlan::query()->lockForUpdate()->findOrFail($plan->id);
            $this->assertCurrentRevision($plan, (int) $payload['plan_revision']);
            $action = ConvivenciaPlanAction::query()->lockForUpdate()->findOrFail($action->id);
            $action->delete();
            $this->planService->recalculatePlanProgress($plan);
            $this->planService->captureVersion($plan, request()->user(), 'Acción archivada.');
        });

        return response()->json(['message' => 'Acción archivada; su trazabilidad histórica se conserva.']);
    }

    private function assertCurrentRevision(ConvivenciaPlan $plan, int $revision): void
    {
        if ($revision !== (int) $plan->revision) {
            throw ValidationException::withMessages([
                'plan_revision' => ['El plan fue actualizado por otra persona. Recarga la versión vigente antes de continuar.'],
            ]);
        }
    }

    private function normalizeSchedule(array $payload): array
    {
        if (($payload['date_precision'] ?? 'exact') === 'month') {
            $payload['starts_on'] = null;
            $payload['ends_on'] = null;
        } else {
            $payload['planned_month'] = null;
        }

        return $payload;
    }
}
