<?php

namespace App\Http\Controllers\Infirmary;

use App\Http\Controllers\Controller;
use App\Models\Infirmary\InfirmaryAccident;
use App\Models\Infirmary\InfirmaryAttention;
use App\Models\Infirmary\InfirmaryAttentionCall;
use App\Models\Infirmary\InfirmaryAttentionFollowUp;
use App\Models\Infirmary\InfirmaryAttentionReferral;
use App\Models\Infirmary\InfirmaryAttentionTreatment;
use App\Models\Infirmary\InfirmaryMedication;
use App\Models\Infirmary\InfirmaryMedicationAdministration;
use App\Models\Infirmary\InfirmaryMedicationAuthorization;
use App\Services\Infirmary\InfirmaryAccessService;
use App\Services\Infirmary\InfirmaryMedicationStockService;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class InfirmaryDashboardController extends Controller
{
    public function __construct(
        private readonly InfirmaryAccessService $accessService,
        private readonly InfirmaryMedicationStockService $stockService,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        abort_unless($this->accessService->canViewModule(request()->user()), 403);
        $this->stockService->refreshDynamicStatuses();

        $today = now()->startOfDay();
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();
        $rangeStart = now()->copy()->subDays(13)->startOfDay();

        $days = collect(CarbonPeriod::create($rangeStart, now()->startOfDay()))
            ->map(fn ($day) => $day->format('Y-m-d'));

        $attentionsByDay = InfirmaryAttention::query()
            ->selectRaw('DATE(attended_at) as label, COUNT(*) as total')
            ->whereBetween('attended_at', [$rangeStart, now()->endOfDay()])
            ->groupBy('label')
            ->pluck('total', 'label');

        $accidentsByMonth = InfirmaryAccident::query()
            ->selectRaw("DATE_FORMAT(occurred_at, '%Y-%m') as label, COUNT(*) as total")
            ->where('occurred_at', '>=', now()->subMonths(11)->startOfMonth())
            ->groupBy('label')
            ->orderBy('label')
            ->get();

        $recentTreatments = InfirmaryAttentionTreatment::query()
            ->where('created_at', '>=', now()->subMonths(6))
            ->get(['treatment_types']);

        $treatmentCounts = [];
        foreach ($recentTreatments as $treatment) {
            foreach (($treatment->treatment_types ?: []) as $type) {
                $treatmentCounts[$type] = ($treatmentCounts[$type] ?? 0) + 1;
            }
        }

        arsort($treatmentCounts);

        return response()->json([
            'metrics' => [
                'attentions_today' => InfirmaryAttention::query()->where('attended_at', '>=', $today)->count(),
                'attentions_month' => InfirmaryAttention::query()->whereBetween('attended_at', [$monthStart, $monthEnd])->count(),
                'accidents_month' => InfirmaryAccident::query()->whereBetween('occurred_at', [$monthStart, $monthEnd])->count(),
                'medications_administered_month' => InfirmaryMedicationAdministration::query()->whereBetween('administered_at', [$monthStart, $monthEnd])->count(),
                'referrals_month' => InfirmaryAttentionReferral::query()->whereBetween('referred_at', [$monthStart, $monthEnd])->count(),
                'calls_month' => InfirmaryAttentionCall::query()->whereBetween('called_at', [$monthStart, $monthEnd])->count(),
                'students_with_permanent_medication' => InfirmaryMedicationAuthorization::query()
                    ->whereIn('status', ['vigente', 'proxima_a_vencer'])
                    ->distinct('student_profile_id')
                    ->count('student_profile_id'),
                'critical_stock' => InfirmaryMedication::query()->whereIn('status', ['stock_bajo', 'agotado'])->count(),
                'expiring_medications' => InfirmaryMedication::query()->whereIn('status', ['proximo_a_vencer', 'vencido'])->count(),
                'average_attention_minutes' => round((float) InfirmaryAttention::query()->avg('attention_duration_minutes'), 1),
            ],
            'alerts' => [
                'expired_medications' => InfirmaryMedication::query()->where('status', 'vencido')->count(),
                'expiring_medications' => InfirmaryMedication::query()->where('status', 'proximo_a_vencer')->count(),
                'critical_stock' => InfirmaryMedication::query()->whereIn('status', ['stock_bajo', 'agotado'])->count(),
                'open_accidents' => InfirmaryAccident::query()->where('case_status', '!=', 'cerrado')->count(),
                'open_attentions' => InfirmaryAttention::query()->where('status', '!=', 'finalizada')->count(),
                'pending_follow_ups' => InfirmaryAttentionFollowUp::query()->where('status', '!=', 'cerrado')->count(),
                'pending_calls' => InfirmaryAttentionCall::query()->where('call_status', 'pendiente')->count(),
                'expiring_authorizations' => InfirmaryMedicationAuthorization::query()->where('status', 'proxima_a_vencer')->count(),
            ],
            'charts' => [
                'attentions_by_day' => [
                    'labels' => $days->values(),
                    'series' => $days->map(fn ($day) => (int) ($attentionsByDay[$day] ?? 0))->values(),
                ],
                'accidents_by_month' => [
                    'labels' => $accidentsByMonth->pluck('label')->values(),
                    'series' => $accidentsByMonth->pluck('total')->map(fn ($value) => (int) $value)->values(),
                ],
                'medications_administered' => InfirmaryMedicationAdministration::query()
                    ->join('infirmary_medications', 'infirmary_medications.id', '=', 'infirmary_medication_administrations.medication_id')
                    ->selectRaw('COALESCE(infirmary_medications.commercial_name, infirmary_medications.name) as label, COUNT(*) as total')
                    ->groupBy('label')
                    ->orderByDesc('total')
                    ->limit(8)
                    ->get(),
                'frequent_treatments' => collect($treatmentCounts)->take(8)->map(fn ($total, $label) => [
                    'label' => $label,
                    'total' => $total,
                ])->values(),
                'referrals' => InfirmaryAttentionReferral::query()
                    ->selectRaw('referral_type as label, COUNT(*) as total')
                    ->groupBy('label')
                    ->orderByDesc('total')
                    ->limit(8)
                    ->get(),
                'accidents_by_place' => InfirmaryAccident::query()
                    ->selectRaw('COALESCE(place, "Sin lugar") as label, COUNT(*) as total')
                    ->groupBy('label')
                    ->orderByDesc('total')
                    ->limit(8)
                    ->get(),
                'attentions_by_course' => InfirmaryAttention::query()
                    ->selectRaw('COALESCE(course_name_snapshot, "Sin curso") as label, COUNT(*) as total')
                    ->groupBy('label')
                    ->orderByDesc('total')
                    ->limit(8)
                    ->get(),
            ],
            'breakdowns' => [
                'accidents_by_course' => InfirmaryAccident::query()
                    ->leftJoin('course_sections', 'course_sections.id', '=', 'infirmary_accidents.course_section_id')
                    ->selectRaw('COALESCE(course_sections.display_name, "Sin curso") as label, COUNT(*) as total')
                    ->groupBy('label')
                    ->orderByDesc('total')
                    ->limit(8)
                    ->get(),
                'accidents_by_dependency' => InfirmaryAccident::query()
                    ->leftJoin('maintenance_dependencies', 'maintenance_dependencies.id', '=', 'infirmary_accidents.dependency_id')
                    ->selectRaw('COALESCE(maintenance_dependencies.name, "Sin dependencia") as label, COUNT(*) as total')
                    ->groupBy('label')
                    ->orderByDesc('total')
                    ->limit(8)
                    ->get(),
                'attentions_by_category' => InfirmaryAttention::query()
                    ->selectRaw('attention_category as label, COUNT(*) as total')
                    ->groupBy('label')
                    ->orderByDesc('total')
                    ->get(),
                'attentions_by_hour' => InfirmaryAttention::query()
                    ->selectRaw('HOUR(attended_at) as hour_label, COUNT(*) as total')
                    ->groupBy('hour_label')
                    ->orderBy('hour_label')
                    ->get()
                    ->map(fn ($row) => ['label' => str_pad((string) $row->hour_label, 2, '0', STR_PAD_LEFT) . ':00', 'total' => (int) $row->total]),
            ],
            'recent' => [
                'attentions' => InfirmaryAttention::query()
                    ->with('student:id,first_name,last_name')
                    ->latest('attended_at')
                    ->limit(6)
                    ->get(['id', 'student_profile_id', 'attended_at', 'attention_category', 'priority', 'status', 'consultation_reason']),
                'accidents' => InfirmaryAccident::query()
                    ->with('student:id,first_name,last_name')
                    ->latest('occurred_at')
                    ->limit(6)
                    ->get(['id', 'student_profile_id', 'occurred_at', 'accident_type', 'severity', 'case_status', 'place']),
                'medication_alerts' => InfirmaryMedication::query()
                    ->whereIn('status', ['stock_bajo', 'agotado', 'proximo_a_vencer', 'vencido'])
                    ->orderBy('expires_at')
                    ->orderBy('current_stock')
                    ->limit(8)
                    ->get(['id', 'name', 'commercial_name', 'current_stock', 'minimum_stock', 'expires_at', 'status']),
            ],
        ]);
    }
}
