<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\SystemModule;
use App\Models\User;
use App\Services\Rbac\RoleModuleSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    public function __construct(private readonly RoleModuleSyncService $roleModuleSyncService) {}

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
        $this->normalizeSlug($request);
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'slug' => ['required', 'string', 'max:191', 'regex:/^[a-z0-9]+(?:_[a-z0-9]+)*$/', 'unique:roles,slug'],
            'description' => ['nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['integer', Rule::exists('permissions', 'id')->where('active', true)],
            'modules' => ['sometimes', 'array'],
            'modules.*' => ['integer', Rule::exists('system_modules', 'id')->where('active', true)],
        ]);

        $role = DB::transaction(function () use ($payload): Role {
            $role = Role::create(Arr::only($payload, ['name', 'slug', 'description', 'active']));
            $this->syncRoleAccess($role, $payload);

            return $role;
        });

        return response()->json([
            'message' => 'Rol creado correctamente.',
            'data' => $this->loadRoleAccess($role),
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
        $this->normalizeSlug($request);
        $payload = $request->validate([
            'name' => ['sometimes', 'string', 'max:191'],
            'slug' => ['sometimes', 'string', 'max:191', 'regex:/^[a-z0-9]+(?:_[a-z0-9]+)*$/', Rule::unique('roles', 'slug')->ignore($role->id)],
            'description' => ['nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['integer', Rule::exists('permissions', 'id')->where('active', true)],
            'modules' => ['sometimes', 'array'],
            'modules.*' => ['integer', Rule::exists('system_modules', 'id')->where('active', true)],
        ]);

        $this->assertRoleMutationIsSafe($role, $payload);

        DB::transaction(function () use ($role, $payload): void {
            $role->update(Arr::only($payload, ['name', 'slug', 'description', 'active']));
            $this->syncRoleAccess($role, $payload);
        });

        return response()->json([
            'message' => 'Rol actualizado correctamente.',
            'data' => $this->loadRoleAccess($role),
        ]);
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($role->slug === 'super_admin') {
            throw ValidationException::withMessages(['role' => 'El rol Super Admin no se puede eliminar.']);
        }

        if ($role->users()->exists()) {
            throw ValidationException::withMessages(['role' => 'No se puede eliminar un rol que todavía tiene usuarios asociados.']);
        }

        $role->delete();

        return response()->json([
            'message' => 'Rol eliminado correctamente.',
        ]);
    }

    public function setPermissions(Request $request, Role $role): JsonResponse
    {
        $payload = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['integer', Rule::exists('permissions', 'id')->where('active', true)],
        ]);

        $this->assertRoleMutationIsSafe($role, $payload);

        DB::transaction(fn () => $this->syncRoleAccess($role, $payload));

        return response()->json([
            'message' => 'Permisos actualizados correctamente.',
            'data' => $this->loadRoleAccess($role),
        ]);
    }

    public function setModules(Request $request, Role $role): JsonResponse
    {
        $payload = $request->validate([
            'modules' => ['present', 'array'],
            'modules.*' => ['integer', Rule::exists('system_modules', 'id')->where('active', true)],
        ]);

        $this->assertRoleMutationIsSafe($role, $payload);

        DB::transaction(fn () => $this->syncRoleAccess($role, $payload));

        return response()->json([
            'message' => 'Módulos actualizados correctamente.',
            'data' => $this->loadRoleAccess($role),
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function syncRoleAccess(Role $role, array $payload): void
    {
        if ($role->slug === 'super_admin') {
            $role->permissions()->sync(Permission::query()->where('active', true)->pluck('id'));
            $role->modules()->sync(SystemModule::query()->where('active', true)->pluck('id'));

            return;
        }

        if (array_key_exists('permissions', $payload)) {
            $role->permissions()->sync($payload['permissions']);
        }

        if (array_key_exists('permissions', $payload) || array_key_exists('modules', $payload)) {
            $this->roleModuleSyncService->syncRoleModulesFromPermissions($role, $payload['modules'] ?? null);
        }
    }

    /** @param array<string, mixed> $payload */
    private function assertRoleMutationIsSafe(Role $role, array $payload): void
    {
        if ($role->slug === 'super_admin') {
            if (($payload['slug'] ?? 'super_admin') !== 'super_admin' || array_key_exists('active', $payload) && ! $payload['active']) {
                throw ValidationException::withMessages(['role' => 'El rol Super Admin debe permanecer activo y conservar su slug.']);
            }

            return;
        }

        if ($role->users()->exists() && array_key_exists('permissions', $payload) && empty($payload['permissions'])) {
            throw ValidationException::withMessages(['permissions' => 'Un rol con usuarios asociados debe conservar al menos un permiso.']);
        }

        if ($role->users()->exists() && array_key_exists('active', $payload) && ! $payload['active']) {
            throw ValidationException::withMessages(['active' => 'Retire primero los usuarios antes de desactivar el rol.']);
        }
    }

    private function normalizeSlug(Request $request): void
    {
        if (! $request->has('slug')) {
            return;
        }

        $request->merge([
            'slug' => Str::snake(Str::lower(trim((string) $request->input('slug')))),
        ]);
    }

    private function loadRoleAccess(Role $role): Role
    {
        return $role->fresh()->load(
            'permissions:id,name,slug',
            'modules:id,name,slug,parent_id,frontend_route,sort_order',
        );
    }
}
