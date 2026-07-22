<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Cargo;
use App\Models\Role;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function catalogs(): JsonResponse
    {
        return response()->json([
            'cargos' => Cargo::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'slug']),
            'roles' => Role::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));

        $users = User::query()
            ->with([
                'cargo:id,name,slug',
                'roles:id,name,slug',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate((int) $request->query('per_page', 15));

        $actorId = $request->user()?->id;
        $users->getCollection()->transform(function (User $user) use ($actorId): User {
            $user->setAttribute(
                'can_delete',
                $user->id !== $actorId
                    && ! $user->roles->contains(fn (Role $role) => $role->slug === 'super_admin')
            );

            return $user;
        });

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'cargo_id' => ['nullable', 'integer', 'exists:cargos,id'],
            'user_type' => ['nullable', 'string', 'max:191'],
            'active' => ['sometimes', 'boolean'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ]);

        $roles = $payload['roles'] ?? [];
        unset($payload['roles']);

        $payload['password'] = Hash::make($payload['password']);

        $user = User::create($payload);
        if (!empty($roles)) {
            $user->roles()->sync($roles);
        }

        return response()->json([
            'message' => 'Usuario creado correctamente.',
            'data' => $user->load('cargo:id,name,slug', 'roles:id,name,slug'),
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json([
            'data' => $user->load('cargo:id,name,slug', 'roles:id,name,slug'),
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'cargo_id' => ['nullable', 'integer', 'exists:cargos,id'],
            'user_type' => ['nullable', 'string', 'max:191'],
            'active' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('password', $payload)) {
            $payload['password'] = $payload['password']
                ? Hash::make($payload['password'])
                : $user->password;
        }

        $user->update($payload);

        return response()->json([
            'message' => 'Usuario actualizado correctamente.',
            'data' => $user->load('cargo:id,name,slug', 'roles:id,name,slug'),
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        $this->assertUsersCanBeDeleted(collect([$user]), request()->user());
        $user->delete();

        return response()->json([
            'message' => 'Usuario eliminado correctamente.',
        ]);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'users' => ['required', 'array', 'min:1', 'max:100'],
            'users.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
        ]);

        $userIds = collect($payload['users'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $users = User::query()
            ->with('roles:id,name,slug')
            ->whereIn('id', $userIds)
            ->get();

        $this->assertUsersCanBeDeleted($users, $request->user());

        try {
            DB::transaction(function () use ($users): void {
                foreach ($users as $user) {
                    $user->delete();
                }
            });
        } catch (QueryException $exception) {
            report($exception);

            return response()->json([
                'message' => 'No se eliminaron usuarios. Uno o más tienen registros relacionados que deben conservarse.',
            ], 409);
        }

        return response()->json([
            'message' => $users->count() === 1
                ? 'Usuario eliminado correctamente.'
                : "{$users->count()} usuarios eliminados correctamente.",
            'deleted_count' => $users->count(),
        ]);
    }

    public function setActive(Request $request, User $user): JsonResponse
    {
        $payload = $request->validate([
            'active' => ['required', 'boolean'],
        ]);

        $user->update(['active' => $payload['active']]);

        return response()->json([
            'message' => 'Estado actualizado correctamente.',
            'data' => $user->load('cargo:id,name,slug', 'roles:id,name,slug'),
        ]);
    }

    public function setRoles(Request $request, User $user): JsonResponse
    {
        $payload = $request->validate([
            'roles' => ['present', 'array'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ]);

        $user->roles()->sync($payload['roles']);

        return response()->json([
            'message' => 'Roles actualizados correctamente.',
            'data' => $user->load('cargo:id,name,slug', 'roles:id,name,slug'),
        ]);
    }

    public function setCargo(Request $request, User $user): JsonResponse
    {
        $payload = $request->validate([
            'cargo_id' => ['nullable', 'integer', 'exists:cargos,id'],
        ]);

        $user->update(['cargo_id' => $payload['cargo_id']]);

        return response()->json([
            'message' => 'Cargo actualizado correctamente.',
            'data' => $user->load('cargo:id,name,slug', 'roles:id,name,slug'),
        ]);
    }

    private function assertUsersCanBeDeleted(Collection $users, ?User $actor): void
    {
        $protectedUsers = $users->filter(function (User $user) use ($actor): bool {
            return $user->id === $actor?->id
                || $user->roles->contains(fn (Role $role) => $role->slug === 'super_admin');
        });

        if ($protectedUsers->isEmpty()) {
            return;
        }

        throw ValidationException::withMessages([
            'users' => 'La cuenta actual y las cuentas Super Admin no pueden eliminarse.',
        ]);
    }
}
