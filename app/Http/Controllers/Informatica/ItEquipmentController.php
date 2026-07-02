<?php

namespace App\Http\Controllers\Informatica;

use App\Http\Controllers\Controller;
use App\Http\Requests\Informatica\ChangeItEquipmentStatusRequest;
use App\Http\Requests\Informatica\SaveItEquipmentRequest;
use App\Models\It\ItEquipment;
use App\Services\Informatica\ItEquipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItEquipmentController extends Controller
{
    public function __construct(
        private readonly ItEquipmentService $equipmentService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ItEquipment::class);

        $search = trim((string) $request->query('search'));

        $query = ItEquipment::query()
            ->with(['responsibleUser:id,name,email', 'createdBy:id,name', 'updatedBy:id,name'])
            ->withCount(['loans', 'maintenanceReports'])
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($inner) use ($search) {
                    $inner
                        ->where('internal_code', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhere('serial_number', 'like', "%{$search}%")
                        ->orWhere('location_name', 'like', "%{$search}%")
                        ->orWhere('responsible_name', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('equipment_type'), fn ($builder) => $builder->where('equipment_type', $request->query('equipment_type')))
            ->when($request->filled('status'), fn ($builder) => $builder->where('status', $request->query('status')))
            ->when($request->filled('brand'), fn ($builder) => $builder->where('brand', 'like', '%' . $request->query('brand') . '%'))
            ->when($request->filled('location_name'), fn ($builder) => $builder->where('location_name', 'like', '%' . $request->query('location_name') . '%'))
            ->when($request->filled('responsible'), fn ($builder) => $builder->where('responsible_name', 'like', '%' . $request->query('responsible') . '%'))
            ->when($request->filled('internal_code'), fn ($builder) => $builder->where('internal_code', 'like', '%' . $request->query('internal_code') . '%'))
            ->when($request->filled('serial_number'), fn ($builder) => $builder->where('serial_number', 'like', '%' . $request->query('serial_number') . '%'))
            ->when($request->boolean('with_inactive') === false, fn ($builder) => $builder->where('active', true));

        return response()->json([
            'items' => $query->orderBy('internal_code')->paginate((int) $request->query('per_page', 15)),
            'summary' => [
                'total' => ItEquipment::query()->count(),
                'available' => ItEquipment::query()->available()->count(),
                'loaned' => ItEquipment::query()->loaned()->count(),
                'maintenance' => ItEquipment::query()->underMaintenance()->count(),
                'damaged' => ItEquipment::query()->damaged()->count(),
                'decommissioned' => ItEquipment::query()->decommissioned()->count(),
            ],
        ]);
    }

    public function show(ItEquipment $equipment): JsonResponse
    {
        $this->authorize('view', $equipment);

        return response()->json([
            'data' => $equipment->load([
                'responsibleUser:id,name,email',
                'attachments.uploadedBy:id,name',
                'loans.attachments.uploadedBy:id,name',
                'loans.deliveredBy:id,name',
                'loans.receivedBy:id,name',
                'maintenanceReports.attachments.uploadedBy:id,name',
                'maintenanceReports.technician:id,name',
                'maintenanceReports.closedBy:id,name',
                'statusLogs.changedBy:id,name',
                'createdBy:id,name',
                'updatedBy:id,name',
            ]),
        ]);
    }

    public function store(SaveItEquipmentRequest $request): JsonResponse
    {
        $this->authorize('create', ItEquipment::class);

        $equipment = $this->equipmentService->create(
            $request->validated(),
            $request->user(),
            $request->file('photo')
        );

        return response()->json([
            'message' => 'Equipo registrado correctamente.',
            'data' => $equipment,
        ], 201);
    }

    public function update(SaveItEquipmentRequest $request, ItEquipment $equipment): JsonResponse
    {
        $this->authorize('update', $equipment);

        $equipment = $this->equipmentService->update(
            $equipment,
            $request->validated(),
            $request->user(),
            $request->file('photo')
        );

        return response()->json([
            'message' => 'Equipo actualizado correctamente.',
            'data' => $equipment,
        ]);
    }

    public function changeStatus(ChangeItEquipmentStatusRequest $request, ItEquipment $equipment): JsonResponse
    {
        $this->authorize('update', $equipment);

        $validated = $request->validated();
        $extra = [];

        if (array_key_exists('active', $validated)) {
            $extra['active'] = $validated['active'];
        }

        $equipment = $this->equipmentService->changeStatus(
            $equipment,
            $validated['status'],
            $request->user(),
            $validated['notes'] ?? null,
            'cambio_manual_estado',
            $equipment->id,
            true,
            $extra
        );

        return response()->json([
            'message' => 'Estado del equipo actualizado correctamente.',
            'data' => $equipment,
        ]);
    }

    public function destroy(ItEquipment $equipment): JsonResponse
    {
        $this->authorize('delete', $equipment);

        $this->equipmentService->delete($equipment);

        return response()->json([
            'message' => 'Equipo eliminado lógicamente correctamente.',
        ]);
    }
}
