<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceDependency;
use App\Models\MaintenanceWorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MaintenanceWorkOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));
        $status = trim((string) $request->query('status'));
        $priority = trim((string) $request->query('priority'));
        $assignee = trim((string) $request->query('assignee'));
        $sort = trim((string) $request->query('sort', 'created'));

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
            ->when($assignee !== '', fn ($query) => $query->where('assigned_to', 'like', "%{$assignee}%"))
            ->when($sort === 'priority', function ($query) {
                $query
                    ->orderByRaw("FIELD(priority, 'Crítico', 'Alta', 'Media', 'Baja')")
                    ->orderByRaw("FIELD(status, 'Sin comenzar', 'En proceso', 'En espera', 'Pausado', 'Terminado', 'Anulado')")
                    ->orderByRaw("CASE WHEN due_date IS NULL THEN 1 ELSE 0 END")
                    ->orderBy('due_date')
                    ->orderByDesc('created_at');
            }, fn ($query) => $query->orderByDesc('created_at'))
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($workOrders);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validated($request);

        if ($request->hasFile('photo')) {
            $payload['photo_reference'] = $this->storePhoto($request->file('photo'));
        }

        $workOrder = MaintenanceWorkOrder::create($payload);

        return response()->json([
            'message' => 'Orden de trabajo creada correctamente.',
            'data' => $workOrder->load('dependency:id,code,name,distribution,sector,zone,usage'),
        ], 201);
    }

    public function show(MaintenanceWorkOrder $maintenanceWorkOrder): JsonResponse
    {
        return response()->json([
            'data' => $maintenanceWorkOrder->load('dependency:id,code,name,distribution,sector,zone,usage'),
        ]);
    }

    public function update(Request $request, MaintenanceWorkOrder $maintenanceWorkOrder): JsonResponse
    {
        $payload = $this->validated($request);

        if ($request->hasFile('photo')) {
            $payload['photo_reference'] = $this->storePhoto($request->file('photo'), $maintenanceWorkOrder->photo_reference);
        }

        $maintenanceWorkOrder->update($payload);

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
            'assignees' => $this->assignees(),
            'requesters' => $this->requesters(),
            'dependencies' => MaintenanceDependency::query()
                ->where('active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'distribution', 'sector', 'zone', 'is_reservable']),
            'summary' => [
                'total' => MaintenanceWorkOrder::count(),
                'open' => MaintenanceWorkOrder::whereNotIn('status', ['Terminado', 'Anulado'])->count(),
                'critical' => MaintenanceWorkOrder::where('priority', 'Crítico')->count(),
                'finished' => MaintenanceWorkOrder::where('status', 'Terminado')->count(),
            ],
        ]);
    }

    public function workload(Request $request): JsonResponse
    {
        $from = trim((string) $request->query('from'));
        $to = trim((string) $request->query('to'));
        $assigneeFilter = trim((string) $request->query('assignee'));
        $dependencyId = $request->query('dependency_id');
        $priority = trim((string) $request->query('priority'));
        $status = trim((string) $request->query('status'));

        $today = Carbon::now()->startOfDay();
        $closedStatuses = ['Terminado', 'Anulado'];

        $workOrders = MaintenanceWorkOrder::query()
            ->when($from !== '', fn ($query) => $query->whereDate('reported_at', '>=', $from))
            ->when($to !== '', fn ($query) => $query->whereDate('reported_at', '<=', $to))
            ->when($dependencyId, fn ($query) => $query->where('maintenance_dependency_id', $dependencyId))
            ->when($priority !== '', fn ($query) => $query->where('priority', $priority))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->get(['id', 'assigned_to', 'status', 'priority', 'due_date', 'reported_at']);

        $rows = [];

        foreach ($workOrders as $workOrder) {
            $assignees = $this->parseAssignees($workOrder->assigned_to);
            if (!$assignees) {
                $assignees = ['Sin asignar'];
            }

            foreach ($assignees as $assignee) {
                if ($assigneeFilter !== '' && $assignee !== $assigneeFilter) {
                    continue;
                }

                if (!isset($rows[$assignee])) {
                    $rows[$assignee] = [
                        'assignee' => $assignee,
                        'assigned' => 0,
                        'pending' => 0,
                        'overdue' => 0,
                        'critical' => 0,
                        'closed' => 0,
                    ];
                }

                $rows[$assignee]['assigned']++;

                $isClosed = in_array($workOrder->status, $closedStatuses, true);
                if ($isClosed) {
                    $rows[$assignee]['closed']++;
                } else {
                    $rows[$assignee]['pending']++;
                }

                if (!$isClosed && $workOrder->priority === 'Crítico') {
                    $rows[$assignee]['critical']++;
                }

                if (!$isClosed && $workOrder->due_date) {
                    $due = $workOrder->due_date instanceof Carbon
                        ? $workOrder->due_date->startOfDay()
                        : Carbon::parse((string) $workOrder->due_date)->startOfDay();

                    if ($due->lt($today)) {
                        $rows[$assignee]['overdue']++;
                    }
                }
            }
        }

        ksort($rows);

        $rows = array_values($rows);

        $totals = [
            'assigned' => array_sum(array_column($rows, 'assigned')),
            'pending' => array_sum(array_column($rows, 'pending')),
            'overdue' => array_sum(array_column($rows, 'overdue')),
            'critical' => array_sum(array_column($rows, 'critical')),
            'closed' => array_sum(array_column($rows, 'closed')),
        ];

        return response()->json([
            'rows' => $rows,
            'totals' => $totals,
        ]);
    }

    public function assigneeReport(Request $request): JsonResponse
    {
        $assignee = trim((string) $request->query('assignee'));
        $from = trim((string) $request->query('from'));
        $to = trim((string) $request->query('to'));
        $dependencyId = $request->query('dependency_id');
        $priority = trim((string) $request->query('priority'));
        $status = trim((string) $request->query('status'));

        // No se deben incluir OTs terminadas/anuladas en el PDF del trabajador.
        $closedStatuses = ['Terminado', 'Anulado'];

        $query = MaintenanceWorkOrder::query()
            ->with('dependency:id,code,name,distribution,sector,zone,usage')
            ->when($from !== '', fn ($query) => $query->whereDate('reported_at', '>=', $from))
            ->when($to !== '', fn ($query) => $query->whereDate('reported_at', '<=', $to))
            ->when($dependencyId, fn ($query) => $query->where('maintenance_dependency_id', $dependencyId))
            ->when($priority !== '', fn ($query) => $query->where('priority', $priority))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->whereNotIn('status', $closedStatuses);

        if ($assignee !== '') {
            if ($assignee === 'Sin asignar') {
                $query->where(function ($query) {
                    $query->whereNull('assigned_to')->orWhere('assigned_to', '');
                });
            } else {
                $query->where('assigned_to', 'like', "%{$assignee}%");
            }
        }

        // Reporte imprimible: por defecto mostrar pendientes primero.
        $workOrders = $query
            ->orderByRaw("CASE WHEN status IN ('" . implode("','", $closedStatuses) . "') THEN 1 ELSE 0 END")
            ->orderByRaw("FIELD(priority, 'Crítico', 'Alta', 'Media', 'Baja')")
            ->orderByRaw("CASE WHEN due_date IS NULL THEN 1 ELSE 0 END")
            ->orderBy('due_date')
            ->orderByDesc('created_at')
            ->get();

        $payload = $workOrders->map(function (MaintenanceWorkOrder $workOrder) {
            return [
                'id' => $workOrder->id,
                'maintenance_dependency_id' => $workOrder->maintenance_dependency_id,
                'dependency' => $workOrder->dependency,
                'reported_at' => optional($workOrder->reported_at)->toDateString(),
                'due_date' => optional($workOrder->due_date)->toDateString(),
                'requested_by' => $workOrder->requested_by,
                'assigned_to' => $workOrder->assigned_to,
                'priority' => $workOrder->priority,
                'status' => $workOrder->status,
                'description' => $workOrder->description,
                'resolution_notes' => $workOrder->resolution_notes,
                'photo_reference' => $workOrder->photo_reference,
                'photo_url' => $workOrder->photo_url,
                'created_at' => optional($workOrder->created_at)->toISOString(),
            ];
        })->all();

        return response()->json([
            'data' => $this->sanitizeForJson($payload),
        ]);
    }

    private function validated(Request $request): array
    {
        $assignees = $this->assignees();
        $requesters = $this->requesters();

        $validated = $request->validate([
            'maintenance_dependency_id' => ['nullable', 'integer', 'exists:maintenance_dependencies,id'],
            'location_code' => ['nullable', 'string', 'max:255'],
            'location_distribution' => ['nullable', 'string', 'max:255'],
            'location_sector' => ['nullable', 'string', 'max:255'],
            'location_name' => ['nullable', 'string', 'max:255'],
            'location_usage' => ['nullable', 'string', 'max:255'],
            'reported_at' => ['nullable', 'date'],
            'requested_by' => ['nullable', 'string', 'max:255', Rule::in($requesters)],
            'assigned_to' => ['nullable', 'array'],
            'assigned_to.*' => ['nullable', 'string', 'max:255', Rule::in($assignees)],
            'priority' => ['required', 'string', Rule::in(['Crítico', 'Alta', 'Media', 'Baja'])],
            'status' => ['required', 'string', Rule::in(['Sin comenzar', 'En proceso', 'En espera', 'Pausado', 'Terminado', 'Anulado'])],
            'due_date' => ['nullable', 'date'],
            'description' => ['required', 'string'],
            'resolution_notes' => ['nullable', 'string'],
            'photo_reference' => ['nullable', 'string'],
            'photo' => ['nullable', 'file', 'image', 'max:5120'],
        ]);

        $assignedTo = Arr::wrap($validated['assigned_to'] ?? null);
        $assignedTo = collect($assignedTo)
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn ($value) => trim($value))
            ->unique()
            ->values()
            ->all();

        $validated['assigned_to'] = $assignedTo ? implode(', ', $assignedTo) : null;

        unset($validated['photo']);

        return $validated;
    }

    private function storePhoto($file, ?string $previous = null): string
    {
        $path = $file->store('maintenance/work-orders', 'public');

        if ($previous) {
            Storage::disk('public')->delete($previous);
        }

        return $path;
    }

    private function requesters(): array
    {
        return [
            'Pedro Nahuelpan',
            'Angelo Espinoza',
            'Laura Davinson',
            'Jeaqueline Sandoval',
        ];
    }

    private function assignees(): array
    {
        return [
            'Ivan',
            'Oscar',
            'Carlos cayul',
            'Laura davinson',
            'Lucia pailla',
            'Lucila valladares',
            'Llineth',
            'Maria paz',
            'Pilar cocio',
            'Sofia navarro',
            'Javier casas',
            'Ariel Villanueva',
            'Manuel Lara',
            'Pedro',
            'Jeaqueline sandoval',
        ];
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

    private function parseAssignees(?string $value): array
    {
        if (!$value) {
            return [];
        }

        return collect(explode(',', $value))
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function sanitizeForJson($value)
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->sanitizeForJson($item), $value);
        }

        if ($value instanceof \JsonSerializable) {
            return $this->sanitizeForJson($value->jsonSerialize());
        }

        if ($value instanceof \Illuminate\Support\Collection) {
            return $value->map(fn ($item) => $this->sanitizeForJson($item))->all();
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_string($value)) {
            if (function_exists('mb_check_encoding') && mb_check_encoding($value, 'UTF-8')) {
                return $value;
            }

            $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
            return $clean === false ? '' : $clean;
        }

        return $value;
    }
}
