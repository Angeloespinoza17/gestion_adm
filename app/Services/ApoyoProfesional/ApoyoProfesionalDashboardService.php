<?php

namespace App\Services\ApoyoProfesional;

use App\Models\ApoyoProfesional\ApoyoAtencion;
use App\Models\ApoyoProfesional\ApoyoDerivacion;
use App\Models\ApoyoProfesional\ApoyoEntrevista;
use App\Models\ApoyoProfesional\ApoyoPlan;
use App\Models\ApoyoProfesional\ApoyoSeguimiento;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class ApoyoProfesionalDashboardService
{
    public function __construct(
        private readonly ApoyoProfesionalAccessService $accessService,
    ) {
    }

    public function build(User $user): array
    {
        $today = now()->startOfDay();
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();
        $rangeStart = now()->copy()->subMonths(11)->startOfMonth();

        $attentionQuery = $this->accessService->applyAttentionVisibility(
            ApoyoAtencion::query(),
            $user,
        );

        $derivationQuery = $this->accessService->applyDerivationVisibility(
            ApoyoDerivacion::query(),
            $user,
        );

        $visibleAttentionIds = (clone $attentionQuery)->pluck('id');
        $studentIds = (clone $attentionQuery)->pluck('student_profile_id');

        $followUpQuery = ApoyoSeguimiento::query()
            ->when($visibleAttentionIds->isNotEmpty(), fn ($query) => $query->whereIn('attention_id', $visibleAttentionIds), fn ($query) => $query->whereRaw('1 = 0'));
        $planQuery = ApoyoPlan::query()
            ->when($studentIds->isNotEmpty(), fn ($query) => $query->whereIn('student_profile_id', $studentIds), fn ($query) => $query->whereRaw('1 = 0'));
        $interviewQuery = ApoyoEntrevista::query()
            ->when($studentIds->isNotEmpty(), fn ($query) => $query->whereIn('student_profile_id', $studentIds), fn ($query) => $query->whereRaw('1 = 0'));

        $months = collect(CarbonPeriod::create($rangeStart, '1 month', now()->startOfMonth()))
            ->map(fn ($month) => $month->format('Y-m'));

        $attentionsByMonth = (clone $attentionQuery)
            ->selectRaw("DATE_FORMAT(attended_at, '%Y-%m') as label, COUNT(*) as total")
            ->where('attended_at', '>=', $rangeStart)
            ->groupBy('label')
            ->pluck('total', 'label');

        return [
            'metrics' => [
                'attentions_today' => (clone $attentionQuery)->where('attended_at', '>=', $today)->count(),
                'attentions_month' => (clone $attentionQuery)->whereBetween('attended_at', [$monthStart, $monthEnd])->count(),
                'open_attentions' => (clone $attentionQuery)->whereNotIn('status', ['cerrada', 'anulada'])->count(),
                'closed_attentions' => (clone $attentionQuery)->where('status', 'cerrada')->count(),
                'pending_follow_ups' => (clone $followUpQuery)->whereIn('status', ['pendiente', 'reprogramado'])->count(),
                'active_cases' => (clone $attentionQuery)->whereNotIn('status', ['cerrada', 'anulada'])->count(),
                'pending_derivations' => (clone $derivationQuery)->whereIn('status', ['enviada', 'recibida', 'en_revision', 'en_seguimiento'])->count(),
                'students_with_multiple_attentions' => DB::query()
                    ->fromSub(
                        (clone $attentionQuery)
                            ->select('student_profile_id', DB::raw('COUNT(*) as total'))
                            ->groupBy('student_profile_id'),
                        'student_attention_counts'
                    )
                    ->where('total', '>', 1)
                    ->count(),
                'confidential_attentions' => (clone $attentionQuery)
                    ->where(function ($query) {
                        $query
                            ->where('is_confidential_case', true)
                            ->orWhereIn('confidentiality_level', ['confidencial', 'alta_confidencialidad']);
                    })
                    ->count(),
                'cases_escalated_direction' => (clone $attentionQuery)->whereNotNull('escalated_to_direction_at')->count(),
                'cases_derived_convivencia' => (clone $attentionQuery)->whereNotNull('derived_to_convivencia_at')->count(),
                'cases_derived_pie' => (clone $attentionQuery)->whereNotNull('derived_to_pie_at')->count(),
            ],
            'alerts' => [
                'open_attentions' => (clone $attentionQuery)->whereNotIn('status', ['cerrada', 'anulada'])->count(),
                'pending_follow_ups' => (clone $followUpQuery)->whereIn('status', ['pendiente', 'reprogramado'])->count(),
                'overdue_follow_ups' => (clone $followUpQuery)
                    ->whereIn('status', ['pendiente', 'reprogramado'])
                    ->where('scheduled_at', '<', now())
                    ->count(),
                'pending_derivations' => (clone $derivationQuery)->whereIn('status', ['enviada', 'recibida', 'en_revision', 'en_seguimiento'])->count(),
                'urgent_cases' => (clone $attentionQuery)->where('priority_level', 'urgente')->whereNotIn('status', ['cerrada', 'anulada'])->count(),
                'confidential_cases' => (clone $attentionQuery)
                    ->where(function ($query) {
                        $query
                            ->where('is_confidential_case', true)
                            ->orWhereIn('confidentiality_level', ['confidencial', 'alta_confidencialidad']);
                    })
                    ->count(),
                'active_plans' => (clone $planQuery)->whereIn('status', ['disenado', 'en_ejecucion', 'en_seguimiento'])->count(),
                'upcoming_interviews' => (clone $interviewQuery)->whereBetween('interview_at', [now(), now()->copy()->addDays(7)])->count(),
                'cases_without_recent_follow_up' => (clone $attentionQuery)
                    ->whereNotIn('status', ['cerrada', 'anulada'])
                    ->whereDoesntHave('followUps', fn ($query) => $query->where('scheduled_at', '>=', now()->copy()->subDays(15)))
                    ->count(),
            ],
            'charts' => [
                'attentions_by_month' => [
                    'labels' => $months->values(),
                    'series' => $months->map(fn ($month) => (int) ($attentionsByMonth[$month] ?? 0))->values(),
                ],
                'attentions_by_professional' => (clone $attentionQuery)
                    ->selectRaw('COALESCE(professional_role_name, "Sin profesional") as label, COUNT(*) as total')
                    ->groupBy('label')
                    ->orderByDesc('total')
                    ->limit(8)
                    ->get(),
                'attentions_by_course' => (clone $attentionQuery)
                    ->selectRaw('COALESCE(course_name_snapshot, "Sin curso") as label, COUNT(*) as total')
                    ->groupBy('label')
                    ->orderByDesc('total')
                    ->limit(8)
                    ->get(),
                'frequent_motives' => (clone $attentionQuery)
                    ->selectRaw('COALESCE(motive_label, reason_summary) as label, COUNT(*) as total')
                    ->groupBy('label')
                    ->orderByDesc('total')
                    ->limit(8)
                    ->get(),
                'pending_follow_ups' => (clone $followUpQuery)
                    ->selectRaw('status as label, COUNT(*) as total')
                    ->groupBy('label')
                    ->orderByDesc('total')
                    ->get(),
                'derivations_by_area' => (clone $derivationQuery)
                    ->selectRaw('destination_area_name as label, COUNT(*) as total')
                    ->groupBy('label')
                    ->orderByDesc('total')
                    ->get(),
                'open_vs_closed_cases' => [
                    ['label' => 'Abiertos', 'total' => (clone $attentionQuery)->whereNotIn('status', ['cerrada', 'anulada'])->count()],
                    ['label' => 'Cerrados', 'total' => (clone $attentionQuery)->where('status', 'cerrada')->count()],
                ],
            ],
            'breakdowns' => [
                'attentions_by_professional_area' => (clone $attentionQuery)
                    ->selectRaw('COALESCE(professional_area_name, "Sin área") as label, COUNT(*) as total')
                    ->groupBy('label')
                    ->orderByDesc('total')
                    ->get(),
                'attentions_by_role' => (clone $attentionQuery)
                    ->selectRaw('COALESCE(professional_role_name, "Sin rol") as label, COUNT(*) as total')
                    ->groupBy('label')
                    ->orderByDesc('total')
                    ->get(),
            ],
            'recent' => [
                'attentions' => (clone $attentionQuery)
                    ->with('student:id,first_name,last_name')
                    ->latest('attended_at')
                    ->limit(6)
                    ->get(['id', 'student_profile_id', 'attended_at', 'professional_role_name', 'reason_summary', 'status', 'priority_level']),
                'derivations' => (clone $derivationQuery)
                    ->with('student:id,first_name,last_name')
                    ->latest('derived_at')
                    ->limit(6)
                    ->get(['id', 'student_profile_id', 'derived_at', 'destination_area_name', 'status', 'urgency_level']),
                'follow_ups' => (clone $followUpQuery)
                    ->latest('scheduled_at')
                    ->limit(6)
                    ->get(['id', 'attention_id', 'student_profile_id', 'scheduled_at', 'status', 'comment']),
            ],
        ];
    }
}
