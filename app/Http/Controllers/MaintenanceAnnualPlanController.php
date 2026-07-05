<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\MaintenanceAnnualPlan;
use App\Models\MaintenanceDependency;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class MaintenanceAnnualPlanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));
        $dependencyId = $request->query('dependency_id');
        $plannedYear = $request->query('planned_year');
        $plannedMonth = $request->query('planned_month');
        $responsible = trim((string) $request->query('responsible'));
        $status = trim((string) $request->query('status'));
        $frequency = trim((string) $request->query('frequency'));
        $category = trim((string) $request->query('category'));
        $itemType = trim((string) $request->query('item_type'));
        $dueScope = trim((string) $request->query('due_scope'));

        $plans = MaintenanceAnnualPlan::query()
            ->with([
                'dependency:id,code,name,distribution,sector,zone,usage',
                'inventoryItem:id,code,name,dependency_id,status,condition,category_id',
                'inventoryItem.category:id,name',
                'technicalArea:id,code,name,parent_dependency_id,distribution,sector,zone,usage',
                'technicalArea.parentDependency:id,code,name',
            ])
            ->when($dependencyId, fn ($query) => $query->where('maintenance_dependency_id', $dependencyId))
            ->when($plannedYear, fn ($query) => $query->where('planned_year', (int) $plannedYear))
            ->when($plannedMonth, fn ($query) => $query->where('planned_month', (int) $plannedMonth))
            ->when($responsible !== '', fn ($query) => $query->where('responsible', $responsible))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($frequency !== '', fn ($query) => $query->where('frequency', $frequency))
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->when($itemType !== '', fn ($query) => $query->where('item_type', $itemType))
            ->when($dueScope !== '', fn ($query) => $this->applyDueScope($query, $dueScope))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('component_name', 'like', "%{$search}%")
                        ->orWhereHas('inventoryItem', function ($query) use ($search) {
                            $query
                                ->where('code', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%")
                                ->orWhere('serial_number', 'like', "%{$search}%");
                        })
                        ->orWhereHas('technicalArea', function ($query) use ($search) {
                            $query
                                ->where('code', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%")
                                ->orWhere('distribution', 'like', "%{$search}%")
                                ->orWhere('sector', 'like', "%{$search}%")
                                ->orWhere('zone', 'like', "%{$search}%");
                        })
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
            ->when($dueScope === 'upcoming', function ($query) {
                $query
                    ->orderByRaw('scheduled_date IS NULL')
                    ->orderBy('scheduled_date')
                    ->orderBy('planned_year')
                    ->orderBy('planned_month');
            }, function ($query) {
                $query
                    ->orderByDesc('planned_year')
                    ->orderByDesc('planned_month')
                    ->orderByDesc('created_at');
            })
            ->paginate((int) $request->query('per_page', 15))
            ->through(fn (MaintenanceAnnualPlan $plan) => $this->serializePlan($plan));

        return response()->json($plans);
    }

    private function applyDueScope(Builder $query, string $dueScope): Builder
    {
        $today = Carbon::today();

        return match ($dueScope) {
            'upcoming' => $query
                ->whereNotIn('status', ['Cumplida', 'Cancelada'])
                ->whereNotNull('scheduled_date')
                ->whereDate('scheduled_date', '<=', $today->copy()->addDays(90)->toDateString()),
            'overdue' => $query
                ->whereNotIn('status', ['Cumplida', 'Cancelada'])
                ->whereNotNull('scheduled_date')
                ->whereDate('scheduled_date', '<', $today->toDateString()),
            'no_date' => $query->whereNull('scheduled_date'),
            default => $query,
        };
    }

    private function serializePlan(MaintenanceAnnualPlan $plan): array
    {
        $alert = $this->alertState($plan);

        return [
            'id' => $plan->id,
            'maintenance_dependency_id' => $plan->maintenance_dependency_id,
            'item_type' => $plan->item_type ?: 'dependency',
            'inventory_item_id' => $plan->inventory_item_id,
            'technical_area_id' => $plan->technical_area_id,
            'component_name' => $plan->component_name,
            'planned_year' => $plan->planned_year,
            'planned_month' => $plan->planned_month,
            'category' => $plan->category,
            'responsible' => $plan->responsible,
            'frequency' => $plan->frequency,
            'status' => $plan->status,
            'title' => $plan->title,
            'description' => $plan->description,
            'scheduled_date' => optional($plan->scheduled_date)->toDateString(),
            'completed_date' => optional($plan->completed_date)->toDateString(),
            'last_maintenance_date' => optional($plan->last_maintenance_date)->toDateString(),
            'alert_days' => $plan->alert_days,
            'alert_enabled' => $plan->alert_enabled,
            'notes' => $plan->notes,
            'created_at' => optional($plan->created_at)->toDateTimeString(),
            'updated_at' => optional($plan->updated_at)->toDateTimeString(),
            'dependency' => $plan->dependency,
            'inventory_item' => $plan->inventoryItem,
            'technical_area' => $plan->technicalArea,
            'item_label' => $this->itemLabel($plan),
            'item_type_label' => $this->itemTypeLabel($plan->item_type ?: 'dependency'),
            'days_until_due' => $alert['days_until_due'],
            'alert_state' => $alert['state'],
            'alert_label' => $alert['label'],
        ];
    }

    private function itemLabel(MaintenanceAnnualPlan $plan): string
    {
        return match ($plan->item_type) {
            'inventory_item' => $plan->inventoryItem
                ? "{$plan->inventoryItem->code} · {$plan->inventoryItem->name}"
                : 'Bien de inventario',
            'technical_area' => $plan->technicalArea
                ? "{$plan->technicalArea->code} · {$plan->technicalArea->name}"
                : 'Área técnica',
            'dependency_component' => $plan->component_name ?: 'Elemento de dependencia',
            default => $plan->dependency
                ? "{$plan->dependency->code} · {$plan->dependency->name}"
                : 'Dependencia',
        };
    }

    private function itemTypeLabel(string $type): string
    {
        return [
            'dependency' => 'Dependencia',
            'dependency_component' => 'Elemento de dependencia',
            'inventory_item' => 'Bien inventariado',
            'technical_area' => 'Área técnica',
        ][$type] ?? 'Dependencia';
    }

    private function alertState(MaintenanceAnnualPlan $plan): array
    {
        if (in_array($plan->status, ['Cumplida', 'Cancelada'], true)) {
            return ['state' => 'closed', 'label' => 'Cerrada', 'days_until_due' => null];
        }

        if (!$plan->scheduled_date) {
            return ['state' => 'no-date', 'label' => 'Sin fecha', 'days_until_due' => null];
        }

        $days = Carbon::today()->diffInDays($plan->scheduled_date, false);

        if ($days < 0) {
            return ['state' => 'overdue', 'label' => 'Vencida', 'days_until_due' => $days];
        }

        if ($plan->alert_enabled && $days <= (int) ($plan->alert_days ?? 30)) {
            return ['state' => 'upcoming', 'label' => $days === 0 ? 'Hoy' : "En {$days} dias", 'days_until_due' => $days];
        }

        return ['state' => 'scheduled', 'label' => 'Programada', 'days_until_due' => $days];
    }

    public function store(Request $request): JsonResponse
    {
        $plan = MaintenanceAnnualPlan::create($this->validated($request));

        return response()->json([
            'message' => 'Mantención programada creada correctamente.',
            'data' => $this->serializePlan($plan->load([
                'dependency:id,code,name,distribution,sector,zone,usage',
                'inventoryItem:id,code,name,dependency_id,status,condition,category_id',
                'technicalArea:id,code,name,parent_dependency_id,distribution,sector,zone,usage',
            ])),
        ], 201);
    }

    public function show(MaintenanceAnnualPlan $maintenanceAnnualPlan): JsonResponse
    {
        return response()->json($this->serializePlan($maintenanceAnnualPlan->load([
            'dependency:id,code,name,distribution,sector,zone,usage',
            'inventoryItem:id,code,name,dependency_id,status,condition,category_id',
            'inventoryItem.category:id,name',
            'technicalArea:id,code,name,parent_dependency_id,distribution,sector,zone,usage',
            'technicalArea.parentDependency:id,code,name',
        ])));
    }

    public function update(Request $request, MaintenanceAnnualPlan $maintenanceAnnualPlan): JsonResponse
    {
        $maintenanceAnnualPlan->update($this->validated($request));

        return response()->json([
            'message' => 'Mantención programada actualizada correctamente.',
            'data' => $this->serializePlan($maintenanceAnnualPlan->load([
                'dependency:id,code,name,distribution,sector,zone,usage',
                'inventoryItem:id,code,name,dependency_id,status,condition,category_id',
                'inventoryItem.category:id,name',
                'technicalArea:id,code,name,parent_dependency_id,distribution,sector,zone,usage',
                'technicalArea.parentDependency:id,code,name',
            ])),
        ]);
    }

    public function destroy(MaintenanceAnnualPlan $maintenanceAnnualPlan): JsonResponse
    {
        $maintenanceAnnualPlan->delete();

        return response()->json([
            'message' => 'Mantención programada eliminada correctamente.',
        ]);
    }

    public function catalogs(): JsonResponse
    {
        return response()->json([
            'frequencies' => $this->frequencies(),
            'statuses' => $this->statuses(),
            'categories' => $this->categories(),
            'item_types' => $this->itemTypes(),
            'component_suggestions' => $this->componentSuggestions(),
            'responsibles' => $this->people(),
            'maintenance_assignees' => $this->maintenanceAssigneeCatalog(),
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
                    'is_reservable',
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
                ->with(['dependency:id,code,name', 'category:id,name'])
                ->orderBy('code')
                ->get([
                    'id',
                    'code',
                    'name',
                    'category_id',
                    'dependency_id',
                    'status',
                    'condition',
                    'serial_number',
                ]),
        ]);
    }

    private function validated(Request $request): array
    {
        $frequencies = $this->frequencies();
        $statuses = $this->statuses();
        $itemTypes = array_column($this->itemTypes(), 'value');

        $validated = $request->validate([
            'maintenance_dependency_id' => [
                'required',
                'integer',
                Rule::exists('maintenance_dependencies', 'id')
                    ->where('dependency_kind', MaintenanceDependency::KIND_SPACE)
                    ->where('is_maintenance_location', true)
                    ->where('active', true),
            ],
            'item_type' => ['required', 'string', Rule::in($itemTypes)],
            'inventory_item_id' => [
                Rule::requiredIf($request->input('item_type') === 'inventory_item'),
                'nullable',
                'integer',
                Rule::exists('inventory_items', 'id')->where('active', true),
            ],
            'technical_area_id' => [
                Rule::requiredIf($request->input('item_type') === 'technical_area'),
                'nullable',
                'integer',
                Rule::exists('maintenance_dependencies', 'id')
                    ->where('dependency_kind', MaintenanceDependency::KIND_TECHNICAL_ASSET)
                    ->where('active', true),
            ],
            'component_name' => [
                Rule::requiredIf($request->input('item_type') === 'dependency_component'),
                'nullable',
                'string',
                'max:255',
            ],
            'planned_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'planned_month' => ['required', 'integer', 'min:1', 'max:12'],
            'category' => ['required', 'string', 'max:255'],
            'responsible' => ['required', 'string', 'max:255'],
            'frequency' => ['required', 'string', Rule::in($frequencies)],
            'status' => ['required', 'string', Rule::in($statuses)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'scheduled_date' => ['nullable', 'date'],
            'completed_date' => ['nullable', 'date'],
            'last_maintenance_date' => ['nullable', 'date'],
            'alert_days' => ['required', 'integer', 'min:1', 'max:365'],
            'alert_enabled' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        if (!empty($validated['scheduled_date'])) {
            $scheduledDate = Carbon::parse($validated['scheduled_date']);
            $validated['planned_year'] = (int) $scheduledDate->year;
            $validated['planned_month'] = (int) $scheduledDate->month;
        }

        if (!empty($validated['completed_date']) && empty($validated['last_maintenance_date'])) {
            $validated['last_maintenance_date'] = $validated['completed_date'];
        }

        if (($validated['item_type'] ?? null) !== 'inventory_item') {
            $validated['inventory_item_id'] = null;
        }

        if (($validated['item_type'] ?? null) !== 'technical_area') {
            $validated['technical_area_id'] = null;
        }

        if (($validated['item_type'] ?? null) !== 'dependency_component') {
            $validated['component_name'] = null;
        }

        $validated['alert_enabled'] = (bool) ($validated['alert_enabled'] ?? true);

        return $validated;
    }

    private function frequencies(): array
    {
        return ['Diaria', 'Semanal', 'Mensual', 'Semestral', 'Anual'];
    }

    private function statuses(): array
    {
        return ['Programada', 'En ejecución', 'Cumplida', 'Vencida', 'Cancelada'];
    }

    private function categories(): array
    {
        return [
            'General',
            'Eléctrica',
            'Climatización',
            'Aseo',
            'Seguridad',
            'Infraestructura',
            'Redes/Informática',
            'Extintores',
            'Equipamiento',
            'Elementos constructivos',
        ];
    }

    private function itemTypes(): array
    {
        return [
            ['value' => 'dependency', 'label' => 'Dependencia completa'],
            ['value' => 'dependency_component', 'label' => 'Elemento de dependencia'],
            ['value' => 'inventory_item', 'label' => 'Bien de inventario'],
            ['value' => 'technical_area', 'label' => 'Área técnica'],
        ];
    }

    private function componentSuggestions(): array
    {
        return [
            'Ventanas',
            'Puertas',
            'Paredes',
            'Pisos',
            'Cielos',
            'Luminarias',
            'Canaletas',
            'Cerrajería',
            'Cubierta',
            'Artefactos sanitarios',
            'Mobiliario fijo',
            'Rejas',
        ];
    }

    private function people(): array
    {
        return $this->maintenanceAssigneeQuery()
            ->pluck('full_name')
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
                    $staff->maintenance_role_label ? ' · ' . $staff->maintenance_role_label : ''
                )),
                'value' => $staff->full_name,
            ])
            ->values()
            ->all();
    }
}
