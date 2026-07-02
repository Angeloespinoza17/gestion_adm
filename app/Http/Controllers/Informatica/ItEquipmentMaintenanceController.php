<?php

namespace App\Http\Controllers\Informatica;

use App\Http\Controllers\Controller;
use App\Http\Requests\Informatica\CloseItEquipmentMaintenanceRequest;
use App\Http\Requests\Informatica\SaveItEquipmentMaintenanceRequest;
use App\Models\It\ItEquipmentMaintenanceReport;
use App\Services\Informatica\ItEquipmentMaintenanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItEquipmentMaintenanceController extends Controller
{
    public function __construct(
        private readonly ItEquipmentMaintenanceService $maintenanceService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ItEquipmentMaintenanceReport::class);

        $search = trim((string) $request->query('search'));

        $query = ItEquipmentMaintenanceReport::query()
            ->with([
                'equipment:id,internal_code,equipment_type,brand,model,status',
                'technician:id,name,email',
                'closedBy:id,name',
            ])
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($inner) use ($search) {
                    $inner
                        ->where('maintenance_code', 'like', "%{$search}%")
                        ->orWhere('technician_name_snapshot', 'like', "%{$search}%")
                        ->orWhere('reason', 'like', "%{$search}%")
                        ->orWhereHas('equipment', function ($equipmentQuery) use ($search) {
                            $equipmentQuery
                                ->where('internal_code', 'like', "%{$search}%")
                                ->orWhere('brand', 'like', "%{$search}%")
                                ->orWhere('model', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('status'), fn ($builder) => $builder->where('status', $request->query('status')))
            ->when($request->filled('maintenance_type'), fn ($builder) => $builder->where('maintenance_type', $request->query('maintenance_type')))
            ->when($request->filled('it_equipment_id'), fn ($builder) => $builder->where('it_equipment_id', $request->query('it_equipment_id')))
            ->when($request->filled('technician_user_id'), fn ($builder) => $builder->where('technician_user_id', $request->query('technician_user_id')))
            ->when($request->filled('final_equipment_status'), fn ($builder) => $builder->where('final_equipment_status', $request->query('final_equipment_status')))
            ->when($request->filled('date_from'), fn ($builder) => $builder->whereDate('maintenance_date', '>=', $request->query('date_from')))
            ->when($request->filled('date_to'), fn ($builder) => $builder->whereDate('maintenance_date', '<=', $request->query('date_to')))
            ->when($request->boolean('only_pending'), fn ($builder) => $builder->whereIn('status', ['borrador', 'finalizado', 'pendiente_revision']));

        return response()->json([
            'items' => $query->latest('maintenance_date')->paginate((int) $request->query('per_page', 15)),
            'summary' => [
                'pending' => ItEquipmentMaintenanceReport::query()->pending()->count(),
                'closed' => ItEquipmentMaintenanceReport::query()->closed()->count(),
                'month_total' => ItEquipmentMaintenanceReport::query()->whereDate('maintenance_date', '>=', now()->startOfMonth())->count(),
                'month_cost' => round((float) ItEquipmentMaintenanceReport::query()->whereDate('maintenance_date', '>=', now()->startOfMonth())->sum('cost_amount'), 2),
            ],
        ]);
    }

    public function show(ItEquipmentMaintenanceReport $report): JsonResponse
    {
        $this->authorize('view', $report);

        return response()->json([
            'data' => $report->load([
                'equipment:id,internal_code,equipment_type,brand,model,status,location_name',
                'technician:id,name,email',
                'closedBy:id,name',
                'attachments.uploadedBy:id,name',
            ]),
        ]);
    }

    public function store(SaveItEquipmentMaintenanceRequest $request): JsonResponse
    {
        $this->authorize('create', ItEquipmentMaintenanceReport::class);

        $report = $this->maintenanceService->create(
            $request->validated(),
            $request->user(),
            $request->file('attachment')
        );

        return response()->json([
            'message' => 'Informe de mantención registrado correctamente.',
            'data' => $report,
        ], 201);
    }

    public function update(SaveItEquipmentMaintenanceRequest $request, ItEquipmentMaintenanceReport $report): JsonResponse
    {
        $this->authorize('update', $report);

        $report = $this->maintenanceService->update(
            $report,
            $request->validated(),
            $request->user(),
            $request->file('attachment')
        );

        return response()->json([
            'message' => 'Informe de mantención actualizado correctamente.',
            'data' => $report,
        ]);
    }

    public function close(CloseItEquipmentMaintenanceRequest $request, ItEquipmentMaintenanceReport $report): JsonResponse
    {
        $this->authorize('update', $report);

        $report = $this->maintenanceService->close(
            $report,
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'message' => 'Mantención cerrada correctamente.',
            'data' => $report,
        ]);
    }
}
