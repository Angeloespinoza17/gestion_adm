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
            'capabilities' => [
                'can_create_request' => $this->accessService->canCreateRequest($request->user()),
                'can_edit_request' => $this->accessService->canEditRequest($request->user()),
                'can_delete_request' => $this->accessService->canDeleteRequest($request->user()),
                'can_change_request_status' => $this->accessService->canChangeRequestStatus($request->user()),
                'can_register_request_delivery' => $this->accessService->canRegisterRequestDelivery($request->user()),
                'can_manage_subjects' => $this->accessService->canManageSubjects($request->user()),
                'can_manage_machines' => $this->accessService->canManageMachines($request->user()),
                'can_manage_inventory' => $this->accessService->canManageInventory($request->user()),
                'can_register_stock_movements' => $this->accessService->canRegisterStockMovements($request->user()),
                'can_request_materials' => $this->accessService->canRequestMaterials($request->user()),
                'can_approve_deliveries' => $this->accessService->canApproveDeliveries($request->user()),
                'can_export_reports' => $this->accessService->canExportReports($request->user()),
            ],
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
            'machines' => CentroApuntesMaquina::query()->orderBy('name')->get(['id', 'name', 'internal_code', 'type', 'status']),
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
