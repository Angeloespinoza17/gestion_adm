<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Cargo;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

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
        $user->delete();

        return response()->json([
            'message' => 'Usuario eliminado correctamente.',
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
}
