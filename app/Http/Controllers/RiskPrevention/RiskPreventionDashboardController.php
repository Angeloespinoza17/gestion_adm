<?php

namespace App\Http\Controllers\RiskPrevention;

use App\Http\Controllers\Controller;
use App\Models\RiskPrevention\RiskPreventionAccident;
use App\Models\RiskPrevention\RiskPreventionDocument;
use App\Models\RiskPrevention\RiskPreventionEppDelivery;
use App\Models\RiskPrevention\RiskPreventionFireExtinguisher;
use App\Models\RiskPrevention\RiskPreventionTraining;
use App\Models\RiskPrevention\RiskPreventionTrainingParticipant;
use App\Services\RiskPrevention\RiskPreventionAccessService;
use Illuminate\Http\JsonResponse;

class RiskPreventionDashboardController extends Controller
{
    public function __construct(
        private readonly RiskPreventionAccessService $accessService,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        abort_unless($this->accessService->canView(request()->user()), 403);

        $this->accessService->refreshDynamicStatuses();
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        return response()->json([
            'metrics' => [
                'extinguishers_due' => RiskPreventionFireExtinguisher::query()
                    ->whereIn('status', ['por_vencer', 'vencido'])
                    ->count(),
                'accidents_month' => RiskPreventionAccident::query()
                    ->whereBetween('occurred_at', [$monthStart, $monthEnd])
                    ->count(),
                'trainings_pending' => RiskPreventionTrainingParticipant::query()
                    ->where('compliance_status', 'pendiente')
                    ->count(),
                'epp_due' => RiskPreventionEppDelivery::query()
                    ->where('status', 'por_reponer')
                    ->count(),
                'documents_due' => RiskPreventionDocument::query()
                    ->whereIn('status', ['por_vencer', 'vencido'])
                    ->count(),
            ],
            'extinguisher_alert_summary' => [
                'days_30' => RiskPreventionFireExtinguisher::query()
                    ->where('status', 'por_vencer')
                    ->whereDate('expires_at', '>', now()->addDays(15)->startOfDay())
                    ->count(),
                'days_15' => RiskPreventionFireExtinguisher::query()
                    ->where('status', 'por_vencer')
                    ->whereBetween('expires_at', [now()->startOfDay(), now()->addDays(15)->startOfDay()])
                    ->count(),
                'days_7' => RiskPreventionFireExtinguisher::query()
                    ->where('status', 'por_vencer')
                    ->whereBetween('expires_at', [now()->startOfDay(), now()->addDays(7)->startOfDay()])
                    ->count(),
                'expired' => RiskPreventionFireExtinguisher::query()
                    ->where('status', 'vencido')
                    ->count(),
            ],
            'extinguisher_alerts' => RiskPreventionFireExtinguisher::query()
                ->whereIn('status', ['por_vencer', 'vencido'])
                ->orderBy('expires_at')
                ->limit(8)
                ->get(),
            'recent_accidents' => RiskPreventionAccident::query()
                ->orderByDesc('occurred_at')
                ->limit(8)
                ->get(),
            'pending_trainings' => RiskPreventionTraining::query()
                ->with(['participants' => fn ($query) => $query->where('compliance_status', 'pendiente')->orderBy('employee_name')])
                ->whereHas('participants', fn ($query) => $query->where('compliance_status', 'pendiente'))
                ->orderByDesc('training_date')
                ->limit(8)
                ->get(),
            'epp_due_list' => RiskPreventionEppDelivery::query()
                ->with('item:id,name,epp_type,unit')
                ->where('status', 'por_reponer')
                ->orderBy('replacement_due_at')
                ->limit(8)
                ->get(),
            'documents_due_list' => RiskPreventionDocument::query()
                ->whereIn('status', ['por_vencer', 'vencido'])
                ->orderBy('valid_until')
                ->limit(8)
                ->get(),
        ]);
    }
}
