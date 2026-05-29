<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceDependency;
use App\Models\MaintenanceWorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MaintenanceWorkOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));
        $status = trim((string) $request->query('status'));
        $priority = trim((string) $request->query('priority'));

        $workOrders = MaintenanceWorkOrder::query()
            ->with('dependency:id,code,name,distribution,sector,zone,usage')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('description', 'like', "%{$search}%")
                        ->orWhere('requested_by', 'like', "%{$search}%")
                        ->orWhere('assigned_to', 'like', "%{$search}%")
                        ->orWhere('location_code', 'like', "%{$search}%")
                        ->orWhere('location_distribution', 'like', "%{$search}%")
                        ->orWhere('location_sector', 'like', "%{$search}%")
                        ->orWhere('location_name', 'like', "%{$search}%")
                        ->orWhere('location_usage', 'like', "%{$search}%")
                        ->orWhereHas('dependency', function ($query) use ($search) {
                            $query
                                ->where('code', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%")
                                ->orWhere('distribution', 'like', "%{$search}%")
                                ->orWhere('sector', 'like', "%{$search}%")
                                ->orWhere('zone', 'like', "%{$search}%");
                        });
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($priority !== '', fn ($query) => $query->where('priority', $priority))
            ->orderByRaw("FIELD(priority, 'Crítico', 'Alta', 'Media', 'Baja')")
            ->orderByRaw("FIELD(status, 'Sin comenzar', 'En proceso', 'En espera', 'Pausado', 'Terminado', 'Anulado')")
            ->orderByRaw("CASE WHEN due_date IS NULL THEN 1 ELSE 0 END")
            ->orderBy('due_date')
            ->orderByDesc('reported_at')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($workOrders);
    }

    public function store(Request $request): JsonResponse
    {
        $workOrder = MaintenanceWorkOrder::create($this->validated($request));

        return response()->json([
            'message' => 'Orden de trabajo creada correctamente.',
            'data' => $workOrder->load('dependency:id,code,name,distribution,sector,zone,usage'),
        ], 201);
    }

    public function update(Request $request, MaintenanceWorkOrder $maintenanceWorkOrder): JsonResponse
    {
        $maintenanceWorkOrder->update($this->validated($request));

        return response()->json([
            'message' => 'Orden de trabajo actualizada correctamente.',
            'data' => $maintenanceWorkOrder->load('dependency:id,code,name,distribution,sector,zone,usage'),
        ]);
    }

    public function destroy(MaintenanceWorkOrder $maintenanceWorkOrder): JsonResponse
    {
        $maintenanceWorkOrder->delete();

        return response()->json([
            'message' => 'Orden de trabajo eliminada correctamente.',
        ]);
    }

    public function catalogs(): JsonResponse
    {
        return response()->json([
            'priorities' => ['Crítico', 'Alta', 'Media', 'Baja'],
            'statuses' => ['Sin comenzar', 'En proceso', 'En espera', 'Pausado', 'Terminado', 'Anulado'],
            'assignees' => $this->distinct('assigned_to'),
            'requesters' => $this->distinct('requested_by'),
            'dependencies' => MaintenanceDependency::query()
                ->where('active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'distribution', 'sector', 'zone']),
            'summary' => [
                'total' => MaintenanceWorkOrder::count(),
                'open' => MaintenanceWorkOrder::whereNotIn('status', ['Terminado', 'Anulado'])->count(),
                'critical' => MaintenanceWorkOrder::where('priority', 'Crítico')->count(),
                'finished' => MaintenanceWorkOrder::where('status', 'Terminado')->count(),
            ],
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'maintenance_dependency_id' => ['nullable', 'integer', 'exists:maintenance_dependencies,id'],
            'location_code' => ['nullable', 'string', 'max:255'],
            'location_distribution' => ['nullable', 'string', 'max:255'],
            'location_sector' => ['nullable', 'string', 'max:255'],
            'location_name' => ['nullable', 'string', 'max:255'],
            'location_usage' => ['nullable', 'string', 'max:255'],
            'reported_at' => ['nullable', 'date'],
            'requested_by' => ['nullable', 'string', 'max:255'],
            'assigned_to' => ['nullable', 'string', 'max:255'],
            'priority' => ['required', 'string', Rule::in(['Crítico', 'Alta', 'Media', 'Baja'])],
            'status' => ['required', 'string', Rule::in(['Sin comenzar', 'En proceso', 'En espera', 'Pausado', 'Terminado', 'Anulado'])],
            'due_date' => ['nullable', 'date'],
            'description' => ['required', 'string'],
            'resolution_notes' => ['nullable', 'string'],
            'photo_reference' => ['nullable', 'string'],
        ]);
    }

    private function distinct(string $column): array
    {
        return MaintenanceWorkOrder::query()
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->values()
            ->all();
    }
}
