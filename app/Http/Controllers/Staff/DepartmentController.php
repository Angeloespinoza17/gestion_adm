<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreDepartmentRequest;
use App\Http\Requests\Staff\UpdateDepartmentRequest;
use App\Models\Department;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DepartmentController extends Controller
{
    public function catalogs(): JsonResponse
    {
        return response()->json([
            'responsible_staff' => Staff::query()
                ->orderBy('full_name')
                ->with('cargo:id,name')
                ->get(['id', 'full_name', 'rut', 'cargo_id', 'active']),
            'staff' => Staff::query()
                ->orderBy('full_name')
                ->with('cargo:id,name')
                ->get(['id', 'full_name', 'rut', 'cargo_id', 'active']),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));
        $active = $request->query('active');

        $departments = Department::query()
            ->with([
                'responsibleStaff:id,full_name,rut,cargo_id,active',
                'responsibleStaff.cargo:id,name',
                'staff:id,full_name,rut,cargo_id,active',
                'staff.cargo:id,name',
            ])
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
        $staffIds = $this->teamIds($payload);
        unset($payload['staff_ids']);
        $payload['slug'] = $this->generateSlug((string) $payload['name']);

        $department = DB::transaction(function () use ($payload, $staffIds) {
            $department = Department::query()->create($payload);
            $department->staff()->sync($staffIds);

            return $department;
        });

        return response()->json([
            'message' => 'Departamento creado correctamente.',
            'data' => $this->loadDepartment($department),
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
        $hasStaffIds = array_key_exists('staff_ids', $payload);
        $staffIds = $this->teamIds($payload);
        unset($payload['staff_ids']);

        if (array_key_exists('name', $payload)) {
            $payload['slug'] = $this->generateSlug((string) $payload['name'], $department->id);
        }

        DB::transaction(function () use ($department, $payload, $hasStaffIds, $staffIds) {
            $department->update($payload);

            if ($hasStaffIds) {
                $department->staff()->sync($staffIds);
            }
        });

        return response()->json([
            'message' => 'Departamento actualizado correctamente.',
            'data' => $this->loadDepartment($department),
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
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, int>
     */
    private function teamIds(array $payload): array
    {
        return collect($payload['staff_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function loadDepartment(Department $department): Department
    {
        return $department->load([
            'responsibleStaff:id,full_name,rut,cargo_id,active',
            'responsibleStaff.cargo:id,name',
            'staff:id,full_name,rut,cargo_id,active',
            'staff.cargo:id,name',
        ])->loadCount('staff');
    }
}
