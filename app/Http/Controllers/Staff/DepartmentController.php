<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreDepartmentRequest;
use App\Http\Requests\Staff\UpdateDepartmentRequest;
use App\Models\Department;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DepartmentController extends Controller
{
    public function catalogs(): JsonResponse
    {
        return response()->json([
            'responsible_staff' => Staff::query()
                ->where('active', true)
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'rut']),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));
        $active = $request->query('active');

        $departments = Department::query()
            ->with('responsibleStaff:id,full_name,rut')
            ->withCount('staff')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            });

        if ($active !== null && $active !== '') {
            $departments->where('active', filter_var($active, FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json([
            'data' => $departments
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $payload['slug'] = $this->generateSlug((string) $payload['name']);

        $department = Department::query()->create($payload);

        return response()->json([
            'message' => 'Departamento creado correctamente.',
            'data' => $department->load('responsibleStaff:id,full_name,rut'),
        ], 201);
    }

    public function show(Department $department): JsonResponse
    {
        return response()->json([
            'data' => $department->load([
                'responsibleStaff:id,full_name,rut',
                'staff:id,full_name,rut,status,active',
            ]),
        ]);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): JsonResponse
    {
        $payload = $request->validated();

        if (array_key_exists('name', $payload)) {
            $payload['slug'] = $this->generateSlug((string) $payload['name'], $department->id);
        }

        $department->update($payload);

        return response()->json([
            'message' => 'Departamento actualizado correctamente.',
            'data' => $department->load('responsibleStaff:id,full_name,rut'),
        ]);
    }

    public function destroy(Department $department): JsonResponse
    {
        $department->delete();

        return response()->json([
            'message' => 'Departamento eliminado correctamente.',
        ]);
    }

    public function setActive(Request $request, Department $department): JsonResponse
    {
        $payload = $request->validate([
            'active' => ['required', 'boolean'],
        ]);

        $department->update(['active' => $payload['active']]);

        return response()->json([
            'message' => 'Estado del departamento actualizado correctamente.',
            'data' => $department->load('responsibleStaff:id,full_name,rut'),
        ]);
    }

    private function generateSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'departamento';
        $slug = $base;
        $counter = 2;

        while (
            Department::query()
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
