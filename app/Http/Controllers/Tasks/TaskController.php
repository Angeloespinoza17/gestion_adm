<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\SaveTaskRequest;
use App\Models\Task;
use App\Models\TaskAssigner;
use App\Models\User;
use App\Services\Tasks\TaskAccessService;
use App\Services\Tasks\TaskQueryFilters;
use App\Services\Tasks\TaskService;
use App\Services\Tasks\TaskStatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function __construct(
        private readonly TaskAccessService $accessService,
        private readonly TaskService $taskService,
        private readonly TaskStatisticsService $statisticsService,
    ) {
    }

    public function catalogs(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Task::class);

        $user = $request->user();
        $staffUsers = User::query()
            ->with('staff:id,full_name,cargo_id', 'staff.cargo:id,name')
            ->where('active', true)
            ->where(function ($query) {
                $query->where('user_type', 'staff')->orWhereNotNull('staff_id');
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'user_type', 'staff_id']);

        $assignableUserIds = collect([(int) $user->id]);
        if ($this->accessService->canManageBacklogs($user)) {
            $assignableUserIds = $staffUsers->pluck('id');
        } else {
            $assignedTargets = TaskAssigner::query()
                ->where('assigner_user_id', $user->id)
                ->where('active', true)
                ->pluck('target_user_id');
            $assignableUserIds = $assignableUserIds->merge($assignedTargets)->unique()->values();
        }

        return response()->json([
            'priorities' => Task::PRIORITY_OPTIONS,
            'statuses' => Task::STATUS_OPTIONS,
            'users' => $staffUsers,
            'assignable_users' => $staffUsers->whereIn('id', $assignableUserIds)->values(),
            'current_user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'staff_id' => $user->staff_id,
            ],
            'capabilities' => [
                'can_manage_backlogs' => $this->accessService->canManageBacklogs($user),
                'can_manage_assigners' => $this->accessService->canManageAssigners($user),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Task::class);

        $query = $this->accessService->visibleQuery($request->user())
            ->with([
                'owner:id,name,email,user_type,staff_id',
                'owner.staff:id,full_name,cargo_id',
                'owner.staff.cargo:id,name',
                'creator:id,name,email,user_type,staff_id',
                'subtasks.owner:id,name,email,user_type,staff_id',
                'subtasks.creator:id,name,email,user_type,staff_id',
            ]);

        TaskQueryFilters::apply($query, $request);

        if ($request->boolean('only_subtasks')) {
            $query->whereNotNull('parent_task_id');
        } elseif (!$request->boolean('include_subtasks')) {
            $query->whereNull('parent_task_id');
        }

        $sortBy = in_array($request->query('sort_by'), ['title', 'priority', 'status', 'stakeholder', 'due_date', 'created_at', 'updated_at', 'sort_order'], true)
            ? $request->query('sort_by')
            : 'sort_order';
        $sortDirection = $request->query('sort_direction') === 'desc' ? 'desc' : 'asc';

        $query->orderBy($sortBy, $sortDirection)->orderByDesc('updated_at')->orderByDesc('id');

        if ($request->query('per_page') === 'all') {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate((int) $request->query('per_page', 20)));
    }

    public function stats(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Task::class);

        return response()->json([
            'data' => $this->statisticsService->build($request, $request->user()),
        ]);
    }

    public function show(Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        return response()->json([
            'data' => $this->taskService->loadTask($task),
        ]);
    }

    public function store(SaveTaskRequest $request): JsonResponse
    {
        $this->authorize('create', Task::class);

        $task = $this->taskService->create($request->validated(), $request->user());

        return response()->json([
            'message' => 'Tarea creada correctamente.',
            'data' => $task,
        ], 201);
    }

    public function storeSubtask(SaveTaskRequest $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $payload = $request->validated();
        $payload['parent_task_id'] = $task->id;
        $payload['owner_user_id'] = $task->owner_user_id;

        $subtask = $this->taskService->create($payload, $request->user());

        return response()->json([
            'message' => 'Subtarea creada correctamente.',
            'data' => $subtask,
        ], 201);
    }

    public function update(SaveTaskRequest $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $task = $this->taskService->update($task, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Tarea actualizada correctamente.',
            'data' => $task,
        ]);
    }

    public function updateStatus(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $payload = $request->validate([
            'status' => ['required', Rule::in(Task::statusValues())],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $task = $this->taskService->updateStatus(
            $task,
            $payload['status'],
            $request->user(),
            isset($payload['sort_order']) ? (int) $payload['sort_order'] : null,
        );

        return response()->json([
            'message' => 'Estado de tarea actualizado correctamente.',
            'data' => $task,
        ]);
    }

    public function destroy(Request $request, Task $task): JsonResponse
    {
        $this->authorize('delete', $task);

        $this->taskService->delete($task, $request->user(), $request->boolean('delete_subtasks'));

        return response()->json([
            'message' => 'Tarea eliminada correctamente.',
        ]);
    }
}
