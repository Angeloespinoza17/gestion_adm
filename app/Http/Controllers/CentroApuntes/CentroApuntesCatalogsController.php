<?php

namespace App\Http\Controllers\CentroApuntes;

use App\Http\Controllers\Controller;
use App\Models\CentroApuntes\CentroApuntesAsignatura;
use App\Models\CentroApuntes\CentroApuntesMaquina;
use App\Models\CentroApuntes\CentroApuntesSolicitud;
use App\Models\CentroApuntes\PanolEntrega;
use App\Models\CentroApuntes\PanolInsumo;
use App\Models\CentroApuntes\PanolMovimiento;
use App\Models\Department;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\CentroApuntes\CentroApuntesAccessService;

class CentroApuntesCatalogsController extends Controller
{
    public function __construct(
        private readonly CentroApuntesAccessService $accessService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canAccessCatalogs($request->user()), 403);

        $users = User::query()
            ->with(['staff.cargo:id,name', 'staff.departments:id,name'])
            ->where('user_type', 'staff')
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'staff_id']);

        return response()->json([
            'task_types' => $this->toOptions(CentroApuntesSolicitud::TASK_TYPES),
            'paper_sizes' => $this->toOptions(CentroApuntesSolicitud::PAPER_SIZES),
            'request_priorities' => $this->toOptions(CentroApuntesSolicitud::PRIORITY_OPTIONS),
            'request_statuses' => $this->toOptions(CentroApuntesSolicitud::STATUS_OPTIONS),
            'subject_statuses' => $this->toOptions(CentroApuntesAsignatura::STATUS_OPTIONS),
            'subject_areas' => $this->toOptions(CentroApuntesAsignatura::AREA_OPTIONS),
            'subject_levels' => $this->toOptions(CentroApuntesAsignatura::EDUCATION_LEVEL_OPTIONS),
            'machine_types' => $this->toOptions(CentroApuntesMaquina::TYPE_OPTIONS),
            'machine_statuses' => $this->toOptions(CentroApuntesMaquina::STATUS_OPTIONS),
            'supply_categories' => $this->toOptions(PanolInsumo::CATEGORY_OPTIONS),
            'supply_units' => $this->toOptions(PanolInsumo::UNIT_OPTIONS),
            'supply_statuses' => $this->toOptions(PanolInsumo::STATUS_OPTIONS),
            'movement_types' => $this->toOptions(PanolMovimiento::TYPE_OPTIONS),
            'delivery_statuses' => $this->toOptions(PanolEntrega::STATUS_OPTIONS),
            'report_periods' => $this->toOptions(['diario', 'semanal', 'mensual', 'semestral', 'anual']),
            'users' => $users->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'cargo' => $user->staff?->cargo?->name,
                'departments' => $user->staff?->departments?->pluck('name')->values()->all() ?? [],
                'label' => trim(sprintf('%s%s', $user->name, $user->staff?->cargo?->name ? ' · ' . $user->staff->cargo->name : '')),
            ])->values(),
            'subjects' => CentroApuntesAsignatura::query()->orderBy('name')->get(['id', 'name', 'code', 'area', 'education_level', 'status']),
            'machines' => CentroApuntesMaquina::query()->orderBy('name')->get(['id', 'name', 'internal_code', 'type', 'status', 'estimated_cost_letter', 'estimated_cost_officio']),
            'supplies' => PanolInsumo::query()->orderBy('name')->get(['id', 'name', 'category', 'unit_of_measure', 'current_stock', 'status']),
            'departments' => Department::query()->where('active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'color']),
            'suppliers' => Supplier::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'rut']),
        ]);
    }

    /**
     * @param  array<int, string>  $values
     * @return array<int, array{value:string,label:string}>
     */
    private function toOptions(array $values): array
    {
        return collect($values)
            ->map(fn (string $value) => [
                'value' => $value,
                'label' => str($value)->replace('_', ' ')->title()->toString(),
            ])
            ->values()
            ->all();
    }
}
