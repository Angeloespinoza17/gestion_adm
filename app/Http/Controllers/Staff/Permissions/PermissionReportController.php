<?php

namespace App\Http\Controllers\Staff\Permissions;

use App\Http\Controllers\Controller;
use App\Models\PermissionRequest;
use App\Services\Permissions\PermissionRequestAccessService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PermissionReportController extends Controller
{
    public function __construct(
        private readonly PermissionRequestAccessService $accessService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PermissionRequest::class);

        $query = $this->filteredQuery($request)
            ->with([
                'staff:id,full_name,rut,cargo_id',
                'staff.cargo:id,name',
                'permissionType:id,name',
                'departments:id,name,color',
            ]);

        $summaryQuery = clone $query;

        return response()->json([
            'data' => $query
                ->orderByDesc('start_date')
                ->orderByDesc('id')
                ->paginate((int) $request->query('per_page', 20)),
            'summary' => [
                'total' => (clone $summaryQuery)->count(),
                'con_goce' => (clone $summaryQuery)->where('with_pay', true)->count(),
                'sin_goce' => (clone $summaryQuery)->where('with_pay', false)->count(),
                'rechazados' => (clone $summaryQuery)->where('status', 'rechazado')->count(),
                'pendientes' => (clone $summaryQuery)->whereIn('status', ['ingresado', 'pendiente_jefatura', 'pendiente_direccion', 'pendiente_rrhh', 'observado'])->count(),
                'requieren_reemplazo' => (clone $summaryQuery)->where('requires_replacement', true)->count(),
                'afectan_remuneracion' => (clone $summaryQuery)->where('affects_salary', true)->count(),
                'fuera_plazo_o_urgencia' => (clone $summaryQuery)->where(function (Builder $query) {
                    $query->where('urgency', true)->orWhere('retroactive', true);
                })->count(),
            ],
        ]);
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = $this->accessService->visibleQuery($request->user());
        $search = trim((string) $request->query('search'));

        return $query
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $nested) use ($search) {
                    $nested
                        ->where('reason', 'like', "%{$search}%")
                        ->orWhereHas('staff', fn (Builder $staffQuery) => $staffQuery->where('full_name', 'like', "%{$search}%"))
                        ->orWhereHas('permissionType', fn (Builder $typeQuery) => $typeQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->query('staff_id'), fn (Builder $query, $value) => $query->where('staff_id', $value))
            ->when($request->query('department_id'), fn (Builder $query, $value) => $query->whereHas('departments', fn (Builder $departmentQuery) => $departmentQuery->where('departments.id', $value)))
            ->when($request->query('permission_type_id'), fn (Builder $query, $value) => $query->where('permission_type_id', $value))
            ->when($request->query('status'), fn (Builder $query, $value) => $query->where('status', $value))
            ->when($request->query('month') && $request->query('year'), fn (Builder $query) => $query->whereYear('start_date', (int) $request->query('year'))->whereMonth('start_date', (int) $request->query('month')))
            ->when($request->query('with_pay') !== null && $request->query('with_pay') !== '', fn (Builder $query) => $query->where('with_pay', filter_var($request->query('with_pay'), FILTER_VALIDATE_BOOLEAN)))
            ->when($request->query('requires_replacement') !== null && $request->query('requires_replacement') !== '', fn (Builder $query) => $query->where('requires_replacement', filter_var($request->query('requires_replacement'), FILTER_VALIDATE_BOOLEAN)))
            ->when($request->query('affects_salary') !== null && $request->query('affects_salary') !== '', fn (Builder $query) => $query->where('affects_salary', filter_var($request->query('affects_salary'), FILTER_VALIDATE_BOOLEAN)))
            ->when($request->query('late_or_urgent') !== null && $request->query('late_or_urgent') !== '', fn (Builder $query) => $query->where(function (Builder $nested) {
                $nested->where('urgency', true)->orWhere('retroactive', true);
            }));
    }
}
