<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\MaintenanceDependency;
use App\Models\MaintenanceWorkOrder;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MaintenanceWorkOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));
        $status = trim((string) $request->query('status'));
        $priority = trim((string) $request->query('priority'));
        $assignee = trim((string) $request->query('assignee'));
        $sort = trim((string) $request->query('sort', 'created'));
        $queue = trim((string) $request->query('queue'));

        $workOrders = MaintenanceWorkOrder::query()
            ->with([
                'dependency:id,code,name,distribution,sector,zone,usage',
                'technicalArea:id,code,name,parent_dependency_id,distribution,sector,zone,usage',
                'inventoryItem:id,code,name,dependency_id,status,condition',
                'closedByUser:id,name',
            ])
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
                        ->orWhere('dependency_component', 'like', "%{$search}%")
                        ->orWhereHas('dependency', function ($query) use ($search) {
                            $query
                                ->where('code', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%")
                                ->orWhere('distribution', 'like', "%{$search}%")
                                ->orWhere('sector', 'like', "%{$search}%")
                                ->orWhere('zone', 'like', "%{$search}%")
                                ->orWhere('usage', 'like', "%{$search}%");
                        })
                        ->orWhereHas('technicalArea', function ($query) use ($search) {
                            $query
                                ->where('code', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%")
                                ->orWhere('distribution', 'like', "%{$search}%")
                                ->orWhere('sector', 'like', "%{$search}%")
                                ->orWhere('zone', 'like', "%{$search}%");
                        })
                        ->orWhereHas('inventoryItem', function ($query) use ($search) {
                            $query
                                ->where('code', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($queue === 'active', fn ($query) => $query->whereNotIn('status', ['Terminado', 'Anulado']))
            ->when($queue === 'pending_closure', fn ($query) => $query->pendingClosure())
            ->when($queue === 'completed', fn ($query) => $query->closedWithNote())
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($priority !== '', fn ($query) => $query->where('priority', $priority))
            ->when($assignee !== '', fn ($query) => $this->whereAssignedTo($query, $assignee))
            ->when($sort === 'priority', function ($query) {
                $query
                    ->orderByRaw("FIELD(priority, 'Crítico', 'Alta', 'Media', 'Baja')")
                    ->orderByRaw("FIELD(status, 'Sin comenzar', 'En proceso', 'En espera', 'Pausado', 'Terminado', 'Anulado')")
                    ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('due_date')
                    ->orderByDesc('created_at');
            }, fn ($query) => $query->orderByDesc('created_at'))
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($workOrders);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validated($request);

        if ($payload['status'] === 'Terminado' && ! empty($payload['resolution_notes'])) {
            $payload['closed_at'] = now();
            $payload['closed_by_user_id'] = $request->user()?->id;
        } elseif ($payload['status'] === 'Terminado') {
            $payload['closed_at'] = null;
            $payload['closed_by_user_id'] = null;
        }

        if ($request->hasFile('photo')) {
            $payload['photo_reference'] = $this->storePhoto($request->file('photo'));
        }

        $workOrder = MaintenanceWorkOrder::create($payload);

        return response()->json([
            'message' => 'Orden de trabajo creada correctamente.',
            'data' => $workOrder->load([
                'dependency:id,code,name,distribution,sector,zone,usage',
                'technicalArea:id,code,name,parent_dependency_id,distribution,sector,zone,usage',
                'inventoryItem:id,code,name,dependency_id,status,condition',
                'closedByUser:id,name',
            ]),
        ], 201);
    }

    public function show(MaintenanceWorkOrder $maintenanceWorkOrder): JsonResponse
    {
        return response()->json([
            'data' => $maintenanceWorkOrder->load([
                'dependency:id,code,name,distribution,sector,zone,usage',
                'technicalArea:id,code,name,parent_dependency_id,distribution,sector,zone,usage',
                'inventoryItem:id,code,name,dependency_id,status,condition',
                'closedByUser:id,name',
            ]),
        ]);
    }

    public function update(Request $request, MaintenanceWorkOrder $maintenanceWorkOrder): JsonResponse
    {
        $payload = $this->validated($request);

        if ($payload['status'] === 'Terminado' && ! empty($payload['resolution_notes'])) {
            $payload['closed_at'] = $maintenanceWorkOrder->closed_at ?: now();
            $payload['closed_by_user_id'] = $maintenanceWorkOrder->closed_by_user_id ?: $request->user()?->id;
        } elseif ($payload['status'] === 'Terminado') {
            $payload['closed_at'] = null;
            $payload['closed_by_user_id'] = null;
        } elseif ($maintenanceWorkOrder->status === 'Terminado') {
            $payload['closed_at'] = null;
            $payload['closed_by_user_id'] = null;
        }

        if ($request->hasFile('photo')) {
            $payload['photo_reference'] = $this->storePhoto($request->file('photo'), $maintenanceWorkOrder->photo_reference);
        }

        $maintenanceWorkOrder->update($payload);

        return response()->json([
            'message' => 'Orden de trabajo actualizada correctamente.',
            'data' => $maintenanceWorkOrder->load([
                'dependency:id,code,name,distribution,sector,zone,usage',
                'technicalArea:id,code,name,parent_dependency_id,distribution,sector,zone,usage',
                'inventoryItem:id,code,name,dependency_id,status,condition',
                'closedByUser:id,name',
            ]),
        ]);
    }

    public function requestClosure(Request $request, MaintenanceWorkOrder $maintenanceWorkOrder): JsonResponse
    {
        if (in_array($maintenanceWorkOrder->status, ['Terminado', 'Anulado'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Una OT terminada o anulada no puede enviarse a cierre.',
            ]);
        }

        $payload = ['status' => 'Terminado'];

        if ($maintenanceWorkOrder->hasClosureNote()) {
            $payload['closed_at'] = $maintenanceWorkOrder->closed_at ?: now();
            $payload['closed_by_user_id'] = $maintenanceWorkOrder->closed_by_user_id ?: $request->user()?->id;
        } else {
            $payload['closed_at'] = null;
            $payload['closed_by_user_id'] = null;
        }

        $maintenanceWorkOrder->update($payload);

        return response()->json([
            'message' => "La OT #{$maintenanceWorkOrder->id} quedó pendiente de cierre.",
            'data' => $maintenanceWorkOrder->load([
                'dependency:id,code,name,distribution,sector,zone,usage',
                'technicalArea:id,code,name,parent_dependency_id,distribution,sector,zone,usage',
                'inventoryItem:id,code,name,dependency_id,status,condition',
                'closedByUser:id,name',
            ]),
        ]);
    }

    public function close(Request $request, MaintenanceWorkOrder $maintenanceWorkOrder): JsonResponse
    {
        if ($maintenanceWorkOrder->status !== 'Terminado' || $maintenanceWorkOrder->hasClosureNote()) {
            throw ValidationException::withMessages([
                'status' => 'La OT debe estar terminada y sin nota para registrar su cierre.',
            ]);
        }

        $validated = $request->validate([
            'resolution_notes' => ['required', 'string', 'max:10000'],
            'closure_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $payload = [
            'status' => 'Terminado',
            'resolution_notes' => trim($validated['resolution_notes']),
            'closed_at' => now(),
            'closed_by_user_id' => $request->user()?->id,
        ];

        if ($request->hasFile('closure_document')) {
            $document = $request->file('closure_document');
            $payload['closure_document_reference'] = $this->storeClosureDocument(
                $document,
                $maintenanceWorkOrder->closure_document_reference
            );
            $payload['closure_document_original_name'] = Str::limit($document->getClientOriginalName(), 255, '');
        }

        $maintenanceWorkOrder->update($payload);

        return response()->json([
            'message' => "La OT #{$maintenanceWorkOrder->id} fue cerrada correctamente.",
            'data' => $maintenanceWorkOrder->load([
                'dependency:id,code,name,distribution,sector,zone,usage',
                'technicalArea:id,code,name,parent_dependency_id,distribution,sector,zone,usage',
                'inventoryItem:id,code,name,dependency_id,status,condition',
                'closedByUser:id,name',
            ]),
        ]);
    }

    public function destroy(MaintenanceWorkOrder $maintenanceWorkOrder): JsonResponse
    {
        if (MaintenanceWorkOrder::isManagedPhotoReference($maintenanceWorkOrder->photo_reference)) {
            Storage::disk('public')->delete($maintenanceWorkOrder->photo_reference);
        }

        if (MaintenanceWorkOrder::isManagedClosureDocumentReference($maintenanceWorkOrder->closure_document_reference)) {
            Storage::disk('public')->delete($maintenanceWorkOrder->closure_document_reference);
        }

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
            'maintenance_assignees' => $this->maintenanceAssigneeCatalog(),
            'requesters' => $this->requesters(),
            'dependency_components' => $this->dependencyComponents(),
            'dependencies' => MaintenanceDependency::query()
                ->maintenanceLocations()
                ->where('active', true)
                ->orderBy('code')
                ->get([
                    'id',
                    'code',
                    'name',
                    'distribution',
                    'sector',
                    'zone',
                    'usage',
                    'is_maintenance_location',
                ]),
            'technical_areas' => MaintenanceDependency::query()
                ->technicalAssets()
                ->where('active', true)
                ->with('parentDependency:id,code,name')
                ->orderBy('code')
                ->get([
                    'id',
                    'parent_dependency_id',
                    'code',
                    'name',
                    'distribution',
                    'sector',
                    'zone',
                    'usage',
                    'active',
                ]),
            'inventory_items' => InventoryItem::query()
                ->where('active', true)
                ->with('dependency:id,code,name')
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'dependency_id', 'status', 'condition']),
            'summary' => [
                'total' => MaintenanceWorkOrder::count(),
                'open' => MaintenanceWorkOrder::whereNotIn('status', ['Terminado', 'Anulado'])->count(),
                'critical' => MaintenanceWorkOrder::where('priority', 'Crítico')->count(),
                'pending_closure' => MaintenanceWorkOrder::pendingClosure()->count(),
                'finished' => MaintenanceWorkOrder::closedWithNote()->count(),
            ],
        ]);
    }

    public function workload(Request $request): JsonResponse
    {
        $from = trim((string) $request->query('from'));
        $to = trim((string) $request->query('to'));
        $assigneeFilter = trim((string) $request->query('assignee'));
        $dependencyId = $request->integer('dependency_id');
        $priority = trim((string) $request->query('priority'));
        $status = trim((string) $request->query('status'));

        $today = Carbon::now()->startOfDay();
        $closedStatuses = ['Terminado', 'Anulado'];
        $assigneeCatalog = collect($this->maintenanceAssigneeCatalog());

        $workOrders = MaintenanceWorkOrder::query()
            ->when($from !== '', fn ($query) => $query->whereDate('reported_at', '>=', $from))
            ->when($to !== '', fn ($query) => $query->whereDate('reported_at', '<=', $to))
            ->when($dependencyId > 0, fn (Builder $query) => $this->whereLocatedInDependency($query, $dependencyId))
            ->when($priority !== '', fn ($query) => $query->where('priority', $priority))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->get(['id', 'assigned_to', 'status', 'priority', 'due_date', 'reported_at']);

        $rows = [];
        $shouldShowUnassigned = $assigneeFilter === 'Sin asignar';

        foreach ($assigneeCatalog as $assignee) {
            $name = $assignee['value'] ?? $assignee['full_name'] ?? null;

            if (! $name) {
                continue;
            }

            if ($assigneeFilter !== '' && $assigneeFilter !== $name) {
                continue;
            }

            $rows[$name] = [
                'assignee' => $name,
                'assignee_label' => $assignee['label'] ?? $name,
                'staff_id' => $assignee['id'] ?? null,
                'rut' => $assignee['rut'] ?? null,
                'maintenance_role' => $assignee['maintenance_role'] ?? null,
                'maintenance_role_label' => $assignee['maintenance_role_label'] ?? null,
                'assigned' => 0,
                'pending' => 0,
                'overdue' => 0,
                'critical' => 0,
                'closed' => 0,
            ];
        }

        if ($shouldShowUnassigned) {
            $rows['Sin asignar'] = [
                'assignee' => 'Sin asignar',
                'assignee_label' => 'Sin asignar',
                'staff_id' => null,
                'rut' => null,
                'maintenance_role' => null,
                'maintenance_role_label' => null,
                'assigned' => 0,
                'pending' => 0,
                'overdue' => 0,
                'critical' => 0,
                'closed' => 0,
            ];
        }

        foreach ($workOrders as $workOrder) {
            $assignees = $this->parseAssignees($workOrder->assigned_to);
            if (! $assignees && $shouldShowUnassigned) {
                $assignees = ['Sin asignar'];
            }

            foreach ($assignees as $assignee) {
                if (! isset($rows[$assignee])) {
                    continue;
                }

                $rows[$assignee]['assigned']++;

                $isClosed = in_array($workOrder->status, $closedStatuses, true);
                if ($isClosed) {
                    $rows[$assignee]['closed']++;
                } else {
                    $rows[$assignee]['pending']++;
                }

                if (! $isClosed && $workOrder->priority === 'Crítico') {
                    $rows[$assignee]['critical']++;
                }

                if (! $isClosed && $workOrder->due_date) {
                    $due = $workOrder->due_date instanceof Carbon
                        ? $workOrder->due_date->startOfDay()
                        : Carbon::parse((string) $workOrder->due_date)->startOfDay();

                    if ($due->lt($today)) {
                        $rows[$assignee]['overdue']++;
                    }
                }
            }
        }

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
        $dependencyId = $request->integer('dependency_id');
        $priority = trim((string) $request->query('priority'));
        $status = trim((string) $request->query('status'));
        $allowedAssignees = $this->assignees();

        // No se deben incluir OTs terminadas/anuladas en el PDF del trabajador.
        $closedStatuses = ['Terminado', 'Anulado'];

        if ($assignee !== '' && $assignee !== 'Sin asignar' && ! in_array($assignee, $allowedAssignees, true)) {
            return response()->json([
                'data' => [],
            ]);
        }

        $query = MaintenanceWorkOrder::query()
            ->with([
                'dependency:id,code,name,distribution,sector,zone,usage',
                'technicalArea:id,code,name,parent_dependency_id,distribution,sector,zone,usage',
                'inventoryItem:id,code,name,dependency_id,status,condition',
            ])
            ->when($from !== '', fn ($query) => $query->whereDate('reported_at', '>=', $from))
            ->when($to !== '', fn ($query) => $query->whereDate('reported_at', '<=', $to))
            ->when($dependencyId > 0, fn (Builder $query) => $this->whereLocatedInDependency($query, $dependencyId))
            ->when($priority !== '', fn ($query) => $query->where('priority', $priority))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->whereNotIn('status', $closedStatuses);

        if ($assignee !== '') {
            if ($assignee === 'Sin asignar') {
                $query->where(function ($query) {
                    $query->whereNull('assigned_to')->orWhere('assigned_to', '');
                });
            } else {
                $this->whereAssignedTo($query, $assignee);
            }
        }

        // Reporte imprimible: por defecto mostrar pendientes primero.
        $workOrders = $query
            ->orderByRaw("CASE WHEN status IN ('".implode("','", $closedStatuses)."') THEN 1 ELSE 0 END")
            ->orderByRaw("CASE priority WHEN 'Crítico' THEN 1 WHEN 'Alta' THEN 2 WHEN 'Media' THEN 3 WHEN 'Baja' THEN 4 ELSE 5 END")
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_date')
            ->orderByDesc('created_at')
            ->get();

        $payload = $workOrders->map(function (MaintenanceWorkOrder $workOrder) {
            return [
                'id' => $workOrder->id,
                'maintenance_dependency_id' => $workOrder->maintenance_dependency_id,
                'technical_area_id' => $workOrder->technical_area_id,
                'inventory_item_id' => $workOrder->inventory_item_id,
                'dependency_component' => $workOrder->dependency_component,
                'dependency' => $workOrder->dependency,
                'technical_area' => $workOrder->technicalArea,
                'inventory_item' => $workOrder->inventoryItem,
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
        $assignees = $this->allowedAssignees($request);
        $requesters = $this->requesters();

        $validated = $request->validate([
            'maintenance_dependency_id' => [
                'nullable',
                'integer',
                Rule::exists('maintenance_dependencies', 'id')
                    ->where('dependency_kind', MaintenanceDependency::KIND_SPACE)
                    ->where('is_maintenance_location', true)
                    ->where('active', true),
            ],
            'technical_area_id' => [
                'nullable',
                'integer',
                Rule::exists('maintenance_dependencies', 'id')
                    ->where('dependency_kind', MaintenanceDependency::KIND_TECHNICAL_ASSET)
                    ->where('active', true),
            ],
            'dependency_component' => ['nullable', 'string', 'max:255'],
            'inventory_item_id' => ['nullable', 'integer', Rule::exists('inventory_items', 'id')->where('active', true)],
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
            'photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,gif,bmp,webp', 'max:5120'],
        ]);

        $assignedTo = Arr::wrap($validated['assigned_to'] ?? null);
        $assignedTo = collect($assignedTo)
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn ($value) => trim($value))
            ->unique()
            ->values()
            ->all();

        $validated['assigned_to'] = $assignedTo ? implode(', ', $assignedTo) : null;
        $validated['dependency_component'] = isset($validated['dependency_component'])
            ? trim((string) $validated['dependency_component'])
            : null;
        $validated['dependency_component'] = $validated['dependency_component'] !== ''
            ? $validated['dependency_component']
            : null;
        $validated['resolution_notes'] = isset($validated['resolution_notes'])
            ? trim((string) $validated['resolution_notes'])
            : null;
        $validated['resolution_notes'] = $validated['resolution_notes'] !== ''
            ? $validated['resolution_notes']
            : null;

        if (! empty($validated['technical_area_id'])) {
            $technicalArea = MaintenanceDependency::query()
                ->technicalAssets()
                ->whereKey($validated['technical_area_id'])
                ->first(['id', 'parent_dependency_id']);

            if ($technicalArea?->parent_dependency_id) {
                if (! empty($validated['maintenance_dependency_id'])
                    && (int) $technicalArea->parent_dependency_id !== (int) $validated['maintenance_dependency_id']) {
                    throw ValidationException::withMessages([
                        'technical_area_id' => 'El área técnica seleccionada no pertenece a la dependencia indicada.',
                    ]);
                }

                if (empty($validated['maintenance_dependency_id'])) {
                    $validated['maintenance_dependency_id'] = $technicalArea->parent_dependency_id;
                }
            }
        }

        if (! empty($validated['inventory_item_id']) && empty($validated['maintenance_dependency_id'])) {
            $validated['maintenance_dependency_id'] = InventoryItem::query()
                ->whereKey($validated['inventory_item_id'])
                ->value('dependency_id');
        }

        if (! empty($validated['inventory_item_id']) && ! empty($validated['maintenance_dependency_id'])) {
            $inventoryDependencyId = InventoryItem::query()
                ->whereKey($validated['inventory_item_id'])
                ->value('dependency_id');

            if ($inventoryDependencyId && (int) $inventoryDependencyId !== (int) $validated['maintenance_dependency_id']) {
                throw ValidationException::withMessages([
                    'inventory_item_id' => 'El bien inventariado seleccionado no pertenece a la dependencia indicada.',
                ]);
            }
        }

        unset($validated['photo']);

        return $validated;
    }

    private function storePhoto(UploadedFile $file, ?string $previous = null): string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
            throw ValidationException::withMessages([
                'photo' => 'El servidor no dispone del procesador de imágenes requerido.',
            ]);
        }

        $contents = $file->get();
        $source = @imagecreatefromstring($contents);

        if (! $source instanceof \GdImage) {
            throw ValidationException::withMessages([
                'photo' => 'La foto no se pudo procesar. Usa una imagen JPG, PNG, GIF, BMP o WebP válida.',
            ]);
        }

        $source = $this->applyPhotoOrientation($source, $file);
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $maxDimension = 2400;
        $scale = min(1, $maxDimension / max($sourceWidth, $sourceHeight));
        $targetWidth = max(1, (int) round($sourceWidth * $scale));
        $targetHeight = max(1, (int) round($sourceHeight * $scale));
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        if (! $canvas instanceof \GdImage) {
            imagedestroy($source);

            throw ValidationException::withMessages([
                'photo' => 'No se pudo preparar la foto para su almacenamiento.',
            ]);
        }

        try {
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefill($canvas, 0, 0, $white);
            imagecopyresampled(
                $canvas,
                $source,
                0,
                0,
                0,
                0,
                $targetWidth,
                $targetHeight,
                $sourceWidth,
                $sourceHeight
            );

            ob_start();
            $encoded = imagejpeg($canvas, null, 88);
            $jpeg = ob_get_clean();
        } finally {
            imagedestroy($canvas);
            imagedestroy($source);
        }

        if (! $encoded || ! is_string($jpeg) || $jpeg === '') {
            throw ValidationException::withMessages([
                'photo' => 'No se pudo convertir la foto a un formato compatible.',
            ]);
        }

        $path = 'maintenance/work-orders/'.Str::uuid().'.jpg';

        if (! Storage::disk('public')->put($path, $jpeg, ['visibility' => 'public'])) {
            throw ValidationException::withMessages([
                'photo' => 'No se pudo guardar la foto. Intenta nuevamente.',
            ]);
        }

        if (MaintenanceWorkOrder::isManagedPhotoReference($previous)) {
            Storage::disk('public')->delete($previous);
        }

        return $path;
    }

    private function storeClosureDocument(UploadedFile $file, ?string $previous = null): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $path = $file->storeAs(
            'maintenance/work-orders/closures',
            Str::uuid().'.'.$extension,
            'public'
        );

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages([
                'closure_document' => 'No se pudo guardar el acta o evidencia de cierre.',
            ]);
        }

        if (MaintenanceWorkOrder::isManagedClosureDocumentReference($previous)) {
            Storage::disk('public')->delete($previous);
        }

        return $path;
    }

    private function applyPhotoOrientation(\GdImage $image, UploadedFile $file): \GdImage
    {
        if (! function_exists('exif_read_data') || $file->getMimeType() !== 'image/jpeg') {
            return $image;
        }

        $exif = @exif_read_data($file->getRealPath());
        $orientation = (int) ($exif['Orientation'] ?? 1);
        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        if (! $rotated instanceof \GdImage) {
            return $image;
        }

        imagedestroy($image);

        return $rotated;
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

    private function dependencyComponents(): array
    {
        return [
            'Ventana',
            'Ventanas',
            'Puerta',
            'Puertas',
            'Pared',
            'Paredes',
            'Piso',
            'Techo',
            'Cielo',
            'Luminaria',
            'Interruptor',
            'Enchufe',
            'Cerradura',
            'Manilla',
            'Bisagra',
            'Vidrio',
            'Cortina',
            'Persiana',
            'Mueble fijo',
            'Estante',
            'Mesón',
            'Lavamanos',
            'Llave de agua',
            'Baño',
            'Canaleta',
            'Rejilla',
            'Baranda',
            'Escalera',
            'Calefacción',
            'Radiador',
        ];
    }

    private function assignees(): array
    {
        return $this->maintenanceAssigneeQuery()
            ->pluck('full_name')
            ->values()
            ->all();
    }

    private function allowedAssignees(Request $request): array
    {
        $assignees = $this->assignees();

        $currentWorkOrder = collect($request->route()?->parameters() ?? [])
            ->first(fn ($parameter) => $parameter instanceof MaintenanceWorkOrder);

        if ($currentWorkOrder instanceof MaintenanceWorkOrder) {
            $assignees = array_merge($assignees, $this->parseAssignees($currentWorkOrder->assigned_to));
        }

        return collect($assignees)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function maintenanceAssigneeQuery(): Builder
    {
        return Staff::query()
            ->with('cargo:id,name,slug')
            ->where('active', true)
            ->where('can_receive_maintenance_orders', true)
            ->orderBy('full_name');
    }

    private function maintenanceAssigneeCatalog(): array
    {
        return $this->maintenanceAssigneeQuery()
            ->get([
                'id',
                'full_name',
                'rut',
                'cargo_id',
                'maintenance_role',
                'can_receive_maintenance_orders',
            ])
            ->map(fn (Staff $staff) => [
                'id' => $staff->id,
                'full_name' => $staff->full_name,
                'rut' => $staff->rut,
                'cargo' => $staff->cargo ? [
                    'id' => $staff->cargo->id,
                    'name' => $staff->cargo->name,
                    'slug' => $staff->cargo->slug,
                ] : null,
                'maintenance_role' => $staff->maintenance_role,
                'maintenance_role_label' => $staff->maintenance_role_label,
                'label' => trim(sprintf(
                    '%s%s',
                    $staff->full_name,
                    $staff->maintenance_role_label ? ' · '.$staff->maintenance_role_label : ''
                )),
                'value' => $staff->full_name,
            ])
            ->values()
            ->all();
    }

    private function whereAssignedTo(Builder $query, string $assignee): Builder
    {
        return $query->where(function (Builder $query) use ($assignee) {
            $query
                ->where('assigned_to', $assignee)
                ->orWhere('assigned_to', 'like', "{$assignee},%")
                ->orWhere('assigned_to', 'like', "%, {$assignee}")
                ->orWhere('assigned_to', 'like', "%, {$assignee},%");
        });
    }

    private function whereLocatedInDependency(Builder $query, int $dependencyId): Builder
    {
        return $query->where(function (Builder $query) use ($dependencyId) {
            $query
                // Si la OT está asociada a un bien, prevalece su ubicación actual.
                ->whereHas('inventoryItem', function (Builder $inventoryItems) use ($dependencyId) {
                    $inventoryItems->where('dependency_id', $dependencyId);
                })
                // Las OT generales conservan como ubicación la dependencia de la orden.
                ->orWhere(function (Builder $workOrders) use ($dependencyId) {
                    $workOrders
                        ->whereDoesntHave('inventoryItem')
                        ->where('maintenance_dependency_id', $dependencyId);
                });
        });
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
        if (! $value) {
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

        if ($value instanceof Collection) {
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
