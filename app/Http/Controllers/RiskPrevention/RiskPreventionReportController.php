<?php

namespace App\Http\Controllers\RiskPrevention;

use App\Http\Controllers\Controller;
use App\Models\RiskPrevention\RiskPreventionAccident;
use App\Models\RiskPrevention\RiskPreventionDocument;
use App\Models\RiskPrevention\RiskPreventionEmergencyDrill;
use App\Models\RiskPrevention\RiskPreventionEmergencyPlan;
use App\Models\RiskPrevention\RiskPreventionEppDelivery;
use App\Models\RiskPrevention\RiskPreventionFireExtinguisher;
use App\Models\RiskPrevention\RiskPreventionTrainingParticipant;
use App\Services\RiskPrevention\RiskPreventionAccessService;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RiskPreventionReportController extends Controller
{
    public function __construct(
        private readonly RiskPreventionAccessService $accessService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canView($request->user()), 403);

        $this->accessService->refreshDynamicStatuses();

        $from = $request->filled('from') ? Carbon::parse((string) $request->query('from'))->startOfDay() : now()->subDays(90)->startOfDay();
        $to = $request->filled('to') ? Carbon::parse((string) $request->query('to'))->endOfDay() : now()->endOfDay();

        return response()->json([
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'accidents_by_type' => RiskPreventionAccident::query()
                ->selectRaw('accident_type as label, COUNT(*) as total')
                ->whereBetween('occurred_at', [$from, $to])
                ->groupBy('accident_type')
                ->orderByDesc('total')
                ->get(),
            'accidents_by_status' => RiskPreventionAccident::query()
                ->selectRaw('case_status as label, COUNT(*) as total')
                ->whereBetween('occurred_at', [$from, $to])
                ->groupBy('case_status')
                ->orderByDesc('total')
                ->get(),
            'accident_details' => RiskPreventionAccident::query()
                ->whereBetween('occurred_at', [$from, $to])
                ->orderByDesc('occurred_at')
                ->limit(20)
                ->get(),
            'training_compliance' => DB::table('prevent_training_participants')
                ->join('prevent_trainings', 'prevent_trainings.id', '=', 'prevent_training_participants.training_id')
                ->selectRaw('prevent_training_participants.compliance_status as label, COUNT(*) as total')
                ->whereBetween('prevent_trainings.training_date', [$from->toDateString(), $to->toDateString()])
                ->groupBy('prevent_training_participants.compliance_status')
                ->orderByDesc('total')
                ->get(),
            'training_pending_people' => DB::table('prevent_training_participants')
                ->join('prevent_trainings', 'prevent_trainings.id', '=', 'prevent_training_participants.training_id')
                ->select(
                    'prevent_training_participants.employee_name',
                    'prevent_trainings.name as training_name',
                    'prevent_trainings.training_date',
                    'prevent_trainings.modality'
                )
                ->where('prevent_training_participants.compliance_status', 'pendiente')
                ->whereBetween('prevent_trainings.training_date', [$from->toDateString(), $to->toDateString()])
                ->orderBy('prevent_trainings.training_date')
                ->limit(30)
                ->get(),
            'epp_by_employee' => DB::table('prevent_epp_deliveries')
                ->join('prevent_epp_items', 'prevent_epp_items.id', '=', 'prevent_epp_deliveries.epp_item_id')
                ->selectRaw("
                    prevent_epp_deliveries.employee_name as employee_name,
                    COUNT(prevent_epp_deliveries.id) as deliveries_count,
                    SUM(prevent_epp_deliveries.quantity) as total_units,
                    SUM(CASE WHEN prevent_epp_deliveries.status = 'por_reponer' THEN 1 ELSE 0 END) as pending_replacements
                ")
                ->whereBetween('prevent_epp_deliveries.delivered_at', [$from->toDateString(), $to->toDateString()])
                ->groupBy('prevent_epp_deliveries.employee_name')
                ->orderByDesc('deliveries_count')
                ->get(),
            'general_status' => [
                'extinguishers' => RiskPreventionFireExtinguisher::query()
                    ->selectRaw('status as label, COUNT(*) as total')
                    ->groupBy('status')
                    ->orderByDesc('total')
                    ->get(),
                'documents' => RiskPreventionDocument::query()
                    ->selectRaw('status as label, COUNT(*) as total')
                    ->groupBy('status')
                    ->orderByDesc('total')
                    ->get(),
                'open_accidents' => RiskPreventionAccident::query()->where('case_status', '!=', 'cerrado')->count(),
                'pending_trainings' => RiskPreventionTrainingParticipant::query()->where('compliance_status', 'pendiente')->count(),
                'epp_due' => RiskPreventionEppDelivery::query()->where('status', 'por_reponer')->count(),
                'emergency_plans' => RiskPreventionEmergencyPlan::query()->count(),
                'drills_total' => RiskPreventionEmergencyDrill::query()->count(),
            ],
        ]);
    }
}
