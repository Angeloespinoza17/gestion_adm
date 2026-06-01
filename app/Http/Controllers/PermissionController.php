<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        $permissions = Permission::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description', 'active']);

        return response()->json(['data' => $permissions]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'slug' => ['required', 'string', 'max:191', 'unique:permissions,slug'],
            'description' => ['nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $permission = Permission::create($payload);

        return response()->json([
            'message' => 'Permiso creado correctamente.',
            'data' => $permission,
        ], 201);
    }
}

