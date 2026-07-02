<?php

namespace App\Http\Controllers\Porter;

use App\Http\Controllers\Controller;
use App\Models\PorterDailyLogEntry;
use App\Models\PorterExternalServiceEntry;
use App\Models\PorterGoodsMovement;
use App\Models\PorterKeyLoan;
use App\Models\PorterMovementLog;
use App\Models\PorterReceivedItem;
use App\Models\PorterStudentWithdrawal;
use App\Models\PorterVisit;
use App\Models\StudentProfile;
use App\Services\Porter\PorterAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PorterDashboardController extends Controller
{
    public function __construct(
        private readonly PorterAccessService $accessService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        $today = now()->startOfDay();
        $pendingItemStatuses = ['recibido_en_porteria', 'derivado', 'pendiente'];
        $pendingGoodsStatuses = ['recibido_en_porteria', 'derivado_a_departamento', 'pendiente'];

        $pendingItems = PorterReceivedItem::query()
            ->whereIn('status', $pendingItemStatuses)
            ->orderBy('received_at')
            ->limit(5)
            ->get([
                'id',
                'recipient_type',
                'recipient_label',
                'description',
                'status',
                'received_at',
                'student_profile_id',
                'department_id',
            ]);

        $activeVisits = PorterVisit::query()
            ->where('status', 'en_curso')
            ->orderBy('entered_at')
            ->limit(5)
            ->get([
                'id',
                'visitor_name',
                'purpose',
                'visited_person_label',
                'entered_at',
                'status',
            ]);

        $activeExternalServices = PorterExternalServiceEntry::query()
            ->where('status', 'en_curso')
            ->orderBy('entered_at')
            ->limit(5)
            ->get([
                'id',
                'service_type',
                'company_name',
                'contact_name',
                'vehicle_plate',
                'entered_at',
                'status',
            ]);

        $activeKeyLoans = PorterKeyLoan::query()
            ->with('porterKey:id,code,name')
            ->whereIn('status', ['prestada', 'observada'])
            ->orderBy('checked_out_at')
            ->limit(5)
            ->get([
                'id',
                'porter_key_id',
                'requester_name',
                'checked_out_at',
                'expected_return_at',
                'status',
            ]);

        $highlightedDailyLogs = PorterDailyLogEntry::query()
            ->with('registeredBy:id,name')
            ->whereDate('logged_on', today())
            ->whereIn('priority', ['alta'])
            ->latest('logged_at')
            ->limit(5)
            ->get();

        $pendingGoods = PorterGoodsMovement::query()
            ->whereIn('status', $pendingGoodsStatuses)
            ->orderBy('moved_at')
            ->limit(5)
            ->get([
                'id',
                'movement_type',
                'goods_detail',
                'status',
                'moved_at',
                'department_id',
                'contact_name',
            ]);

        $observedWithdrawals = PorterStudentWithdrawal::query()
            ->with(['studentProfile:id,first_name,last_name'])
            ->whereIn('status', ['observado', 'rechazado'])
            ->latest('withdrawn_at')
            ->limit(6)
            ->get();

        $recentMovements = PorterMovementLog::query()
            ->with(['performedBy:id,name'])
            ->latest('performed_at')
            ->limit(10)
            ->get();

        $alerts = collect();

        foreach ($observedWithdrawals as $withdrawal) {
            $alerts->push([
                'kind' => 'withdrawal',
                'priority' => 'high',
                'label' => 'Retiro observado',
                'detail' => sprintf('%s - %s', $withdrawal->student_full_name_snapshot, $withdrawal->person_name),
                'when' => $withdrawal->withdrawn_at,
            ]);
        }

        foreach ($pendingItems as $item) {
            if ($item->received_at && $item->received_at->lt(now()->subHours(8))) {
                $alerts->push([
                    'kind' => 'received_item',
                    'priority' => 'medium',
                    'label' => 'Objeto pendiente por más de 8 horas',
                    'detail' => $item->description,
                    'when' => $item->received_at,
                ]);
            }
        }

        foreach ($pendingGoods as $movement) {
            if ($movement->moved_at && $movement->moved_at->lt(now()->subHours(8))) {
                $alerts->push([
                    'kind' => 'goods',
                    'priority' => 'medium',
                    'label' => 'Mercadería pendiente de derivación',
                    'detail' => $movement->goods_detail,
                    'when' => $movement->moved_at,
                ]);
            }
        }

        foreach ($activeVisits as $visit) {
            if ($visit->entered_at && $visit->entered_at->lt(now()->subHours(4))) {
                $alerts->push([
                    'kind' => 'visit',
                    'priority' => 'medium',
                    'label' => 'Visita sin salida registrada',
                    'detail' => sprintf('%s - %s', $visit->visitor_name, $visit->purpose),
                    'when' => $visit->entered_at,
                ]);
            }
        }

        foreach ($activeExternalServices as $entry) {
            if ($entry->entered_at && $entry->entered_at->lt(now()->subHours(6))) {
                $alerts->push([
                    'kind' => 'external_service',
                    'priority' => 'medium',
                    'label' => 'Proveedor aún dentro del recinto',
                    'detail' => trim(($entry->company_name ?: $entry->contact_name) . ' - ' . $entry->service_type),
                    'when' => $entry->entered_at,
                ]);
            }
        }

        foreach ($activeKeyLoans as $loan) {
            if ($loan->expected_return_at && $loan->expected_return_at->lt(now())) {
                $alerts->push([
                    'kind' => 'key_loan',
                    'priority' => 'high',
                    'label' => 'Llave con devolución vencida',
                    'detail' => sprintf('%s - %s', $loan->porterKey?->name ?: 'Llave', $loan->requester_name),
                    'when' => $loan->expected_return_at,
                ]);
            }
        }

        foreach ($highlightedDailyLogs as $entry) {
            $alerts->push([
                'kind' => 'daily_log',
                'priority' => 'high',
                'label' => 'Bitácora destacada',
                'detail' => $entry->title,
                'when' => $entry->logged_at,
            ]);
        }

        return response()->json([
            'stats' => [
                'withdrawals_today' => PorterStudentWithdrawal::query()->where('withdrawn_at', '>=', $today)->count(),
                'pending_items' => PorterReceivedItem::query()->whereIn('status', $pendingItemStatuses)->count(),
                'pending_goods' => PorterGoodsMovement::query()->whereIn('status', $pendingGoodsStatuses)->count(),
                'observed_withdrawals' => PorterStudentWithdrawal::query()->where('status', 'observado')->count(),
                'active_visits' => PorterVisit::query()->where('status', 'en_curso')->count(),
                'active_external_services' => PorterExternalServiceEntry::query()->where('status', 'en_curso')->count(),
                'keys_out' => PorterKeyLoan::query()->where('status', 'prestada')->count(),
                'students_with_pickup_restriction' => StudentProfile::query()->where('pickup_restriction', true)->count(),
                'alerts_total' => $alerts->count(),
            ],
            'pending_deliveries' => [
                'items' => $pendingItems,
                'goods' => $pendingGoods,
            ],
            'active_controls' => [
                'visits' => $activeVisits,
                'external_services' => $activeExternalServices,
                'key_loans' => $activeKeyLoans,
                'daily_logs' => $highlightedDailyLogs,
            ],
            'alerts' => $alerts
                ->sortByDesc(fn ($alert) => $alert['when']?->timestamp ?? 0)
                ->values()
                ->take(8)
                ->all(),
            'recent_movements' => $recentMovements,
            'withdrawals_today' => PorterStudentWithdrawal::query()
                ->with(['registeredBy:id,name', 'studentProfile:id,first_name,last_name'])
                ->where('withdrawn_at', '>=', $today)
                ->latest('withdrawn_at')
                ->limit(8)
                ->get(),
        ]);
    }
}
