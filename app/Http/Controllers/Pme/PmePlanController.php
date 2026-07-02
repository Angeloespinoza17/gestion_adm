<?php

namespace App\Http\Controllers\Pme;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pme\SavePmeCycleRequest;
use App\Http\Requests\Pme\SavePmePlanRequest;
use App\Models\Pme\PmeCycle;
use App\Models\Pme\PmePlan;
use App\Services\Pme\PmeAccessService;
use App\Services\Pme\PmePlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PmePlanController extends Controller
{
    public function __construct(
        private readonly PmeAccessService $accessService,
        private readonly PmePlanService $planService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        $plans = PmePlan::query()
            ->with(['academicYear:id,name,year', 'responsibleUser:id,name'])
            ->withCount(['objectives', 'actions', 'incomes'])
            ->orderByDesc('school_year')
            ->paginate(15);

        return response()->json($plans);
    }

    public function history(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        return response()->json([
            'data' => PmePlan::query()
                ->where('is_active', false)
                ->with(['academicYear:id,name,year', 'responsibleUser:id,name'])
                ->withCount(['objectives', 'actions'])
                ->orderByDesc('school_year')
                ->get(),
        ]);
    }

    public function show(Request $request, PmePlan $plan): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        return response()->json([
            'data' => $plan->load([
                'academicYear',
                'responsibleUser:id,name,email',
                'cycles',
                'objectives.dimension:id,name',
                'actions.responsibleUser:id,name',
            ]),
        ]);
    }

    public function store(SavePmePlanRequest $request): JsonResponse
    {
        abort_unless($this->accessService->canCreatePlan($request->user()), 403);

        return response()->json([
            'message' => 'PME creado correctamente.',
            'data' => $this->planService->store($request->validated(), $request->user()),
        ], 201);
    }

    public function update(SavePmePlanRequest $request, PmePlan $plan): JsonResponse
    {
        abort_unless($this->accessService->canEditPlan($request->user()), 403);

        return response()->json([
            'message' => 'PME actualizado correctamente.',
            'data' => $this->planService->update($plan, $request->validated(), $request->user()),
        ]);
    }

    public function activate(Request $request, PmePlan $plan): JsonResponse
    {
        abort_unless($this->accessService->canEditPlan($request->user()), 403);

        return response()->json([
            'message' => 'PME activado correctamente.',
            'data' => $this->planService->activate($plan, $request->user()),
        ]);
    }

    public function close(SavePmeCycleRequest $request, PmePlan $plan): JsonResponse
    {
        abort_unless($this->accessService->canClosePlan($request->user()), 403);

        return response()->json([
            'message' => 'PME cerrado correctamente.',
            'data' => $this->planService->close($plan, $request->user(), $request->validated('observations')),
        ]);
    }

    public function archive(SavePmeCycleRequest $request, PmePlan $plan): JsonResponse
    {
        abort_unless($this->accessService->canClosePlan($request->user()), 403);

        return response()->json([
            'message' => 'PME archivado correctamente.',
            'data' => $this->planService->archive($plan, $request->user(), $request->validated('observations')),
        ]);
    }

    public function duplicate(SavePmePlanRequest $request, PmePlan $plan): JsonResponse
    {
        abort_unless($this->accessService->canCreatePlan($request->user()), 403);

        return response()->json([
            'message' => 'La estructura del PME fue duplicada correctamente.',
            'data' => $this->planService->duplicateStructure($plan, $request->validated(), $request->user()),
        ], 201);
    }

    public function closeCycle(SavePmeCycleRequest $request, PmeCycle $cycle): JsonResponse
    {
        abort_unless($this->accessService->canClosePlan($request->user()), 403);

        return response()->json([
            'message' => 'Ciclo PME cerrado correctamente.',
            'data' => $this->planService->closeCycle($cycle, $request->user(), $request->validated('observations')),
        ]);
    }
}
