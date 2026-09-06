<?php

namespace App\Http\Controllers;

use App\Models\SystemModule;
use App\Services\Rbac\RoleModuleSyncService;
use App\Services\Rbac\SensitiveModuleAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __construct(
        private readonly RoleModuleSyncService $roleModuleSyncService,
        private readonly SensitiveModuleAccessService $sensitiveModuleAccessService,
    ) {}

    public function modules(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user->isSuperAdmin()) {
            $modules = SystemModule::query()
                ->where('active', true)
                ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
                ->orderBy('parent_id')
                ->orderBy('sort_order')
                ->get(['id', 'parent_id', 'name', 'slug', 'frontend_route', 'icon', 'sort_order']);

            return $this->noStoreResponse($this->normalizeHomeModule($this->filterMessagingModule($user, $modules)));
        }

        $directModules = SystemModule::query()
            ->where('active', true)
            ->whereHas('roles', function ($query) use ($user) {
                $query->whereHas('users', fn ($query) => $query->where('users.id', $user->id));
            })
            ->get(['id', 'parent_id', 'name', 'slug', 'frontend_route', 'icon', 'sort_order']);

        $moduleIds = array_values(array_unique(array_merge(
            $this->roleModuleSyncService->expandModuleIds($directModules->pluck('id')->all(), includeDescendants: false, includeAncestors: true),
            $this->roleModuleSyncService->moduleIdsForUserPermissions($user),
        )));

        if ($user->active && ($user->user_type === 'staff' || $user->staff_id !== null)) {
            $moduleIds = array_values(array_unique(array_merge(
                $moduleIds,
                SystemModule::query()
                    ->where('active', true)
                    ->whereIn('slug', ['tasks', 'tasks_backlog'])
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all(),
            )));
        }

        if ($user->canUseMessaging()) {
            $messagingModuleIds = SystemModule::query()
                ->where('active', true)
                ->where('slug', 'messaging')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $moduleIds = array_values(array_unique(array_merge(
                $moduleIds,
                $this->roleModuleSyncService->expandModuleIds(
                    $messagingModuleIds,
                    includeDescendants: false,
                    includeAncestors: true
                ),
            )));
        }

        $modules = SystemModule::query()
            ->whereIn('id', $moduleIds)
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->get(['id', 'parent_id', 'name', 'slug', 'frontend_route', 'icon', 'sort_order']);

        $modules = $this->sensitiveModuleAccessService->filterModules($user, $modules);
        $modules = $this->filterMessagingModule($user, $modules);

        return $this->noStoreResponse($this->normalizeHomeModule($modules));
    }

    public function permissions(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $markers = [];
        if ($user->isSuperAdmin()) {
            $markers[] = '__superadmin__';
        }
        if ($user->canUseMessaging()) {
            $markers[] = '__staff__';
        }

        return response()->json([
            'data' => array_values(array_unique(array_merge($markers, $user->permissionSlugs()))),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
    }

    private function normalizeHomeModule($modules)
    {
        return $modules->map(function (SystemModule $module) {
            if ($module->slug === 'dashboard') {
                $module->name = 'Inicio';
                $module->frontend_route = '/inicio';
            }

            return $module;
        });
    }

    private function filterMessagingModule($user, $modules)
    {
        if ($user->canUseMessaging()) {
            return $modules;
        }

        $blockedIds = $modules
            ->where('slug', 'messaging')
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        do {
            $previousCount = $blockedIds->count();
            $blockedIds = $blockedIds
                ->merge($modules->whereIn('parent_id', $blockedIds)->pluck('id')->map(fn ($id) => (int) $id))
                ->unique()
                ->values();
        } while ($blockedIds->count() > $previousCount);

        return $modules
            ->reject(fn (SystemModule $module) => $blockedIds->contains((int) $module->id))
            ->values();
    }

    private function noStoreResponse($modules): JsonResponse
    {
        return response()->json(['data' => $modules])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
    }
}
