<?php

namespace App\Http\Controllers;

use App\Models\Cargo;
use App\Models\Role;
use App\Models\User;
use App\Services\Staff\StaffProfileLinker;
use App\Services\Users\UserDeletionService;
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
    private const USER_TYPE_OPTIONS = [
        ['value' => 'staff', 'label' => 'Funcionario'],
        ['value' => 'student', 'label' => 'Estudiante'],
        ['value' => 'guardian', 'label' => 'Apoderado'],
        ['value' => 'role_preview', 'label' => 'Cuenta de vista previa'],
    ];

    public function catalogs(): JsonResponse
    {
        return response()->json([
            'cargos' => Cargo::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'slug']),
            'roles' => Role::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'slug']),
            'user_types' => self::USER_TYPE_OPTIONS,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));
        $userType = trim((string) $request->query('user_type'));

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
            ->when($userType !== '', fn ($query) => $query->where('user_type', $userType))
            ->orderBy('name')
            ->paginate((int) $request->query('per_page', 15));

        $actorId = $request->user()?->id;
        $users->getCollection()->transform(function (User $user) use ($actorId): User {
            $user->setAttribute(
                'can_delete',
                $user->id !== $actorId
                    && $user->user_type !== 'role_preview'
                    && ! $user->roles->contains(fn (Role $role) => $role->slug === 'super_admin')
            );

            return $user;
        });

        return response()->json($users);
    }

    public function store(Request $request, StaffProfileLinker $staffProfileLinker): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'cargo_id' => ['nullable', 'integer', 'exists:cargos,id'],
            'user_type' => [
                'nullable',
                Rule::in(array_column(self::USER_TYPE_OPTIONS, 'value')),
                Rule::notIn(['role_preview']),
            ],
            'active' => ['sometimes', 'boolean'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ]);

        $roles = $payload['roles'] ?? [];
        unset($payload['roles']);

        $payload['password'] = Hash::make($payload['password']);

        $actorId = $request->user()?->id;
        $user = DB::transaction(function () use ($payload, $roles, $actorId, $staffProfileLinker): User {
            $user = User::query()->create($payload);

            if (! empty($roles)) {
                $user->roles()->sync($roles);
            }

            if ($user->user_type === 'staff') {
                $staffProfileLinker->ensureLinked($user, $actorId);
            }

            return $user->refresh();
        });

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

    public function update(Request $request, User $user, StaffProfileLinker $staffProfileLinker): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'cargo_id' => ['nullable', 'integer', 'exists:cargos,id'],
            'user_type' => [
                'nullable',
                Rule::in(array_column(self::USER_TYPE_OPTIONS, 'value')),
                function ($attribute, $value, $fail) use ($user) {
                    if ($user->user_type === 'role_preview' && $value !== 'role_preview') {
                        $fail('La categoría de las cuentas técnicas de vista previa no puede modificarse.');
                    }

                    if ($user->user_type !== 'role_preview' && $value === 'role_preview') {
                        $fail('La categoría Cuenta de vista previa está reservada para cuentas técnicas.');
                    }
                },
            ],
            'active' => ['sometimes', 'boolean'],
        ]);

        $categoryChanged = array_key_exists('user_type', $payload)
            && $payload['user_type'] !== $user->user_type;

        if ($categoryChanged) {
            if ($payload['user_type'] !== 'staff') {
                $payload['staff_id'] = null;
            }

            if ($payload['user_type'] !== 'student') {
                $payload['student_id'] = null;
            }

            if ($payload['user_type'] !== 'guardian') {
                $payload['guardian_id'] = null;
            }
        }

        if (array_key_exists('password', $payload)) {
            $payload['password'] = $payload['password']
                ? Hash::make($payload['password'])
                : $user->password;
        }

        $actorId = $request->user()?->id;
        DB::transaction(function () use ($user, $payload, $actorId, $staffProfileLinker): void {
            $user->update($payload);

            if ($user->user_type === 'staff') {
                $staffProfileLinker->ensureLinked($user, $actorId);
            }
        });

        $user->refresh();

        return response()->json([
            'message' => 'Usuario actualizado correctamente.',
            'data' => $user->load('cargo:id,name,slug', 'roles:id,name,slug'),
        ]);
    }

    public function destroy(User $user, UserDeletionService $deletionService): JsonResponse
    {
        $this->assertUsersCanBeDeleted(collect([$user]), request()->user());

        try {
            $result = $deletionService->deleteUsers(collect([$user]));
        } catch (QueryException $exception) {
            report($exception);

            return response()->json([
                'message' => 'No se eliminó el usuario. La cuenta o su ficha de funcionario tienen registros protegidos que deben conservarse.',
            ], 409);
        }

        return response()->json([
            'message' => match (true) {
                $result['staff'] > 0 => 'Usuario y ficha de funcionario eliminados correctamente.',
                $result['students'] > 0 => 'Usuario y ficha de estudiante eliminados correctamente.',
                default => 'Usuario eliminado correctamente.',
            },
        ]);
    }

    public function bulkDestroy(Request $request, UserDeletionService $deletionService): JsonResponse
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
            $result = $deletionService->deleteUsers($users);
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
            'deleted_staff_count' => $result['staff'],
            'deleted_student_count' => $result['students'],
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
                || $user->user_type === 'role_preview'
                || $user->roles->contains(fn (Role $role) => $role->slug === 'super_admin');
        });

        if ($protectedUsers->isEmpty()) {
            return;
        }

        throw ValidationException::withMessages([
            'users' => 'La cuenta actual, las cuentas Super Admin y las cuentas técnicas de vista previa no pueden eliminarse.',
        ]);
    }
}
