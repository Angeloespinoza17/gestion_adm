<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\SystemModule;
use App\Models\User;
use App\Services\Rbac\RoleModuleSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function __construct(private readonly RoleModuleSyncService $roleModuleSyncService)
    {
    }

    public function catalogs(): JsonResponse
    {
        $permissionGroups = PermissionGroup::query()
            ->with([
                'systemModule:id,parent_id,name,slug,sort_order',
                'permissions' => fn ($query) => $query
                    ->where('active', true)
                    ->orderBy('name')
                    ->select('permissions.id', 'permissions.name', 'permissions.slug', 'permissions.description'),
            ])
            ->where('active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (PermissionGroup $group) => [
                'id' => $group->id,
                'name' => $group->name,
                'slug' => $group->slug,
                'description' => $group->description,
                'sort_order' => $group->sort_order,
                'system_module' => $group->systemModule ? [
                    'id' => $group->systemModule->id,
                    'parent_id' => $group->systemModule->parent_id,
                    'name' => $group->systemModule->name,
                    'slug' => $group->systemModule->slug,
                    'sort_order' => $group->systemModule->sort_order,
                ] : null,
                'permissions' => $group->permissions
                    ->map(fn (Permission $permission) => [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'slug' => $permission->slug,
                        'description' => $permission->description,
                    ])
                    ->values(),
            ])
            ->values();

        return response()->json([
            'permissions' => Permission::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'description']),
            'modules' => SystemModule::query()
                ->where('active', true)
                ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
                ->orderBy('parent_id')
                ->orderBy('sort_order')
                ->get(['id', 'parent_id', 'name', 'slug', 'frontend_route', 'sort_order']),
            'permission_groups' => $permissionGroups,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));

        $roles = Role::query()
            ->withCount(['users', 'permissions', 'modules'])
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $roles]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'slug' => ['required', 'string', 'max:191', 'unique:roles,slug'],
            'description' => ['nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $role = Role::create($payload);

        return response()->json([
            'message' => 'Rol creado correctamente.',
            'data' => $role,
        ], 201);
    }

    public function show(Role $role): JsonResponse
    {
        return response()->json([
            'data' => $role->load([
                'permissions:id,name,slug',
                'modules:id,name,slug,parent_id,frontend_route,sort_order',
                'users' => fn ($query) => $query
                    ->with('cargo:id,name')
                    ->orderBy('name')
                    ->select('users.id', 'users.name', 'users.email', 'users.active', 'users.cargo_id'),
            ]),
        ]);
    }

    public function removeUser(Role $role, User $user): JsonResponse
    {
        if (! $role->users()->whereKey($user->id)->exists()) {
            return response()->json([
                'message' => 'El usuario no está asociado a este rol.',
            ], 404);
        }

        $role->users()->detach($user->id);

        return response()->json([
            'message' => 'Usuario retirado del rol correctamente.',
        ]);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['sometimes', 'string', 'max:191'],
            'slug' => ['sometimes', 'string', 'max:191', Rule::unique('roles', 'slug')->ignore($role->id)],
            'description' => ['nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $role->update($payload);

        return response()->json([
            'message' => 'Rol actualizado correctamente.',
            'data' => $role,
        ]);
    }

    public function destroy(Role $role): JsonResponse
    {
        $role->delete();

        return response()->json([
            'message' => 'Rol eliminado correctamente.',
        ]);
    }

    public function setPermissions(Request $request, Role $role): JsonResponse
    {
        $payload = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role->permissions()->sync($payload['permissions']);
        $this->roleModuleSyncService->syncRoleModulesFromPermissions($role);

        return response()->json([
            'message' => 'Permisos actualizados correctamente.',
            'data' => $role->load('permissions:id,name,slug', 'modules:id,name,slug,parent_id,frontend_route,sort_order'),
        ]);
    }

    public function setModules(Request $request, Role $role): JsonResponse
    {
        $payload = $request->validate([
            'modules' => ['present', 'array'],
            'modules.*' => ['integer', 'exists:system_modules,id'],
        ]);

        $this->roleModuleSyncService->syncRoleModulesFromPermissions($role, $payload['modules']);

        return response()->json([
            'message' => 'Módulos actualizados correctamente.',
            'data' => $role->load('modules:id,name,slug,parent_id,frontend_route,sort_order'),
        ]);
    }
}
