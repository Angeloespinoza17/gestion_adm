<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceAnnualPlan;
use App\Models\MaintenanceDependency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $plans = MaintenanceAnnualPlan::query()
            ->with('dependency:id,code,name,distribution,sector,zone,usage')
            ->when($dependencyId, fn ($query) => $query->where('maintenance_dependency_id', $dependencyId))
            ->when($plannedYear, fn ($query) => $query->where('planned_year', (int) $plannedYear))
            ->when($plannedMonth, fn ($query) => $query->where('planned_month', (int) $plannedMonth))
            ->when($responsible !== '', fn ($query) => $query->where('responsible', $responsible))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($frequency !== '', fn ($query) => $query->where('frequency', $frequency))
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->when($search !== '', function ($query) use ($search) {
                $query
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('dependency', function ($query) use ($search) {
                        $query
                            ->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%")
                            ->orWhere('distribution', 'like', "%{$search}%")
                            ->orWhere('sector', 'like', "%{$search}%")
                            ->orWhere('zone', 'like', "%{$search}%");
                    });
            })
            ->orderByDesc('planned_year')
            ->orderByDesc('planned_month')
            ->orderByDesc('created_at')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($plans);
    }

    public function store(Request $request): JsonResponse
    {
        $plan = MaintenanceAnnualPlan::create($this->validated($request));

        return response()->json([
            'message' => 'Mantención programada creada correctamente.',
            'data' => $plan->load('dependency:id,code,name,distribution,sector,zone,usage'),
        ], 201);
    }

    public function show(MaintenanceAnnualPlan $maintenanceAnnualPlan): JsonResponse
    {
        return response()->json($maintenanceAnnualPlan->load('dependency:id,code,name,distribution,sector,zone,usage'));
    }

    public function update(Request $request, MaintenanceAnnualPlan $maintenanceAnnualPlan): JsonResponse
    {
        $maintenanceAnnualPlan->update($this->validated($request));

        return response()->json([
            'message' => 'Mantención programada actualizada correctamente.',
            'data' => $maintenanceAnnualPlan->load('dependency:id,code,name,distribution,sector,zone,usage'),
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
            'responsibles' => $this->people(),
            'dependencies' => MaintenanceDependency::query()
                ->where('active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'distribution', 'sector', 'zone']),
        ]);
    }

    private function validated(Request $request): array
    {
        $frequencies = $this->frequencies();
        $statuses = $this->statuses();

        return $request->validate([
            'maintenance_dependency_id' => ['required', 'integer', 'exists:maintenance_dependencies,id'],
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
            'notes' => ['nullable', 'string'],
        ]);
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
        ];
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

