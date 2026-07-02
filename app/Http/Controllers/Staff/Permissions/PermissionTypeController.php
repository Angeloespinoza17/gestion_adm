<?php

namespace App\Http\Controllers\Staff\Permissions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Permissions\StorePermissionTypeRequest;
use App\Http\Requests\Staff\Permissions\UpdatePermissionTypeRequest;
use App\Models\PermissionType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('manageTypes', \App\Models\PermissionRequest::class);

        return response()->json([
            'data' => PermissionType::query()
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function show(PermissionType $permissionType): JsonResponse
    {
        $this->authorize('manageTypes', \App\Models\PermissionRequest::class);

        return response()->json([
            'data' => $permissionType,
        ]);
    }

    public function store(StorePermissionTypeRequest $request): JsonResponse
    {
        $this->authorize('manageTypes', \App\Models\PermissionRequest::class);

        $permissionType = PermissionType::query()->create($request->validated());

        return response()->json([
            'message' => 'Tipo de permiso creado correctamente.',
            'data' => $permissionType,
        ], 201);
    }

    public function update(UpdatePermissionTypeRequest $request, PermissionType $permissionType): JsonResponse
    {
        $this->authorize('manageTypes', \App\Models\PermissionRequest::class);

        $permissionType->update($request->validated());

        return response()->json([
            'message' => 'Tipo de permiso actualizado correctamente.',
            'data' => $permissionType->fresh(),
        ]);
    }

    public function setActive(Request $request, PermissionType $permissionType): JsonResponse
    {
        $this->authorize('manageTypes', \App\Models\PermissionRequest::class);

        $payload = $request->validate([
            'active' => ['required', 'boolean'],
        ]);

        $permissionType->update(['active' => $payload['active']]);

        return response()->json([
            'message' => 'Estado actualizado correctamente.',
            'data' => $permissionType->fresh(),
        ]);
    }
}
