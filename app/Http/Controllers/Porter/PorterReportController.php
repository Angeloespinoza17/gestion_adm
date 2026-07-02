<?php

namespace App\Http\Controllers\Porter;

use App\Http\Controllers\Controller;
use App\Models\PorterGoodsMovement;
use App\Models\PorterReceivedItem;
use App\Models\PorterStudentWithdrawal;
use App\Services\Porter\PorterAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PorterReportController extends Controller
{
    public function __construct(
        private readonly PorterAccessService $accessService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        $dateFrom = $request->query('date_from') ?: now()->startOfMonth()->toDateString();
        $dateTo = $request->query('date_to') ?: now()->toDateString();

        $withdrawalsBase = PorterStudentWithdrawal::query()
            ->whereDate('withdrawn_at', '>=', $dateFrom)
            ->whereDate('withdrawn_at', '<=', $dateTo);

        $itemsBase = PorterReceivedItem::query()
            ->whereDate('received_at', '>=', $dateFrom)
            ->whereDate('received_at', '<=', $dateTo);

        $goodsBase = PorterGoodsMovement::query()
            ->whereDate('moved_at', '>=', $dateFrom)
            ->whereDate('moved_at', '<=', $dateTo);

        $withdrawalsByCourse = (clone $withdrawalsBase)
            ->select('course_name_snapshot', DB::raw('COUNT(*) as total'))
            ->groupBy('course_name_snapshot')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $withdrawalsByReason = (clone $withdrawalsBase)
            ->select('reason', DB::raw('COUNT(*) as total'))
            ->groupBy('reason')
            ->orderByDesc('total')
            ->get();

        $topPeople = (clone $withdrawalsBase)
            ->select('person_name', 'person_rut', DB::raw('COUNT(*) as total'))
            ->groupBy('person_name', 'person_rut')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $movementsByUser = DB::table('porter_movement_logs')
            ->join('users', 'users.id', '=', 'porter_movement_logs.performed_by')
            ->whereDate('porter_movement_logs.performed_at', '>=', $dateFrom)
            ->whereDate('porter_movement_logs.performed_at', '<=', $dateTo)
            ->select('users.id', 'users.name', DB::raw('COUNT(*) as total'))
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return response()->json([
            'summary' => [
                'withdrawals_total' => (clone $withdrawalsBase)->count(),
                'withdrawals_authorized' => (clone $withdrawalsBase)->where('status', 'autorizado')->count(),
                'withdrawals_observed' => (clone $withdrawalsBase)->where('status', 'observado')->count(),
                'withdrawals_rejected' => (clone $withdrawalsBase)->where('status', 'rechazado')->count(),
                'items_pending' => (clone $itemsBase)->whereIn('status', ['recibido_en_porteria', 'derivado', 'pendiente'])->count(),
                'goods_pending' => (clone $goodsBase)->whereIn('status', ['recibido_en_porteria', 'derivado_a_departamento', 'pendiente'])->count(),
            ],
            'withdrawals_by_course' => $withdrawalsByCourse,
            'withdrawals_by_reason' => $withdrawalsByReason,
            'top_people' => $topPeople,
            'pending_items' => (clone $itemsBase)
                ->with(['studentProfile:id,first_name,last_name', 'department:id,name'])
                ->whereIn('status', ['recibido_en_porteria', 'derivado', 'pendiente'])
                ->orderBy('received_at')
                ->limit(10)
                ->get(),
            'pending_goods' => (clone $goodsBase)
                ->with(['department:id,name', 'responsibleStaff:id,full_name'])
                ->whereIn('status', ['recibido_en_porteria', 'derivado_a_departamento', 'pendiente'])
                ->orderBy('moved_at')
                ->limit(10)
                ->get(),
            'movements_by_user' => $movementsByUser,
        ]);
    }
}
