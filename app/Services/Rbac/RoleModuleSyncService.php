<?php

namespace App\Services\Rbac;

use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\SystemModule;
use App\Models\User;

class RoleModuleSyncService
{
    /**
     * @param array<int, int|string> $permissionIds
     * @return array<int, int>
     */
    public function moduleIdsForPermissionIds(array $permissionIds): array
    {
        $permissionIds = Permission::query()
            ->where('active', true)
            ->whereIn('id', collect($permissionIds)->map(fn ($id) => (int) $id)->filter()->unique()->values()->all())
            ->pluck('id')
            ->all();

        if (empty($permissionIds)) {
            return [];
        }

        $moduleIds = PermissionGroup::query()
            ->where('active', true)
            ->whereNotNull('system_module_id')
            ->whereHas('permissions', fn ($query) => $query->whereIn('permissions.id', $permissionIds))
            ->pluck('system_module_id')
            ->all();

        return $this->expandModuleIds($moduleIds, includeDescendants: true, includeAncestors: true);
    }

    /**
     * @return array<int, int>
     */
    public function moduleIdsForRolePermissions(Role $role): array
    {
        $role->loadMissing('permissions:id');

        return $this->moduleIdsForPermissionIds($role->permissions->pluck('id')->all());
    }

    /**
     * @return array<int, int>
     */
    public function moduleIdsForUserPermissions(User $user): array
    {
        if ($user->isSuperAdmin()) {
            return SystemModule::query()
                ->where('active', true)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $roles = $user->roles()
            ->with(['permissions' => fn ($query) => $query->where('active', true)->select('permissions.id')])
            ->get();

        return $this->moduleIdsForPermissionIds(
            $roles->pluck('permissions')->flatten()->pluck('id')->all()
        );
    }

    /**
     * @param array<int, int|string> $requestedModuleIds
     * @return array<int, int>
     */
    public function syncRoleModulesFromPermissions(Role $role, ?array $requestedModuleIds = null): array
    {
        $baseModuleIds = $this->expandModuleIds(
            $requestedModuleIds ?? $role->modules()->pluck('system_modules.id')->all(),
            includeDescendants: false,
            includeAncestors: true,
        );

        $moduleIds = collect($baseModuleIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->merge($this->moduleIdsForRolePermissions($role))
            ->unique()
            ->values()
            ->all();

        $role->modules()->sync($moduleIds);

        return $moduleIds;
    }

    /**
     * @param array<int, int|string> $moduleIds
     * @return array<int, int>
     */
    public function expandModuleIds(array $moduleIds, bool $includeDescendants = true, bool $includeAncestors = true): array
    {
        $modules = SystemModule::query()
            ->where('active', true)
            ->get(['id', 'parent_id']);

        $activeIds = $modules->pluck('id')->map(fn ($id) => (int) $id)->flip();
        $parents = [];
        $children = [];

        foreach ($modules as $module) {
            $id = (int) $module->id;
            $parentId = $module->parent_id ? (int) $module->parent_id : null;

            $parents[$id] = $parentId;

            if ($parentId) {
                $children[$parentId][] = $id;
            }
        }

        $expanded = [];
        $baseIds = collect($moduleIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0 && $activeIds->has($id))
            ->unique()
            ->values()
            ->all();

        foreach ($baseIds as $moduleId) {
            $expanded[$moduleId] = true;

            if ($includeAncestors) {
                $parentId = $parents[$moduleId] ?? null;

                while ($parentId && $activeIds->has($parentId)) {
                    $expanded[$parentId] = true;
                    $parentId = $parents[$parentId] ?? null;
                }
            }

            if ($includeDescendants) {
                $stack = $children[$moduleId] ?? [];

                while (!empty($stack)) {
                    $childId = array_pop($stack);

                    if (!$activeIds->has($childId) || isset($expanded[$childId])) {
                        continue;
                    }

                    $expanded[$childId] = true;

                    foreach ($children[$childId] ?? [] as $nestedChildId) {
                        $stack[] = $nestedChildId;
                    }
                }
            }
        }

        return collect(array_keys($expanded))
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
