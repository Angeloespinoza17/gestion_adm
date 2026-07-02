<?php

namespace App\Http\Controllers\Staff\Permissions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Permissions\SyncPermissionTypeWatchersRequest;
use App\Models\PermissionRequest;
use App\Models\PermissionType;
use App\Models\PermissionTypeWatcher;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class PermissionTypeWatcherController extends Controller
{
    public function catalogs(): JsonResponse
    {
        $this->authorize('manageWatchers', PermissionRequest::class);

        return response()->json([
            'types' => PermissionType::query()
                ->orderBy('name')
                ->get(['id', 'name', 'active']),
            'roles' => Role::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'users' => User::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'staff_id']),
            'staff' => Staff::query()
                ->where('active', true)
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'institutional_email']),
            'target_options' => PermissionTypeWatcher::TARGET_OPTIONS,
        ]);
    }

    public function index(PermissionType $permissionType): JsonResponse
    {
        $this->authorize('manageWatchers', PermissionRequest::class);

        return response()->json([
            'data' => $permissionType->watchers()
                ->with(['role:id,name,slug', 'user:id,name,email'])
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function sync(SyncPermissionTypeWatchersRequest $request, PermissionType $permissionType): JsonResponse
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

        $permissionType->watchers()->delete();

        if ($items->isNotEmpty()) {
            $permissionType->watchers()->createMany($items->all());
        }

        return response()->json([
            'message' => 'Destinatarios actualizados correctamente.',
            'data' => $permissionType->watchers()
                ->with(['role:id,name,slug', 'user:id,name,email'])
                ->orderBy('id')
                ->get(),
        ]);
    }
}
