<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceChecklistItem;
use App\Models\MaintenanceDependency;
use App\Models\MaintenanceEvidencePhoto;
use App\Models\MaintenanceVisit;
use App\Models\MaintenanceVisitChecklistResponse;
use App\Models\MaintenanceWorkOrder;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class MaintenanceVisitController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'responsible_staff_id' => [
                'nullable',
                'integer',
                Rule::exists('staff', 'id')
                    ->where('active', true)
                    ->where('can_receive_maintenance_orders', true),
            ],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);
        $search = trim((string) $request->query('search'));
        $responsible = trim((string) $request->query('responsible'));
        $responsibleStaffId = isset($validated['responsible_staff_id'])
            ? (int) $validated['responsible_staff_id']
            : null;
        $responsibleStaffName = $responsibleStaffId
            ? Staff::query()->whereKey($responsibleStaffId)->value('full_name')
            : null;
        $status = trim((string) $request->query('status'));
        $type = trim((string) $request->query('visit_type'));
        $from = trim((string) $request->query('from'));
        $to = trim((string) $request->query('to'));
        $dependencyId = $request->query('dependency_id');

        $query = MaintenanceVisit::query()
            ->when($dependencyId, fn ($query) => $query->where('maintenance_dependency_id', $dependencyId))
            ->when($responsibleStaffId, function ($query) use ($responsibleStaffId, $responsibleStaffName) {
                $query->where(function ($query) use ($responsibleStaffId, $responsibleStaffName) {
                    $query->where('responsible_staff_id', $responsibleStaffId);

                    if ($responsibleStaffName) {
                        $query->orWhere(function ($query) use ($responsibleStaffName) {
                            $query->whereNull('responsible_staff_id')
                                ->where('responsible', $responsibleStaffName);
                        });
                    }
                });
            })
            ->when($responsible !== '', fn ($query) => $query->where('responsible', $responsible))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($type !== '', fn ($query) => $query->where('visit_type', $type))
            ->when($from !== '', fn ($query) => $query->whereDate('visit_date', '>=', $from))
            ->when($to !== '', fn ($query) => $query->whereDate('visit_date', '<=', $to))
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('dependency', function ($query) use ($search) {
                    $query
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('distribution', 'like', "%{$search}%")
                        ->orWhere('sector', 'like', "%{$search}%")
                        ->orWhere('zone', 'like', "%{$search}%")
                        ->orWhere('usage', 'like', "%{$search}%");
                });
            });

        $statusTotals = (clone $query)
            ->select('status')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total) => (int) $total)
            ->all();

        $visits = $query
            ->with('dependency:id,code,name,distribution,sector,zone,usage')
            ->orderByDesc('visit_date')
            ->orderByDesc('visit_time')
            ->orderByDesc('created_at')
            ->paginate((int) ($validated['per_page'] ?? 15));

        return response()->json(array_merge($visits->toArray(), [
            'status_totals' => $statusTotals,
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        $visit = MaintenanceVisit::create($this->validated($request));

        return response()->json([
            'message' => 'Visita creada correctamente.',
            'data' => $visit->load('dependency:id,code,name,distribution,sector,zone,usage'),
        ], 201);
    }

    public function show(MaintenanceVisit $maintenanceVisit): JsonResponse
    {
        return response()->json($maintenanceVisit->load('dependency:id,code,name,distribution,sector,zone,usage'));
    }

    public function update(Request $request, MaintenanceVisit $maintenanceVisit): JsonResponse
    {
        $maintenanceVisit->update($this->validated($request));

        return response()->json([
            'message' => 'Visita actualizada correctamente.',
            'data' => $maintenanceVisit->load('dependency:id,code,name,distribution,sector,zone,usage'),
        ]);
    }

    public function destroy(MaintenanceVisit $maintenanceVisit): JsonResponse
    {
        $maintenanceVisit->delete();

        return response()->json([
            'message' => 'Visita eliminada correctamente.',
        ]);
    }

    public function catalogs(): JsonResponse
    {
        $assignees = $this->maintenanceAssigneeCatalog();

        return response()->json([
            'visit_types' => ['Inspección', 'Mantención', 'Reunión', 'Otro'],
            'statuses' => ['Programada', 'En progreso', 'Finalizada', 'Cancelada'],
            'review_statuses' => ['OK', 'No OK', 'N/A'],
            'responsibles' => collect($assignees)->pluck('full_name')->values()->all(),
            'maintenance_assignees' => $assignees,
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
                    'is_reservable',
                    'is_maintenance_location',
                ]),
        ]);
    }

    public function checklist(MaintenanceVisit $maintenanceVisit): JsonResponse
    {
        $items = MaintenanceChecklistItem::query()
            ->where('active', true)
            ->orderBy('system')
            ->orderBy('subdimension')
            ->orderBy('id')
            ->get(['id', 'system', 'subdimension', 'review']);

        $responses = $maintenanceVisit->checklistResponses()
            ->with([
                'item:id,system,subdimension,review',
                'photos:id,maintenance_visit_checklist_response_id,maintenance_work_order_id,path,original_name,mime_type,size_bytes,created_at',
            ])
            ->get()
            ->keyBy('maintenance_checklist_item_id');

        return response()->json([
            'visit' => $maintenanceVisit->load('dependency:id,code,name,distribution,sector,zone,usage'),
            'items' => $items,
            'responses' => $responses->values(),
        ]);
    }

    public function upsertChecklist(Request $request, MaintenanceVisit $maintenanceVisit): JsonResponse
    {
        $reviewStatuses = ['OK', 'No OK', 'N/A'];

        $payload = $request->validate([
            'responses' => ['required', 'array'],
            'responses.*.maintenance_checklist_item_id' => ['required', 'integer', 'exists:maintenance_checklist_items,id'],
            'responses.*.review_status' => ['nullable', 'string', Rule::in($reviewStatuses)],
            'responses.*.observations' => ['nullable', 'string'],
            'responses.*.finding_description' => ['nullable', 'string'],
        ]);

        foreach ($payload['responses'] as $row) {
            MaintenanceVisitChecklistResponse::updateOrCreate(
                [
                    'maintenance_visit_id' => $maintenanceVisit->id,
                    'maintenance_checklist_item_id' => $row['maintenance_checklist_item_id'],
                ],
                [
                    'review_status' => $row['review_status'] ?? null,
                    'observations' => $row['observations'] ?? null,
                    'finding_description' => $row['finding_description'] ?? null,
                ]
            );
        }

        return response()->json([
            'message' => 'Checklist guardado correctamente.',
        ]);
    }

    public function uploadChecklistPhoto(Request $request, MaintenanceVisit $maintenanceVisit): JsonResponse
    {
        $request->validate([
            'maintenance_checklist_item_id' => ['required', 'integer', 'exists:maintenance_checklist_items,id'],
            'photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,gif,bmp,webp', 'max:5120', 'required_without:photos'],
            'photos' => ['nullable', 'array', 'min:1', 'max:3', 'required_without:photo'],
            'photos.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,gif,bmp,webp', 'max:5120'],
        ]);

        $itemId = (int) $request->input('maintenance_checklist_item_id');
        $files = collect($request->file('photos', []));

        if ($request->hasFile('photo')) {
            $files->push($request->file('photo'));
        }

        $response = MaintenanceVisitChecklistResponse::firstOrCreate(
            [
                'maintenance_visit_id' => $maintenanceVisit->id,
                'maintenance_checklist_item_id' => $itemId,
            ]
        );

        $currentCount = $response->photos()->count() + ($response->photo_reference ? 1 : 0);
        if ($currentCount + $files->count() > 3) {
            throw ValidationException::withMessages([
                'photos' => 'Cada hallazgo u OT admite un máximo de 3 fotografías.',
            ]);
        }

        $storedPaths = [];

        try {
            DB::transaction(function () use ($files, $maintenanceVisit, $request, $response, &$storedPaths): void {
                $lockedResponse = MaintenanceVisitChecklistResponse::query()
                    ->lockForUpdate()
                    ->findOrFail($response->id);
                $lockedCount = $lockedResponse->photos()->count() + ($lockedResponse->photo_reference ? 1 : 0);

                if ($lockedCount + $files->count() > 3) {
                    throw ValidationException::withMessages([
                        'photos' => 'Cada hallazgo u OT admite un máximo de 3 fotografías.',
                    ]);
                }

                foreach ($files as $file) {
                    $path = $file->store("maintenance/visits/{$maintenanceVisit->id}", 'public');
                    $storedPaths[] = $path;

                    $lockedResponse->photos()->create([
                        'maintenance_work_order_id' => $lockedResponse->work_order_id,
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'size_bytes' => $file->getSize(),
                        'uploaded_by_user_id' => $request->user()?->id,
                    ]);
                }

                if ($lockedResponse->work_order_id && $storedPaths !== []) {
                    MaintenanceWorkOrder::query()
                        ->whereKey($lockedResponse->work_order_id)
                        ->where(function ($query) {
                            $query->whereNull('photo_reference')->orWhere('photo_reference', '');
                        })
                        ->update(['photo_reference' => $storedPaths[0]]);
                }
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($storedPaths);
            throw $exception;
        }

        return response()->json([
            'message' => $files->count() === 1
                ? 'Foto agregada correctamente.'
                : "{$files->count()} fotos agregadas correctamente.",
            'data' => $response->fresh()->load('photos'),
        ]);
    }

    public function deleteChecklistPhoto(
        MaintenanceVisit $maintenanceVisit,
        MaintenanceEvidencePhoto $photo
    ): JsonResponse {
        $photo->loadMissing('checklistResponse:id,maintenance_visit_id');

        abort_unless(
            (int) $photo->checklistResponse?->maintenance_visit_id === (int) $maintenanceVisit->id,
            404
        );

        $path = $photo->path;
        $response = $photo->checklistResponse;

        DB::transaction(function () use ($photo, $path, $response): void {
            $workOrderId = $photo->maintenance_work_order_id;
            $photo->delete();

            if (! $workOrderId) {
                return;
            }

            $workOrder = MaintenanceWorkOrder::query()->lockForUpdate()->find($workOrderId);
            if (! $workOrder || $workOrder->photo_reference !== $path) {
                return;
            }

            $replacement = $response->photo_reference
                ?: $response->photos()->oldest('id')->value('path');
            $workOrder->update(['photo_reference' => $replacement]);
        });

        Storage::disk('public')->delete($path);

        return response()->json([
            'message' => 'Foto eliminada correctamente.',
            'data' => $response->fresh()->load('photos'),
        ]);
    }

    public function createWorkOrderFromFinding(Request $request, MaintenanceVisitChecklistResponse $checklistResponse): JsonResponse
    {
        $checklistResponse->load('visit.dependency', 'item', 'photos');

        $visit = $checklistResponse->visit;
        if (! $visit) {
            return response()->json(['message' => 'Visita no encontrada.'], 404);
        }

        if (! $checklistResponse->finding_description) {
            return response()->json(['message' => 'No hay hallazgo para generar OT.'], 422);
        }

        $validated = $request->validate([
            'priority' => ['nullable', 'string', Rule::in(['Crítico', 'Alta', 'Media', 'Baja'])],
            'status' => ['nullable', 'string', Rule::in(['Sin comenzar', 'En proceso', 'En espera', 'Pausado', 'Terminado', 'Anulado'])],
            'due_date' => ['nullable', 'date'],
        ]);

        $description = trim($checklistResponse->finding_description);
        $detail = $checklistResponse->item
            ? "Checklist: {$checklistResponse->item->system} / {$checklistResponse->item->subdimension} - {$checklistResponse->item->review}"
            : null;

        $workOrder = DB::transaction(function () use ($checklistResponse, $description, $detail, $request, $validated, $visit) {
            $lockedResponse = MaintenanceVisitChecklistResponse::query()
                ->lockForUpdate()
                ->findOrFail($checklistResponse->id);

            if ($lockedResponse->work_order_id) {
                return MaintenanceWorkOrder::query()->findOrFail($lockedResponse->work_order_id);
            }

            $photoCount = $lockedResponse->photos()->count() + ($lockedResponse->photo_reference ? 1 : 0);
            if ($photoCount > 3) {
                throw ValidationException::withMessages([
                    'photos' => 'El hallazgo supera el máximo de 3 fotografías permitido para una OT.',
                ]);
            }

            $firstPhotoPath = $lockedResponse->photo_reference
                ?: $lockedResponse->photos()->oldest('id')->value('path');

            $workOrder = MaintenanceWorkOrder::create([
                'maintenance_dependency_id' => $visit->maintenance_dependency_id,
                'reported_at' => Carbon::now(),
                'requested_by' => $visit->responsible,
                'created_by_user_id' => $request->user()?->id,
                'assigned_to' => $visit->responsible,
                'priority' => $validated['priority'] ?? 'Media',
                'status' => $validated['status'] ?? 'Sin comenzar',
                'due_date' => $validated['due_date'] ?? null,
                'description' => $description,
                'resolution_notes' => $detail,
                'photo_reference' => $firstPhotoPath,
            ]);

            $lockedResponse->update(['work_order_id' => $workOrder->id]);
            $lockedResponse->photos()->update(['maintenance_work_order_id' => $workOrder->id]);

            return $workOrder;
        });

        return response()->json([
            'message' => 'OT creada desde hallazgo.',
            'data' => $workOrder->load(
                'dependency:id,code,name,distribution,sector,zone,usage',
                'evidencePhotos'
            ),
        ], 201);
    }

    private function validated(Request $request): array
    {
        $visitTypes = ['Inspección', 'Mantención', 'Reunión', 'Otro'];
        $statuses = ['Programada', 'En progreso', 'Finalizada', 'Cancelada'];

        $validated = $request->validate([
            'maintenance_dependency_id' => [
                'required',
                'integer',
                Rule::exists('maintenance_dependencies', 'id')
                    ->where('dependency_kind', MaintenanceDependency::KIND_SPACE)
                    ->where('is_maintenance_location', true)
                    ->where('active', true),
            ],
            'responsible' => ['required', 'string', 'max:255', Rule::in($this->allowedResponsibles($request))],
            'visit_date' => ['required', 'date'],
            'visit_time' => ['nullable', 'date_format:H:i'],
            'visit_type' => ['required', 'string', Rule::in($visitTypes)],
            'status' => ['required', 'string', Rule::in($statuses)],
            'notes' => ['nullable', 'string'],
        ]);

        $responsibleStaffId = $this->maintenanceAssigneeQuery()
            ->where('full_name', $validated['responsible'])
            ->value('id');
        $currentVisit = collect($request->route()?->parameters() ?? [])
            ->first(fn ($parameter) => $parameter instanceof MaintenanceVisit);

        if (! $responsibleStaffId && $currentVisit instanceof MaintenanceVisit
            && $currentVisit->responsible === $validated['responsible']) {
            $responsibleStaffId = $currentVisit->responsible_staff_id;
        }

        $validated['responsible_staff_id'] = $responsibleStaffId ? (int) $responsibleStaffId : null;

        return $validated;
    }

    private function responsibles(): array
    {
        return $this->maintenanceAssigneeQuery()
            ->pluck('full_name')
            ->values()
            ->all();
    }

    private function allowedResponsibles(Request $request): array
    {
        $responsibles = $this->responsibles();

        $currentVisit = collect($request->route()?->parameters() ?? [])
            ->first(fn ($parameter) => $parameter instanceof MaintenanceVisit);

        if ($currentVisit instanceof MaintenanceVisit) {
            $responsibles[] = $currentVisit->responsible;
        }

        return collect($responsibles)
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
}
