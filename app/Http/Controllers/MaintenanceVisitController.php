<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceChecklistItem;
use App\Models\MaintenanceDependency;
use App\Models\MaintenanceVisit;
use App\Models\MaintenanceVisitChecklistResponse;
use App\Models\MaintenanceWorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MaintenanceVisitController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));
        $responsible = trim((string) $request->query('responsible'));
        $status = trim((string) $request->query('status'));
        $type = trim((string) $request->query('visit_type'));
        $from = trim((string) $request->query('from'));
        $to = trim((string) $request->query('to'));
        $dependencyId = $request->query('dependency_id');

        $visits = MaintenanceVisit::query()
            ->with('dependency:id,code,name,distribution,sector,zone,usage')
            ->when($dependencyId, fn ($query) => $query->where('maintenance_dependency_id', $dependencyId))
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
                        ->orWhere('zone', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('visit_date')
            ->orderByDesc('created_at')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($visits);
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
        return response()->json([
            'visit_types' => ['Inspección', 'Mantención', 'Reunión', 'Otro'],
            'statuses' => ['Programada', 'En progreso', 'Finalizada', 'Cancelada'],
            'review_statuses' => ['OK', 'No OK', 'N/A'],
            'responsibles' => $this->people(),
            'dependencies' => MaintenanceDependency::query()
                ->where('active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'distribution', 'sector', 'zone', 'is_reservable']),
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
            ->with('item:id,system,subdimension,review')
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
        $validated = $request->validate([
            'maintenance_checklist_item_id' => ['required', 'integer', 'exists:maintenance_checklist_items,id'],
            'photo' => ['required', 'file', 'image', 'max:5120'],
        ]);

        $response = MaintenanceVisitChecklistResponse::firstOrCreate(
            [
                'maintenance_visit_id' => $maintenanceVisit->id,
                'maintenance_checklist_item_id' => $validated['maintenance_checklist_item_id'],
            ]
        );

        $path = $request->file('photo')->store("maintenance/visits/{$maintenanceVisit->id}", 'public');

        if ($response->photo_reference) {
            Storage::disk('public')->delete($response->photo_reference);
        }

        $response->update([
            'photo_reference' => $path,
        ]);

        return response()->json([
            'message' => 'Foto guardada correctamente.',
            'data' => $response->fresh(),
        ]);
    }

    public function createWorkOrderFromFinding(Request $request, MaintenanceVisitChecklistResponse $checklistResponse): JsonResponse
    {
        $checklistResponse->load('visit.dependency', 'item');

        $visit = $checklistResponse->visit;
        if (!$visit) {
            return response()->json(['message' => 'Visita no encontrada.'], 404);
        }

        if (!$checklistResponse->finding_description) {
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

        $workOrder = MaintenanceWorkOrder::create([
            'maintenance_dependency_id' => $visit->maintenance_dependency_id,
            'reported_at' => Carbon::now(),
            'requested_by' => $visit->responsible,
            'assigned_to' => $visit->responsible,
            'priority' => $validated['priority'] ?? 'Media',
            'status' => $validated['status'] ?? 'Sin comenzar',
            'due_date' => $validated['due_date'] ?? null,
            'description' => $description,
            'resolution_notes' => $detail,
        ]);

        $checklistResponse->update([
            'work_order_id' => $workOrder->id,
        ]);

        return response()->json([
            'message' => 'OT creada desde hallazgo.',
            'data' => $workOrder->load('dependency:id,code,name,distribution,sector,zone,usage'),
        ], 201);
    }

    private function validated(Request $request): array
    {
        $visitTypes = ['Inspección', 'Mantención', 'Reunión', 'Otro'];
        $statuses = ['Programada', 'En progreso', 'Finalizada', 'Cancelada'];

        return $request->validate([
            'maintenance_dependency_id' => ['required', 'integer', 'exists:maintenance_dependencies,id'],
            'responsible' => ['required', 'string', 'max:255'],
            'visit_date' => ['required', 'date'],
            'visit_time' => ['nullable', 'date_format:H:i'],
            'visit_type' => ['required', 'string', Rule::in($visitTypes)],
            'status' => ['required', 'string', Rule::in($statuses)],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function people(): array
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
}
