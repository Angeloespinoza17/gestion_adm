<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\SaveTaskAssignerRequest;
use App\Models\Task;
use App\Models\TaskAssigner;
use App\Models\User;
use App\Services\Tasks\TaskAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskAssignerController extends Controller
{
    public function __construct(
        private readonly TaskAccessService $accessService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('manageAssigners', Task::class);

        $query = TaskAssigner::query()
            ->with([
                'targetUser:id,name,email,user_type,staff_id',
                'targetUser.staff:id,full_name,cargo_id',
                'targetUser.staff.cargo:id,name',
                'assignerUser:id,name,email,user_type,staff_id',
                'assignerUser.staff:id,full_name,cargo_id',
                'assignerUser.staff.cargo:id,name',
                'createdBy:id,name,email',
            ])
            ->when($request->filled('target_user_id'), fn ($query) => $query->where('target_user_id', (int) $request->query('target_user_id')))
            ->when($request->filled('assigner_user_id'), fn ($query) => $query->where('assigner_user_id', (int) $request->query('assigner_user_id')))
            ->when($request->filled('active'), fn ($query) => $query->where('active', $request->boolean('active')))
            ->orderByDesc('active')
            ->orderByDesc('created_at');

        return response()->json($query->paginate((int) $request->query('per_page', 20)));
    }

    public function store(SaveTaskAssignerRequest $request): JsonResponse
    {
        $this->authorize('manageAssigners', Task::class);

        $payload = $request->validated();
        $assigner = TaskAssigner::query()->firstOrNew([
            'target_user_id' => $payload['target_user_id'],
            'assigner_user_id' => $payload['assigner_user_id'],
        ]);

        if (!$assigner->exists) {
            $assigner->created_by_user_id = $request->user()->id;
        }

        $assigner->active = (bool) ($payload['active'] ?? true);
        $assigner->save();

        return response()->json([
            'message' => 'Asignador de tareas guardado correctamente.',
            'data' => $this->loadAssigner($assigner),
        ], 201);
    }

    public function update(SaveTaskAssignerRequest $request, TaskAssigner $taskAssigner): JsonResponse
    {
        $this->authorize('manageAssigners', Task::class);

        $payload = $request->validated();
        $taskAssigner->update([
            'target_user_id' => $payload['target_user_id'],
            'assigner_user_id' => $payload['assigner_user_id'],
            'active' => (bool) ($payload['active'] ?? true),
        ]);

        return response()->json([
            'message' => 'Asignador de tareas actualizado correctamente.',
            'data' => $this->loadAssigner($taskAssigner),
        ]);
    }

    public function destroy(TaskAssigner $taskAssigner): JsonResponse
    {
        $this->authorize('manageAssigners', Task::class);

        $taskAssigner->update(['active' => false]);

        return response()->json([
            'message' => 'Asignador de tareas desactivado correctamente.',
        ]);
    }

    public function canAssign(Request $request): JsonResponse
    {
        $request->validate([
            'target_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $target = User::query()->findOrFail((int) $request->query('target_user_id'));

        return response()->json([
            'can_assign' => $this->accessService->canCreateForOwner($request->user(), $target),
        ]);
    }

    private function loadAssigner(TaskAssigner $taskAssigner): TaskAssigner
    {
        return $taskAssigner->load([
            'targetUser:id,name,email,user_type,staff_id',
            'targetUser.staff:id,full_name,cargo_id',
            'targetUser.staff.cargo:id,name',
            'assignerUser:id,name,email,user_type,staff_id',
            'assignerUser.staff:id,full_name,cargo_id',
            'assignerUser.staff.cargo:id,name',
            'createdBy:id,name,email',
        ]);
    }
}
