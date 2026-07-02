<?php

namespace App\Http\Controllers\Staff\Permissions;

use App\Http\Controllers\Controller;
use App\Models\PermissionRequest;
use App\Services\Permissions\PermissionRequestAccessService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PermissionDashboardController extends Controller
{
    public function __construct(
        private readonly PermissionRequestAccessService $accessService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PermissionRequest::class);

        $base = $this->accessService->visibleQuery($request->user());
        $monthBase = (clone $base)->whereYear('start_date', now()->year)->whereMonth('start_date', now()->month);

        $topStaff = (clone $base)
            ->join('staff', 'staff.id', '=', 'permission_requests.staff_id')
            ->select('permission_requests.staff_id', 'staff.full_name', DB::raw('COUNT(*) as total'))
            ->groupBy('permission_requests.staff_id', 'staff.full_name')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $topDepartments = DB::table('permission_requests')
            ->join('permission_request_department', 'permission_request_department.permission_request_id', '=', 'permission_requests.id')
            ->join('departments', 'departments.id', '=', 'permission_request_department.department_id')
            ->whereIn('permission_requests.id', (clone $base)->select('permission_requests.id'))
            ->select('departments.id', 'departments.name', DB::raw('COUNT(*) as total'))
            ->groupBy('departments.id', 'departments.name')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        return response()->json([
            'stats' => [
                'pending' => (clone $base)->whereIn('status', ['ingresado', 'pendiente_jefatura', 'pendiente_direccion', 'pendiente_rrhh', 'observado'])->count(),
                'approved_month' => (clone $monthBase)->where('status', 'aprobado')->count(),
                'rejected_month' => (clone $monthBase)->where('status', 'rechazado')->count(),
                'without_pay' => (clone $base)->where('with_pay', false)->count(),
                'requires_replacement' => (clone $base)->where('requires_replacement', true)->count(),
                'upcoming' => (clone $base)->where('status', 'aprobado')->whereBetween('start_date', [Carbon::today(), Carbon::today()->copy()->addDays(15)])->count(),
            ],
            'recent_pending' => (clone $this->accessService->reviewableQuery($request->user()))
                ->with(['staff:id,full_name', 'permissionType:id,name'])
                ->orderBy('start_date')
                ->limit(8)
                ->get(),
            'upcoming_permissions' => (clone $base)
                ->with(['staff:id,full_name', 'permissionType:id,name'])
                ->where('status', 'aprobado')
                ->whereBetween('start_date', [Carbon::today(), Carbon::today()->copy()->addDays(15)])
                ->orderBy('start_date')
                ->limit(8)
                ->get(),
            'top_staff' => $topStaff,
            'top_departments' => $topDepartments,
        ]);
    }
}
