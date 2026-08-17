<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskAccessService;
use App\Services\Tasks\TaskQueryFilters;
use App\Services\Tasks\TaskStatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskReportController extends Controller
{
    public function __construct(
        private readonly TaskAccessService $accessService,
        private readonly TaskStatisticsService $statisticsService,
    ) {
    }

    public function catalogs(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewReports($request->user()), 403);

        $users = User::query()
            ->with('staff:id,full_name,cargo_id', 'staff.cargo:id,name')
            ->where('active', true)
            ->where(fn ($query) => $query->where('user_type', 'staff')->orWhereNotNull('staff_id'))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'user_type', 'staff_id']);

        return response()->json([
            'priorities' => Task::PRIORITY_OPTIONS,
            'statuses' => Task::STATUS_OPTIONS,
            'users' => $users,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewReports($request->user()), 403);

        $query = Task::query()->with([
            'owner:id,name,email,user_type,staff_id',
            'owner.staff:id,full_name,cargo_id',
            'owner.staff.cargo:id,name',
            'creator:id,name,email,user_type,staff_id',
            'stakeholders:id,name,email,user_type,staff_id',
            'stakeholders.staff:id,full_name,cargo_id',
            'stakeholders.staff.cargo:id,name',
        ]);

        TaskQueryFilters::apply($query, $request);

        if (!$request->boolean('include_subtasks')) {
            $query->whereNull('parent_task_id');
        }

        $sortBy = in_array($request->query('sort_by'), ['title', 'priority', 'status', 'due_date', 'created_at', 'updated_at'], true)
            ? $request->query('sort_by')
            : 'due_date';
        $sortDirection = $request->query('sort_direction') === 'desc' ? 'desc' : 'asc';

        if ($sortBy === 'priority') {
            $query->orderByRaw("CASE priority WHEN 'urgente' THEN 1 WHEN 'alta' THEN 2 WHEN 'media' THEN 3 WHEN 'baja' THEN 4 ELSE 5 END {$sortDirection}");
        } elseif ($sortBy === 'status') {
            $query->orderByRaw("CASE status WHEN 'pendiente' THEN 1 WHEN 'en_progreso' THEN 2 WHEN 'bloqueada' THEN 3 WHEN 'en_revision' THEN 4 WHEN 'completada' THEN 5 WHEN 'cancelada' THEN 6 ELSE 7 END {$sortDirection}");
        } elseif ($sortBy === 'due_date') {
            $query->orderByRaw('due_date IS NULL')->orderBy('due_date', $sortDirection);
        } else {
            $query->orderBy($sortBy, $sortDirection);
        }

        $query->orderByDesc('updated_at')->orderByDesc('id');

        if ($request->query('per_page') === 'all') {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate(min(max((int) $request->query('per_page', 25), 1), 100)));
    }

    public function stats(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewReports($request->user()), 403);

        $query = Task::query();
        if (!$request->boolean('include_subtasks')) {
            $query->whereNull('parent_task_id');
        }

        return response()->json([
            'data' => $this->statisticsService->buildFromQuery($request, $query),
        ]);
    }
}
