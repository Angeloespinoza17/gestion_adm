<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceDependency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MaintenanceDependencyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));

        $dependencies = MaintenanceDependency::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('distribution', 'like', "%{$search}%")
                        ->orWhere('sector', 'like', "%{$search}%")
                        ->orWhere('zone', 'like', "%{$search}%")
                        ->orWhere('usage', 'like', "%{$search}%");
                });
            })
            ->orderBy('distribution')
            ->orderBy('sector')
            ->orderBy('name')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($dependencies);
    }

    public function store(Request $request): JsonResponse
    {
        $dependency = MaintenanceDependency::create($this->validated($request));

        return response()->json([
            'message' => 'Dependencia creada correctamente.',
            'data' => $dependency,
        ], 201);
    }

    public function update(Request $request, MaintenanceDependency $maintenanceDependency): JsonResponse
    {
        $maintenanceDependency->update($this->validated($request, $maintenanceDependency));

        return response()->json([
            'message' => 'Dependencia actualizada correctamente.',
            'data' => $maintenanceDependency,
        ]);
    }

    public function destroy(MaintenanceDependency $maintenanceDependency): JsonResponse
    {
        $maintenanceDependency->delete();

        return response()->json([
            'message' => 'Dependencia eliminada correctamente.',
        ]);
    }

    public function catalogs(): JsonResponse
    {
        return response()->json([
            'distributions' => $this->distinct('distribution'),
            'sectors' => $this->distinct('sector'),
            'zones' => $this->distinct('zone'),
            'usages' => $this->distinct('usage'),
            'total' => MaintenanceDependency::count(),
            'active' => MaintenanceDependency::where('active', true)->count(),
        ]);
    }

    private function validated(Request $request, ?MaintenanceDependency $dependency = null): array
    {
        $dependencyId = $dependency?->id;

        return $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('maintenance_dependencies', 'code')->ignore($dependencyId)],
            'name' => ['required', 'string', 'max:255'],
            'distribution' => ['nullable', 'string', 'max:255'],
            'sector' => ['nullable', 'string', 'max:255'],
            'zone' => ['nullable', 'string', 'max:255'],
            'usage' => ['nullable', 'string', 'max:255'],
            'distribution_code' => ['nullable', 'string', 'max:50'],
            'floor_code' => ['nullable', 'string', 'max:50'],
            'dependency_code' => ['nullable', 'string', 'max:50'],
            'numbering' => ['nullable', 'integer', 'min:0'],
            'active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function distinct(string $column): array
    {
        return MaintenanceDependency::query()
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->values()
            ->all();
    }
}
