<?php

namespace App\Http\Controllers;

use App\Http\Requests\Spaces\StoreDependencyRequest;
use App\Http\Requests\Spaces\UpdateDependencyRequest;
use App\Models\DependencyReservation;
use App\Models\DependencyType;
use App\Models\MaintenanceDependency;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MaintenanceDependencyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));
        $typeId = $request->query('dependency_type_id');
        $responsibleStaffId = $request->query('responsible_staff_id');
        $status = trim((string) $request->query('availability_status'));
        $active = $request->query('active');

        $dependencies = $this->queryForContext($request)
            ->with([
                'type:id,name,color',
                'responsibleStaff:id,full_name,rut',
            ])
            ->withCount([
                'reservations',
                'reservations as upcoming_reservations_count' => fn ($query) => $query
                    ->whereIn('status', [DependencyReservation::STATUS_PENDING, DependencyReservation::STATUS_APPROVED])
                    ->where('ends_at', '>=', now()),
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhere('floor_sector', 'like', "%{$search}%")
                        ->orWhere('distribution', 'like', "%{$search}%")
                        ->orWhere('sector', 'like', "%{$search}%")
                        ->orWhere('zone', 'like', "%{$search}%")
                        ->orWhere('usage', 'like', "%{$search}%")
                        ->orWhere('available_equipment', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhere('observations', 'like', "%{$search}%");
                });
            })
            ->when($typeId, fn ($query) => $query->where('dependency_type_id', $typeId))
            ->when($responsibleStaffId, fn ($query) => $query->where('responsible_staff_id', $responsibleStaffId))
            ->when($status !== '', fn ($query) => $query->where('availability_status', $status));

        if ($active !== null && $active !== '') {
            $dependencies->where('active', filter_var($active, FILTER_VALIDATE_BOOLEAN));
        }

        $dependencies = $dependencies
            ->orderBy('distribution')
            ->orderBy('sector')
            ->orderBy('name')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($dependencies);
    }

    public function store(StoreDependencyRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $image = $request->file('image');
        $approverIds = array_map('intval', $payload['approver_user_ids'] ?? []);
        unset($payload['image'], $payload['approver_user_ids']);
        $payload['is_reservable'] = $this->isSpacesContext($request);
        $payload['requires_approval'] = $payload['is_reservable'] ? true : (bool) ($payload['requires_approval'] ?? false);

        $dependency = MaintenanceDependency::query()->create($payload);
        $this->syncApprovers($dependency, $approverIds);

        if ($image instanceof UploadedFile) {
            $this->storeImage($dependency, $image);
        }

        return response()->json([
            'message' => 'Dependencia creada correctamente.',
            'data' => $this->loadDependency($dependency->fresh()),
        ], 201);
    }

    public function show(MaintenanceDependency $maintenanceDependency): JsonResponse
    {
        $this->ensureDependencyVisibleInContext(request(), $maintenanceDependency);

        return response()->json([
            'data' => $this->loadDependency($maintenanceDependency),
        ]);
    }

    public function update(UpdateDependencyRequest $request, MaintenanceDependency $maintenanceDependency): JsonResponse
    {
        $this->ensureDependencyVisibleInContext($request, $maintenanceDependency);

        $payload = $request->validated();
        $image = $request->file('image');
        $approverIds = array_map('intval', $payload['approver_user_ids'] ?? []);
        unset($payload['image'], $payload['approver_user_ids']);
        $payload['is_reservable'] = $this->isSpacesContext($request);
        $payload['requires_approval'] = $payload['is_reservable'] ? true : (bool) ($payload['requires_approval'] ?? false);

        $maintenanceDependency->update($payload);
        $this->syncApprovers($maintenanceDependency, $approverIds);

        if ($image instanceof UploadedFile) {
            $this->storeImage($maintenanceDependency, $image);
        }

        return response()->json([
            'message' => 'Dependencia actualizada correctamente.',
            'data' => $this->loadDependency($maintenanceDependency->fresh()),
        ]);
    }

    public function destroy(MaintenanceDependency $maintenanceDependency): JsonResponse
    {
        $this->ensureDependencyVisibleInContext(request(), $maintenanceDependency);

        if ($maintenanceDependency->workOrders()->exists()) {
            return response()->json([
                'message' => 'No es posible eliminar una dependencia asociada a órdenes de trabajo.',
            ], 422);
        }

        if ($maintenanceDependency->reservations()->exists()) {
            return response()->json([
                'message' => 'No es posible eliminar una dependencia con historial de reservas.',
            ], 422);
        }

        $maintenanceDependency->delete();
        Storage::disk('public')->deleteDirectory(sprintf('dependencies/%d', $maintenanceDependency->id));

        return response()->json([
            'message' => 'Dependencia eliminada correctamente.',
        ]);
    }

    public function catalogs(): JsonResponse
    {
        return response()->json([
            'dependency_types' => DependencyType::query()
                ->where('active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'color']),
            'responsible_staff' => Staff::query()
                ->where('active', true)
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'rut']),
            'approver_users' => User::query()
                ->with('staff:id,full_name,rut')
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'staff_id']),
            'distributions' => $this->distinct(request(), 'distribution'),
            'sectors' => $this->distinct(request(), 'sector'),
            'zones' => $this->distinct(request(), 'zone'),
            'usages' => $this->distinct(request(), 'usage'),
            'statuses' => [
                ['value' => MaintenanceDependency::AVAILABILITY_AVAILABLE, 'label' => 'Disponible'],
                ['value' => MaintenanceDependency::AVAILABILITY_UNAVAILABLE, 'label' => 'No disponible'],
                ['value' => MaintenanceDependency::AVAILABILITY_MAINTENANCE, 'label' => 'En mantención'],
                ['value' => MaintenanceDependency::AVAILABILITY_BLOCKED, 'label' => 'Bloqueada'],
            ],
            'total' => $this->queryForContext(request())->count(),
            'active' => $this->queryForContext(request())->where('active', true)->count(),
        ]);
    }

    public function approversIndex(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));
        $typeId = $request->query('dependency_type_id');

        return response()->json([
            'dependencies' => MaintenanceDependency::query()
                ->where('is_reservable', true)
                ->with([
                    'type:id,name,color',
                    'responsibleStaff:id,full_name,rut',
                    'approvers:id,name,email,staff_id',
                    'approvers.staff:id,full_name,rut',
                ])
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($query) use ($search) {
                        $query
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%")
                            ->orWhere('location', 'like', "%{$search}%");
                    });
                })
                ->when($typeId, fn ($query) => $query->where('dependency_type_id', $typeId))
                ->orderBy('name')
                ->get([
                    'id',
                    'dependency_type_id',
                    'responsible_staff_id',
                    'code',
                    'name',
                    'location',
                    'floor_sector',
                    'availability_status',
                    'requires_approval',
                    'calendar_color',
                ]),
            'dependency_types' => DependencyType::query()
                ->where('active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'color']),
            'approver_users' => User::query()
                ->with('staff:id,full_name,rut')
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'staff_id']),
        ]);
    }

    public function updateApprovers(Request $request, MaintenanceDependency $maintenanceDependency): JsonResponse
    {
        abort_unless($maintenanceDependency->is_reservable, 404);

        $payload = $request->validate([
            'approver_user_ids' => ['nullable', 'array'],
            'approver_user_ids.*' => ['integer', Rule::exists('users', 'id')],
            'requires_approval' => ['nullable', 'boolean'],
        ]);

        $maintenanceDependency->update([
            'requires_approval' => true,
        ]);

        $this->syncApprovers(
            $maintenanceDependency,
            array_map('intval', $payload['approver_user_ids'] ?? [])
        );

        return response()->json([
            'message' => 'Gestores de aprobación actualizados correctamente.',
            'data' => $this->loadDependency($maintenanceDependency->fresh()),
        ]);
    }

    private function distinct(Request $request, string $column): array
    {
        return $this->queryForContext($request)
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->values()
            ->all();
    }

    private function loadDependency(MaintenanceDependency $dependency): MaintenanceDependency
    {
        return $dependency->load([
            'type:id,name,color,description',
            'responsibleStaff:id,full_name,rut',
            'approvers:id,name,email,staff_id',
            'approvers.staff:id,full_name,rut',
            'reservations' => fn ($query) => $query
                ->with([
                    'staff:id,full_name,rut',
                    'department:id,name,color',
                    'createdBy:id,name',
                    'approvedBy:id,name',
                ])
                ->orderBy('starts_at'),
        ]);
    }

    private function syncApprovers(MaintenanceDependency $dependency, array $approverIds): void
    {
        $dependency->approvers()->sync(collect($approverIds)->filter()->unique()->values()->all());
    }

    private function queryForContext(Request $request)
    {
        $query = MaintenanceDependency::query();

        if ($this->isSpacesContext($request)) {
            return $query->where('is_reservable', true);
        }

        if ($this->isMaintenanceContext($request)) {
            return $query->where('is_reservable', false);
        }

        return $query;
    }

    private function isSpacesContext(Request $request): bool
    {
        return $request->is('api/spaces/*');
    }

    private function isMaintenanceContext(Request $request): bool
    {
        return $request->is('api/maintenance/*');
    }

    private function ensureDependencyVisibleInContext(Request $request, MaintenanceDependency $dependency): void
    {
        if ($this->isSpacesContext($request) && !$dependency->is_reservable) {
            abort(404);
        }

        if ($this->isMaintenanceContext($request) && $dependency->is_reservable) {
            abort(404);
        }
    }

    private function storeImage(MaintenanceDependency $dependency, UploadedFile $image): void
    {
        if ($dependency->image_path) {
            Storage::disk('public')->delete($dependency->image_path);
        }

        $path = $image->storePubliclyAs(
            sprintf('dependencies/%d', $dependency->id),
            now()->format('Ymd_His') . '_' . uniqid() . '.' . $image->getClientOriginalExtension(),
            ['disk' => 'public']
        );

        $dependency->update(['image_path' => $path]);
    }
}
