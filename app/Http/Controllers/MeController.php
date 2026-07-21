<?php

namespace App\Http\Controllers;

use App\Models\SystemModule;
use App\Services\Rbac\RoleModuleSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __construct(private readonly RoleModuleSyncService $roleModuleSyncService) {}

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

            return $this->noStoreResponse($this->normalizeHomeModule($modules));
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

        $modules = SystemModule::query()
            ->whereIn('id', $moduleIds)
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->get(['id', 'parent_id', 'name', 'slug', 'frontend_route', 'icon', 'sort_order']);

        return $this->noStoreResponse($this->normalizeHomeModule($modules));
    }

    public function permissions(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return response()->json([
            'data' => $user->isSuperAdmin()
                ? array_values(array_unique(array_merge(['__superadmin__'], $user->permissionSlugs())))
                : $user->permissionSlugs(),
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

    private function noStoreResponse($modules): JsonResponse
    {
        return response()->json(['data' => $modules])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
    }
}
