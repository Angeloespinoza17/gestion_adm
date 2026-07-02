<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\SyncStaffPermissionWatchersRequest;
use App\Models\PermissionRequest;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffPermissionWatcherController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $this->authorize('manageWatchers', PermissionRequest::class);

        $query = Staff::query()
            ->with([
                'cargo:id,name',
                'departments:id,name',
                'permissionWatchers.role:id,name,slug',
                'permissionWatchers.user:id,name,email',
            ])
            ->withCount([
                'permissionWatchers',
                'permissionWatchers as active_permission_watchers_count' => fn ($watchers) => $watchers->where('active', true),
            ])
            ->orderBy('full_name');

        $search = trim((string) $request->string('search'));

        if ($search !== '') {
            $query->where(function ($staffQuery) use ($search) {
                $staffQuery
                    ->where('full_name', 'like', "%{$search}%")
                    ->orWhere('rut', 'like', "%{$search}%")
                    ->orWhere('institutional_email', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('active_only', true)) {
            $query->where('active', true);
        }

        if ($request->boolean('only_with_watchers')) {
            $query->has('permissionWatchers');
        }

        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);
        $data = $query->paginate($perPage);

        $baseSummary = Staff::query();

        if ($request->boolean('active_only', true)) {
            $baseSummary->where('active', true);
        }

        if ($search !== '') {
            $baseSummary->where(function ($staffQuery) use ($search) {
                $staffQuery
                    ->where('full_name', 'like', "%{$search}%")
                    ->orWhere('rut', 'like', "%{$search}%")
                    ->orWhere('institutional_email', 'like', "%{$search}%");
            });
        }

        $summary = [
            'total_staff' => (clone $baseSummary)->count(),
            'with_specific_watchers' => (clone $baseSummary)->has('permissionWatchers')->count(),
            'without_specific_watchers' => (clone $baseSummary)->doesntHave('permissionWatchers')->count(),
            'active_staff' => (clone $baseSummary)->where('active', true)->count(),
        ];

        return response()->json([
            'data' => $data,
            'summary' => $summary,
        ]);
    }

    public function index(Staff $staff): JsonResponse
    {
        $this->authorize('manageWatchers', PermissionRequest::class);

        return response()->json([
            'data' => $staff->permissionWatchers()
                ->with(['role:id,name,slug', 'user:id,name,email'])
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function sync(SyncStaffPermissionWatchersRequest $request, Staff $staff): JsonResponse
    {
        $this->authorize('manageWatchers', PermissionRequest::class);

        $items = collect($request->validated('watchers', []))
            ->map(fn (array $watcher) => [
                'target_type' => $watcher['target_type'],
                'role_id' => $watcher['target_type'] === 'role' ? ($watcher['role_id'] ?? null) : null,
                'user_id' => $watcher['target_type'] === 'user' ? ($watcher['user_id'] ?? null) : null,
                'notify' => (bool) ($watcher['notify'] ?? true),
                'can_view' => (bool) ($watcher['can_view'] ?? true),
                'active' => array_key_exists('active', $watcher) ? (bool) $watcher['active'] : true,
            ])
            ->unique(fn (array $watcher) => implode(':', [
                $watcher['target_type'],
                $watcher['role_id'] ?? 'null',
                $watcher['user_id'] ?? 'null',
            ]))
            ->values();

        $staff->permissionWatchers()->delete();

        if ($items->isNotEmpty()) {
            $staff->permissionWatchers()->createMany($items->all());
        }

        return response()->json([
            'message' => 'Destinatarios del funcionario actualizados correctamente.',
            'data' => $staff->fresh()->load([
                'permissionWatchers.role:id,name,slug',
                'permissionWatchers.user:id,name,email',
            ]),
        ]);
    }
}
