<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\MaintenanceDependency;
use App\Models\MaintenanceEvidencePhoto;
use App\Models\MaintenanceWorkOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class MaintenanceWorkOrderController extends Controller
{
    private const MAX_EVIDENCE_PHOTOS = 3;

    private const EXPLICIT_ASSIGNEE_NAME_TERMS = [
        ['Sebastian', 'Matamala'],
    ];

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
                'createdByUser:id,name',
                'assigneeUsers:id,name',
                'evidencePhotos:id,maintenance_work_order_id,path,original_name,mime_type,size_bytes,created_at',
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
        $photoFiles = $this->uploadedPhotoFiles($request);
        $assignedUserIds = Arr::pull($payload, 'assigned_user_ids', []);
        $actor = $request->user();

        $payload['created_by_user_id'] = $actor?->id;
        $payload['requested_by'] = Str::squish((string) $actor?->name) ?: null;

        if ($payload['status'] === 'Terminado' && ! empty($payload['resolution_notes'])) {
            $payload['closed_at'] = now();
            $payload['closed_by_user_id'] = $request->user()?->id;
        } elseif ($payload['status'] === 'Terminado') {
            $payload['closed_at'] = null;
            $payload['closed_by_user_id'] = null;
        }

        $referenceCount = trim((string) ($payload['photo_reference'] ?? '')) !== '' ? 1 : 0;
        if ($referenceCount + $photoFiles->count() > self::MAX_EVIDENCE_PHOTOS) {
            throw ValidationException::withMessages([
                'photos' => 'Cada OT admite un máximo de 3 fotografías.',
            ]);
        }

        $storedPhotos = $this->storePhotoBatch($photoFiles);

        if ($storedPhotos !== [] && empty($payload['photo_reference'])) {
            $payload['photo_reference'] = $storedPhotos[0]['path'];
        }

        try {
            $workOrder = DB::transaction(function () use ($actor, $assignedUserIds, $payload, $storedPhotos) {
                $workOrder = MaintenanceWorkOrder::create($payload);
                $this->persistEvidencePhotos($workOrder, $storedPhotos, $actor?->id);
                $this->syncAssigneeUsers($workOrder, $assignedUserIds);

                return $workOrder;
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete(collect($storedPhotos)->pluck('path')->all());
            throw $exception;
        }

        return response()->json([
            'message' => 'Orden de trabajo creada correctamente.',
            'data' => $workOrder->load([
                'dependency:id,code,name,distribution,sector,zone,usage',
                'technicalArea:id,code,name,parent_dependency_id,distribution,sector,zone,usage',
                'inventoryItem:id,code,name,dependency_id,status,condition',
                'closedByUser:id,name',
                'createdByUser:id,name',
                'assigneeUsers:id,name',
                'evidencePhotos:id,maintenance_work_order_id,path,original_name,mime_type,size_bytes,created_at',
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
                'createdByUser:id,name',
                'assigneeUsers:id,name',
                'evidencePhotos:id,maintenance_work_order_id,path,original_name,mime_type,size_bytes,created_at',
            ]),
        ]);
    }

    public function update(Request $request, MaintenanceWorkOrder $maintenanceWorkOrder): JsonResponse
    {
        $payload = $this->validated($request);
        $photoFiles = $this->uploadedPhotoFiles($request);
        $shouldSyncAssignees = array_key_exists('assigned_user_ids', $payload);
        $assignedUserIds = Arr::pull($payload, 'assigned_user_ids', []);
        $creatorName = $maintenanceWorkOrder->createdByUser?->name;

        $payload['requested_by'] = $maintenanceWorkOrder->requested_by
            ?: (Str::squish((string) $creatorName) ?: null);

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

        $currentPhotoCount = $this->workOrderPhotoCount($maintenanceWorkOrder);
        if ($currentPhotoCount + $photoFiles->count() > self::MAX_EVIDENCE_PHOTOS) {
            throw ValidationException::withMessages([
                'photos' => "La OT ya tiene {$currentPhotoCount} fotografía(s). El máximo permitido es 3.",
            ]);
        }

        $storedPhotos = $this->storePhotoBatch($photoFiles);
        if ($storedPhotos !== [] && ! trim((string) $maintenanceWorkOrder->photo_reference)) {
            $payload['photo_reference'] = $storedPhotos[0]['path'];
        }

        try {
            DB::transaction(function () use (
                $assignedUserIds,
                $maintenanceWorkOrder,
                $payload,
                $request,
                $shouldSyncAssignees,
                $storedPhotos
            ): void {
                $lockedWorkOrder = MaintenanceWorkOrder::query()
                    ->lockForUpdate()
                    ->findOrFail($maintenanceWorkOrder->id);
                $lockedPhotoCount = $this->workOrderPhotoCount($lockedWorkOrder);

                if ($lockedPhotoCount + count($storedPhotos) > self::MAX_EVIDENCE_PHOTOS) {
                    throw ValidationException::withMessages([
                        'photos' => 'La OT alcanzó el máximo de 3 fotografías.',
                    ]);
                }

                $lockedWorkOrder->update($payload);
                $this->persistEvidencePhotos($lockedWorkOrder, $storedPhotos, $request->user()?->id);

                if ($shouldSyncAssignees) {
                    $this->syncAssigneeUsers($lockedWorkOrder, $assignedUserIds);
                }
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete(collect($storedPhotos)->pluck('path')->all());
            throw $exception;
        }

        return response()->json([
            'message' => 'Orden de trabajo actualizada correctamente.',
            'data' => $maintenanceWorkOrder->load([
                'dependency:id,code,name,distribution,sector,zone,usage',
                'technicalArea:id,code,name,parent_dependency_id,distribution,sector,zone,usage',
                'inventoryItem:id,code,name,dependency_id,status,condition',
                'closedByUser:id,name',
                'createdByUser:id,name',
                'assigneeUsers:id,name',
                'evidencePhotos:id,maintenance_work_order_id,path,original_name,mime_type,size_bytes,created_at',
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
                'createdByUser:id,name',
                'assigneeUsers:id,name',
                'evidencePhotos:id,maintenance_work_order_id,path,original_name,mime_type,size_bytes,created_at',
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
                'createdByUser:id,name',
                'assigneeUsers:id,name',
                'evidencePhotos:id,maintenance_work_order_id,path,original_name,mime_type,size_bytes,created_at',
            ]),
        ]);
    }

    public function deletePhoto(
        MaintenanceWorkOrder $maintenanceWorkOrder,
        MaintenanceEvidencePhoto $photo
    ): JsonResponse
    {
        abort_unless((int) $photo->maintenance_work_order_id === (int) $maintenanceWorkOrder->id, 404);

        $path = $photo->path;

        DB::transaction(function () use ($maintenanceWorkOrder, $path, $photo): void {
            $photo->delete();

            if ($maintenanceWorkOrder->photo_reference !== $path) {
                return;
            }

            $replacement = $maintenanceWorkOrder->evidencePhotos()->oldest('id')->value('path');
            $maintenanceWorkOrder->update(['photo_reference' => $replacement]);
        });

        Storage::disk('public')->delete($path);

        return response()->json([
            'message' => 'Fotografía eliminada correctamente.',
            'data' => $maintenanceWorkOrder->fresh()->load([
                'dependency:id,code,name,distribution,sector,zone,usage',
                'technicalArea:id,code,name,parent_dependency_id,distribution,sector,zone,usage',
                'inventoryItem:id,code,name,dependency_id,status,condition',
                'closedByUser:id,name',
                'createdByUser:id,name',
                'assigneeUsers:id,name',
                'evidencePhotos:id,maintenance_work_order_id,path,original_name,mime_type,size_bytes,created_at',
            ]),
        ]);
    }

    public function destroy(MaintenanceWorkOrder $maintenanceWorkOrder): JsonResponse
    {
        $maintenanceWorkOrder->load('evidencePhotos');
        $standalonePhotos = $maintenanceWorkOrder->evidencePhotos
            ->whereNull('maintenance_visit_checklist_response_id');
        $checklistPaths = $maintenanceWorkOrder->evidencePhotos
            ->whereNotNull('maintenance_visit_checklist_response_id')
            ->pluck('path');
        $photoPathsToDelete = $standalonePhotos->pluck('path');

        if (MaintenanceWorkOrder::isManagedPhotoReference($maintenanceWorkOrder->photo_reference)
            && ! $checklistPaths->contains($maintenanceWorkOrder->photo_reference)) {
            $photoPathsToDelete->push($maintenanceWorkOrder->photo_reference);
        }

        if (MaintenanceWorkOrder::isManagedClosureDocumentReference($maintenanceWorkOrder->closure_document_reference)) {
            Storage::disk('public')->delete($maintenanceWorkOrder->closure_document_reference);
        }

        DB::transaction(function () use ($maintenanceWorkOrder, $standalonePhotos): void {
            MaintenanceEvidencePhoto::query()->whereKey($standalonePhotos->pluck('id'))->delete();
            $maintenanceWorkOrder->delete();
        });

        Storage::disk('public')->delete($photoPathsToDelete->filter()->unique()->values()->all());

        return response()->json([
            'message' => 'Orden de trabajo eliminada correctamente.',
        ]);
    }

    public function catalogs(Request $request): JsonResponse
    {
        $catalogs = [
            'priorities' => ['Crítico', 'Alta', 'Media', 'Baja'],
            'statuses' => ['Sin comenzar', 'En proceso', 'En espera', 'Pausado', 'Terminado', 'Anulado'],
            'assignees' => $this->assignees(),
            'maintenance_assignees' => $this->maintenanceAssigneeCatalog(),
            'requesters' => $this->requesters(),
            'current_user' => [
                'id' => $request->user()?->id,
                'name' => $request->user()?->name,
            ],
            'dependency_components' => $this->dependencyComponents(),
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
        ];

        // Mantiene compatibilidad con otros consumidores, mientras el formulario
        // de OT puede omitir este bloque y usar el buscador liviano dedicado.
        if ($request->boolean('include_dependencies', true)) {
            $catalogs['dependencies'] = $this->maintenanceDependencyQuery()
                ->orderBy('code')
                ->get($this->maintenanceDependencyColumns());
        }

        return response()->json($catalogs);
    }

    public function dependencyOptions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'selected_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $search = Str::squish((string) ($validated['search'] ?? ''));
        $selectedId = (int) ($validated['selected_id'] ?? 0);
        $limit = 40;
        $terms = collect(preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY))
            ->take(5)
            ->values();

        $baseQuery = $this->maintenanceDependencyQuery();
        $query = clone $baseQuery;

        foreach ($terms as $term) {
            $query->where(function (Builder $query) use ($term) {
                $like = "%{$term}%";

                $query
                    ->where('code', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('distribution', 'like', $like)
                    ->orWhere('sector', 'like', $like)
                    ->orWhere('zone', 'like', $like)
                    ->orWhere('usage', 'like', $like);
            });
        }

        if ($search !== '') {
            $query->orderByRaw(
                'CASE WHEN code LIKE ? THEN 0 WHEN name LIKE ? THEN 1 ELSE 2 END',
                ["{$search}%", "{$search}%"]
            );
        }

        $dependencies = $query
            ->orderBy('code')
            ->limit($limit + 1)
            ->get($this->maintenanceDependencyColumns());
        $hasMore = $dependencies->count() > $limit;
        $dependencies = $dependencies->take($limit)->values();

        // Una OT en edición siempre debe poder mostrar su selección, aunque no
        // quede dentro de los primeros resultados de la búsqueda actual.
        if ($selectedId > 0 && ! $dependencies->contains('id', $selectedId)) {
            $selected = (clone $baseQuery)
                ->whereKey($selectedId)
                ->first($this->maintenanceDependencyColumns());

            if ($selected) {
                $dependencies->prepend($selected);
                $dependencies = $dependencies->take($limit)->values();
            }
        }

        return response()->json([
            'data' => $dependencies,
            'meta' => [
                'query' => $search,
                'limit' => $limit,
                'has_more' => $hasMore,
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

    private function maintenanceDependencyQuery(): Builder
    {
        return MaintenanceDependency::query()
            ->maintenanceLocations()
            ->where('active', true);
    }

    private function maintenanceDependencyColumns(): array
    {
        return [
            'id',
            'code',
            'name',
            'distribution',
            'sector',
            'zone',
            'usage',
            'is_maintenance_location',
        ];
    }

    private function validated(Request $request): array
    {
        $assigneeCatalog = collect($this->maintenanceAssigneeCatalog());
        $assignees = $this->allowedAssignees($request, $assigneeCatalog->pluck('value')->all());
        $assigneeUserIds = $assigneeCatalog->pluck('user_id')->filter()->map(fn ($id) => (int) $id)->all();
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
            'requested_by' => ['nullable', 'string', 'max:255'],
            'sync_assignees' => ['nullable', 'boolean'],
            'assigned_user_ids' => ['nullable', 'array'],
            'assigned_user_ids.*' => ['nullable', 'integer', Rule::in($assigneeUserIds)],
            'assigned_to' => ['nullable', 'array'],
            'assigned_to.*' => ['nullable', 'string', 'max:255', Rule::in($assignees)],
            'priority' => ['required', 'string', Rule::in(['Crítico', 'Alta', 'Media', 'Baja'])],
            'status' => ['required', 'string', Rule::in(['Sin comenzar', 'En proceso', 'En espera', 'Pausado', 'Terminado', 'Anulado'])],
            'due_date' => ['nullable', 'date'],
            'description' => ['required', 'string'],
            'resolution_notes' => ['nullable', 'string'],
            'photo_reference' => ['nullable', 'string'],
            'photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,gif,bmp,webp', 'max:5120'],
            'photos' => ['nullable', 'array', 'max:3'],
            'photos.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,gif,bmp,webp', 'max:5120'],
        ]);

        $shouldSyncAssignees = $request->boolean('sync_assignees') || $request->has('assigned_user_ids');

        if ($shouldSyncAssignees) {
            $assignedUserIds = collect(Arr::wrap($validated['assigned_user_ids'] ?? null))
                ->filter(fn ($value) => is_numeric($value))
                ->map(fn ($value) => (int) $value)
                ->unique()
                ->values();
            $namesByUserId = $assigneeCatalog->keyBy(fn ($item) => (int) $item['user_id']);
            $assignedNames = $assignedUserIds
                ->map(fn ($userId) => $namesByUserId->get($userId)['value'] ?? null)
                ->filter()
                ->values();

            $validated['assigned_user_ids'] = $assignedUserIds->all();
            $validated['assigned_to'] = $assignedNames->isNotEmpty()
                ? $assignedNames->implode(', ')
                : null;
        } elseif ($request->has('assigned_to')) {
            $assignedTo = collect(Arr::wrap($validated['assigned_to'] ?? null))
                ->filter(fn ($value) => is_string($value) && trim($value) !== '')
                ->map(fn ($value) => trim($value))
                ->unique()
                ->values()
                ->all();

            $validated['assigned_to'] = $assignedTo ? implode(', ', $assignedTo) : null;
        } else {
            unset($validated['assigned_to']);
        }

        unset($validated['sync_assignees']);
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

        unset($validated['photo'], $validated['photos']);

        return $validated;
    }

    /** @return Collection<int, UploadedFile> */
    private function uploadedPhotoFiles(Request $request): Collection
    {
        $files = collect(Arr::wrap($request->file('photos')))
            ->filter(fn ($file) => $file instanceof UploadedFile);

        if ($request->hasFile('photo')) {
            $files->push($request->file('photo'));
        }

        if ($files->count() > self::MAX_EVIDENCE_PHOTOS) {
            throw ValidationException::withMessages([
                'photos' => 'Cada OT admite un máximo de 3 fotografías.',
            ]);
        }

        return $files->values();
    }

    /** @return array<int, array{file: UploadedFile, path: string}> */
    private function storePhotoBatch(Collection $files): array
    {
        $stored = [];

        try {
            foreach ($files as $file) {
                $stored[] = [
                    'file' => $file,
                    'path' => $this->storePhoto($file),
                ];
            }
        } catch (Throwable $exception) {
            Storage::disk('public')->delete(collect($stored)->pluck('path')->all());
            throw $exception;
        }

        return $stored;
    }

    /** @param array<int, array{file: UploadedFile, path: string}> $storedPhotos */
    private function persistEvidencePhotos(
        MaintenanceWorkOrder $workOrder,
        array $storedPhotos,
        ?int $uploadedByUserId
    ): void {
        foreach ($storedPhotos as $storedPhoto) {
            $file = $storedPhoto['file'];
            $path = $storedPhoto['path'];

            $workOrder->evidencePhotos()->create([
                'path' => $path,
                'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
                'mime_type' => 'image/jpeg',
                'size_bytes' => Storage::disk('public')->size($path),
                'uploaded_by_user_id' => $uploadedByUserId,
            ]);
        }
    }

    private function workOrderPhotoCount(MaintenanceWorkOrder $workOrder): int
    {
        return collect([$workOrder->photo_reference])
            ->concat($workOrder->evidencePhotos()->pluck('path'))
            ->map(fn ($path) => trim((string) $path))
            ->filter()
            ->unique()
            ->count();
    }

    private function storePhoto(UploadedFile $file): string
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
        return collect($this->maintenanceAssigneeCatalog())
            ->pluck('value')
            ->values()
            ->all();
    }

    private function allowedAssignees(Request $request, ?array $catalogAssignees = null): array
    {
        $assignees = $catalogAssignees ?? $this->assignees();

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

    private function maintenanceAssigneeUserQuery(): Builder
    {
        return User::query()
            ->with(['staff.cargo:id,name,slug'])
            ->where('users.active', true)
            ->where(function (Builder $query) {
                $query->whereHas('staff', function (Builder $staff) {
                    $staff
                        ->where('active', true)
                        ->where('can_receive_maintenance_orders', true);
                });

                foreach (self::EXPLICIT_ASSIGNEE_NAME_TERMS as $terms) {
                    $query
                        ->orWhere(function (Builder $userName) use ($terms) {
                            foreach ($terms as $term) {
                                $userName->where('name', 'like', "%{$term}%");
                            }
                        })
                        ->orWhereHas('staff', function (Builder $staff) use ($terms) {
                            $staff->where('active', true);
                            foreach ($terms as $term) {
                                $staff->where('full_name', 'like', "%{$term}%");
                            }
                        });
                }
            })
            ->orderBy('users.name');
    }

    private function maintenanceAssigneeCatalog(): array
    {
        return $this->maintenanceAssigneeUserQuery()
            ->get([
                'users.id',
                'users.name',
                'users.staff_id',
            ])
            ->map(function (User $user) {
                $staff = $user->staff;
                $displayName = Str::squish((string) ($staff?->full_name ?: $user->name));

                return [
                    'id' => $user->id,
                    'user_id' => $user->id,
                    'staff_id' => $staff?->id,
                    'full_name' => $displayName,
                    'cargo' => $staff?->cargo ? [
                        'id' => $staff->cargo->id,
                        'name' => $staff->cargo->name,
                        'slug' => $staff->cargo->slug,
                    ] : null,
                    'maintenance_role' => $staff?->maintenance_role,
                    'maintenance_role_label' => $staff?->maintenance_role_label,
                    'label' => trim(sprintf(
                        '%s%s',
                        $displayName,
                        $staff?->maintenance_role_label ? ' · '.$staff->maintenance_role_label : ''
                    )),
                    'value' => $displayName,
                ];
            })
            ->values()
            ->all();
    }

    private function syncAssigneeUsers(MaintenanceWorkOrder $workOrder, array $userIds): void
    {
        $users = $this->maintenanceAssigneeUserQuery()
            ->whereKey($userIds)
            ->get(['users.id', 'users.name', 'users.staff_id']);
        $syncPayload = $users->mapWithKeys(function (User $user) {
            $displayName = Str::squish((string) ($user->staff?->full_name ?: $user->name));

            return [$user->id => ['assignee_name_snapshot' => $displayName]];
        })->all();

        $workOrder->assigneeUsers()->sync($syncPayload);
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
