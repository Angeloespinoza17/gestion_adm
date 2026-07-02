<?php

namespace App\Http\Controllers;

use App\Http\Requests\Staff\SyncStaffOrganigramRequest;
use App\Models\Staff;
use App\Models\StaffOrganigramRelation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrganigramController extends Controller
{
    public function catalogs(): JsonResponse
    {
        return response()->json([
            'staff' => Staff::query()
                ->where('active', true)
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'rut', 'institutional_email', 'cargo_id']),
            'relationship_types' => StaffOrganigramRelation::RELATIONSHIP_OPTIONS,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));
        $onlyWithRelations = $request->boolean('only_with_relations');
        $activeOnly = $request->boolean('active_only', true);

        $query = Staff::query()
            ->with([
                'cargo:id,name',
                'departments:id,name',
                'organigramRelations' => fn ($relations) => $relations
                    ->with('relatedStaff:id,full_name,rut,institutional_email')
                    ->orderBy('relationship_type')
                    ->orderByDesc('is_primary')
                    ->orderBy('priority')
                    ->orderBy('id'),
            ])
            ->withCount([
                'organigramRelations',
                'organigramRelations as active_organigram_relations_count' => fn ($relations) => $relations->where('active', true),
            ])
            ->when($search !== '', function ($staffQuery) use ($search) {
                $staffQuery->where(function ($inner) use ($search) {
                    $inner
                        ->where('full_name', 'like', "%{$search}%")
                        ->orWhere('rut', 'like', "%{$search}%")
                        ->orWhere('institutional_email', 'like', "%{$search}%");
                });
            })
            ->when($activeOnly, fn ($staffQuery) => $staffQuery->where('active', true))
            ->when($onlyWithRelations, fn ($staffQuery) => $staffQuery->has('organigramRelations'))
            ->orderBy('full_name');

        $data = $query->paginate((int) $request->query('per_page', 15));

        return response()->json([
            'data' => $data,
            'summary' => [
                'total_staff' => $data->total(),
                'with_relations' => Staff::query()
                    ->when($search !== '', function ($staffQuery) use ($search) {
                        $staffQuery->where(function ($inner) use ($search) {
                            $inner
                                ->where('full_name', 'like', "%{$search}%")
                                ->orWhere('rut', 'like', "%{$search}%")
                                ->orWhere('institutional_email', 'like', "%{$search}%");
                        });
                    })
                    ->when($activeOnly, fn ($staffQuery) => $staffQuery->where('active', true))
                    ->has('organigramRelations')
                    ->count(),
            ],
        ]);
    }

    public function show(Staff $staff): JsonResponse
    {
        return response()->json([
            'data' => $this->loadStaff($staff),
        ]);
    }

    public function sync(SyncStaffOrganigramRequest $request, Staff $staff): JsonResponse
    {
        $relations = collect($request->validated('relations', []))
            ->map(fn (array $relation) => [
                'relationship_type' => $relation['relationship_type'],
                'related_staff_id' => (int) $relation['related_staff_id'],
                'custom_label' => $relation['relationship_type'] === 'other' ? trim((string) ($relation['custom_label'] ?? '')) : null,
                'priority' => (int) ($relation['priority'] ?? 1),
                'is_primary' => (bool) ($relation['is_primary'] ?? false),
                'active' => array_key_exists('active', $relation) ? (bool) $relation['active'] : true,
                'notes' => $relation['notes'] ?? null,
                'created_by' => $request->user()?->id,
                'updated_by' => $request->user()?->id,
            ])
            ->unique(fn (array $relation) => implode(':', [
                $relation['relationship_type'],
                $relation['related_staff_id'],
            ]))
            ->values();

        DB::transaction(function () use ($staff, $relations) {
            $staff->organigramRelations()->delete();

            if ($relations->isEmpty()) {
                return;
            }

            $normalized = $relations->groupBy('relationship_type')->flatMap(function ($items) {
                $primaryAssigned = false;

                return $items->map(function (array $relation) use (&$primaryAssigned) {
                    if ($relation['is_primary'] && !$primaryAssigned) {
                        $primaryAssigned = true;
                        return $relation;
                    }

                    $relation['is_primary'] = false;
                    return $relation;
                });
            })->values();

            $staff->organigramRelations()->createMany($normalized->all());
        });

        return response()->json([
            'message' => 'Organigrama del funcionario actualizado correctamente.',
            'data' => $this->loadStaff($staff->fresh()),
        ]);
    }

    private function loadStaff(Staff $staff): Staff
    {
        return $staff->load([
            'cargo:id,name',
            'departments:id,name',
            'organigramRelations' => fn ($relations) => $relations
                ->with('relatedStaff:id,full_name,rut,institutional_email')
                ->orderBy('relationship_type')
                ->orderByDesc('is_primary')
                ->orderBy('priority')
                ->orderBy('id'),
        ]);
    }
}
