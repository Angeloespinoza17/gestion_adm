<?php

namespace App\Http\Controllers;

use App\Models\SystemModule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SystemModuleController extends Controller
{
    public function index(): JsonResponse
    {
        $modules = SystemModule::query()
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->get(['id', 'parent_id', 'name', 'slug', 'frontend_route', 'icon', 'sort_order', 'active']);

        return response()->json(['data' => $modules]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'slug' => ['required', 'string', 'max:191', 'unique:system_modules,slug'],
            'frontend_route' => ['nullable', 'string', 'max:191'],
            'icon' => ['nullable', 'string', 'max:191'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'active' => ['sometimes', 'boolean'],
            'parent_id' => ['nullable', 'integer', 'exists:system_modules,id'],
        ]);

        $module = SystemModule::create($payload);

        return response()->json([
            'message' => 'Módulo creado correctamente.',
            'data' => $module,
        ], 201);
    }

    public function update(Request $request, SystemModule $systemModule): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['sometimes', 'string', 'max:191'],
            'slug' => ['sometimes', 'string', 'max:191', Rule::unique('system_modules', 'slug')->ignore($systemModule->id)],
            'frontend_route' => ['nullable', 'string', 'max:191'],
            'icon' => ['nullable', 'string', 'max:191'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'active' => ['sometimes', 'boolean'],
            'parent_id' => ['nullable', 'integer', 'exists:system_modules,id'],
        ]);

        $systemModule->update($payload);

        return response()->json([
            'message' => 'Módulo actualizado correctamente.',
            'data' => $systemModule,
        ]);
    }

    public function setActive(Request $request, SystemModule $systemModule): JsonResponse
    {
        $payload = $request->validate([
            'active' => ['required', 'boolean'],
        ]);

        $systemModule->update(['active' => $payload['active']]);

        return response()->json([
            'message' => 'Estado actualizado correctamente.',
            'data' => $systemModule,
        ]);
    }
}

