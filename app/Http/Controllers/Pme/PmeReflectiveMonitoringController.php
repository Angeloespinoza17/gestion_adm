<?php

namespace App\Http\Controllers\Pme;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pme\SavePmeReflectiveMonitoringRequest;
use App\Models\Pme\PmeReflectiveMonitoring;
use App\Services\Pme\PmeAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PmeReflectiveMonitoringController extends Controller
{
    public function __construct(
        private readonly PmeAccessService $accessService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        $query = PmeReflectiveMonitoring::query()
            ->with(['plan:id,name,school_year', 'dimension:id,name', 'objective:id,name', 'strategy:id,name', 'action:id,name', 'responsibleUser:id,name'])
            ->withCount('evidences')
            ->orderByDesc('monitored_at');
        $query->when($request->query('pme_plan_id'), fn ($builder, $plan) => $builder->where('pme_plan_id', $plan));
        $query->when($request->query('state'), fn ($builder, $state) => $builder->where('state', $state));
        $query->when($request->query('responsible_user_id'), fn ($builder, $responsible) => $builder->where('responsible_user_id', $responsible));

        return response()->json($query->paginate(15));
    }

    public function store(SavePmeReflectiveMonitoringRequest $request): JsonResponse
    {
        abort_unless($this->accessService->canRegisterMonitoring($request->user()), 403);

        $monitoring = PmeReflectiveMonitoring::query()->create(array_merge($request->validated(), [
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]));

        return response()->json([
            'message' => 'Monitoreo reflexivo registrado correctamente.',
            'data' => $monitoring->fresh(['plan', 'dimension', 'objective', 'strategy', 'action', 'responsibleUser']),
        ], 201);
    }

    public function update(SavePmeReflectiveMonitoringRequest $request, PmeReflectiveMonitoring $monitoring): JsonResponse
    {
        abort_unless($this->accessService->canRegisterMonitoring($request->user()), 403);

        $monitoring->update(array_merge($request->validated(), [
            'updated_by' => $request->user()->id,
        ]));

        return response()->json([
            'message' => 'Monitoreo reflexivo actualizado correctamente.',
            'data' => $monitoring->fresh(['plan', 'dimension', 'objective', 'strategy', 'action', 'responsibleUser']),
        ]);
    }
}
