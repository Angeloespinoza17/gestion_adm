<?php

namespace App\Http\Controllers;

use App\Models\Cargo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CargoController extends Controller
{
    public function index(): JsonResponse
    {
        $cargos = Cargo::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description', 'active']);

        return response()->json(['data' => $cargos]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'slug' => ['required', 'string', 'max:191', 'unique:cargos,slug'],
            'description' => ['nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $cargo = Cargo::create($payload);

        return response()->json([
            'message' => 'Cargo creado correctamente.',
            'data' => $cargo,
        ], 201);
    }

    public function update(Request $request, Cargo $cargo): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['sometimes', 'string', 'max:191'],
            'slug' => ['sometimes', 'string', 'max:191', Rule::unique('cargos', 'slug')->ignore($cargo->id)],
            'description' => ['nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $cargo->update($payload);

        return response()->json([
            'message' => 'Cargo actualizado correctamente.',
            'data' => $cargo,
        ]);
    }

    public function setActive(Request $request, Cargo $cargo): JsonResponse
    {
        $payload = $request->validate([
            'active' => ['required', 'boolean'],
        ]);

        $cargo->update(['active' => $payload['active']]);

        return response()->json([
            'message' => 'Estado actualizado correctamente.',
            'data' => $cargo,
        ]);
    }
}

