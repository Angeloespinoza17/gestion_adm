<?php

namespace App\Http\Controllers\Pme;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pme\ChangePmeActionStateRequest;
use App\Http\Requests\Pme\SavePmeActionProgressRequest;
use App\Http\Requests\Pme\SavePmeActionRequest;
use App\Models\Pme\PmeAction;
use App\Services\Pme\PmeAccessService;
use App\Services\Pme\PmeActionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PmeActionController extends Controller
{
    public function __construct(
        private readonly PmeAccessService $accessService,
        private readonly PmeActionService $actionService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        $query = PmeAction::query()
            ->with(['plan:id,name,school_year', 'dimension:id,name', 'objective:id,name', 'strategy:id,name', 'responsibleUser:id,name', 'indicators:id,name'])
            ->withCount(['evidences', 'activities', 'milestones'])
            ->orderByDesc('id');
        $query->when($request->query('pme_plan_id'), fn ($builder, $plan) => $builder->where('pme_plan_id', $plan));
        $query->when($request->query('pme_dimension_id'), fn ($builder, $dimension) => $builder->where('pme_dimension_id', $dimension));
        $query->when($request->query('state'), fn ($builder, $state) => $builder->where('state', $state));
        $query->when($request->query('responsible_user_id'), fn ($builder, $responsible) => $builder->where('responsible_user_id', $responsible));
        $query->when($request->query('funding_source'), fn ($builder, $funding) => $builder->where('funding_source', $funding));
        $query->when($request->query('search'), function ($builder, $search) {
            $builder->where(function ($nested) use ($search) {
                $nested->where('name', 'like', "%{$search}%")
                    ->orWhere('responsible_area', 'like', "%{$search}%");
            });
        });

        return response()->json($query->paginate(15));
    }

    public function show(Request $request, PmeAction $action): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        return response()->json([
            'data' => $action->load([
                'plan:id,name,school_year',
                'dimension:id,name',
                'objective:id,name',
                'strategy:id,name',
                'responsibleUser:id,name,email',
                'indicators:id,name',
                'activities.responsibleUser:id,name',
                'milestones.responsibleUser:id,name',
                'evidences.uploadedBy:id,name',
                'monitorings.responsibleUser:id,name',
            ]),
        ]);
    }

    public function store(SavePmeActionRequest $request): JsonResponse
    {
        abort_unless($this->accessService->canCreateAction($request->user()), 403);

        return response()->json([
            'message' => 'Acción PME creada correctamente.',
            'data' => $this->actionService->store($request->validated(), $request->user()),
        ], 201);
    }

    public function update(SavePmeActionRequest $request, PmeAction $action): JsonResponse
    {
        abort_unless($this->accessService->canEditAction($request->user()), 403);

        return response()->json([
            'message' => 'Acción PME actualizada correctamente.',
            'data' => $this->actionService->update($action, $request->validated(), $request->user()),
        ]);
    }

    public function progress(SavePmeActionProgressRequest $request, PmeAction $action): JsonResponse
    {
        abort_unless($this->accessService->canEditAction($request->user()), 403);

        return response()->json([
            'message' => 'Avance de acción registrado correctamente.',
            'data' => $this->actionService->registerProgress($action, $request->validated(), $request->user()),
        ]);
    }

    public function changeState(ChangePmeActionStateRequest $request, PmeAction $action): JsonResponse
    {
        abort_unless($this->accessService->canEditAction($request->user()), 403);

        return response()->json([
            'message' => 'Estado de la acción actualizado correctamente.',
            'data' => $this->actionService->changeState($action, $request->validated('state'), $request->user(), $request->validated('notes')),
        ]);
    }

    public function close(ChangePmeActionStateRequest $request, PmeAction $action): JsonResponse
    {
        abort_unless($this->accessService->canCloseAction($request->user()), 403);

        return response()->json([
            'message' => 'Acción cerrada correctamente.',
            'data' => $this->actionService->close($action, $request->user(), $request->validated('notes')),
        ]);
    }

    public function reopen(ChangePmeActionStateRequest $request, PmeAction $action): JsonResponse
    {
        abort_unless($this->accessService->canEditAction($request->user()), 403);

        return response()->json([
            'message' => 'Acción reabierta correctamente.',
            'data' => $this->actionService->reopen($action, $request->user(), $request->validated('notes')),
        ]);
    }
}
