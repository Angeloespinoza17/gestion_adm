<?php

namespace App\Services\ApoyoProfesional;

use App\Models\ApoyoProfesional\ApoyoAtencion;
use App\Models\ApoyoProfesional\ApoyoDerivacion;
use App\Models\ApoyoProfesional\ApoyoEntrevista;
use App\Models\ApoyoProfesional\ApoyoPlan;
use App\Models\ApoyoProfesional\ApoyoSeguimiento;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class ApoyoProfesionalReportService
{
    public function __construct(
        private readonly ApoyoProfesionalAccessService $accessService,
    ) {
    }

    public function build(User $user, array $filters): array
    {
        [$from, $to] = $this->resolveRange($filters);
        $anonymize = (bool) ($filters['anonymize'] ?? false);

        $attentionQuery = $this->accessService->applyAttentionVisibility(
            ApoyoAtencion::query()
                ->with(['student:id,first_name,last_name,rut', 'attendedBy:id,name']),
            $user,
        )->whereBetween('attended_at', [$from, $to]);

        $derivationQuery = $this->accessService->applyDerivationVisibility(
            ApoyoDerivacion::query()->with(['student:id,first_name,last_name,rut', 'destinationUser:id,name']),
            $user,
        )->whereBetween('derived_at', [$from, $to]);

        $visibleAttentionIds = (clone $attentionQuery)->pluck('id');
        $studentIds = (clone $attentionQuery)->pluck('student_profile_id');

        $followUpQuery = ApoyoSeguimiento::query()
            ->when($visibleAttentionIds->isNotEmpty(), fn ($query) => $query->whereIn('attention_id', $visibleAttentionIds), fn ($query) => $query->whereRaw('1 = 0'))
            ->whereBetween('scheduled_at', [$from, $to]);
        $planQuery = ApoyoPlan::query()
            ->when($studentIds->isNotEmpty(), fn ($query) => $query->whereIn('student_profile_id', $studentIds), fn ($query) => $query->whereRaw('1 = 0'))
            ->whereDate('start_date', '<=', $to->toDateString())
            ->where(function (Builder $query) use ($from) {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', $from->toDateString());
            });
        $interviewQuery = ApoyoEntrevista::query()
            ->when($studentIds->isNotEmpty(), fn ($query) => $query->whereIn('student_profile_id', $studentIds), fn ($query) => $query->whereRaw('1 = 0'))
            ->whereBetween('interview_at', [$from, $to]);

        $this->applyFilters($filters, $attentionQuery, $derivationQuery, $followUpQuery, $planQuery, $interviewQuery);

        $attentions = (clone $attentionQuery)->latest('attended_at')->limit(120)->get();
        $derivations = (clone $derivationQuery)->latest('derived_at')->limit(120)->get();
        $followUps = (clone $followUpQuery)->latest('scheduled_at')->limit(120)->get();
        $plans = (clone $planQuery)->latest('start_date')->limit(120)->get();
        $interviews = (clone $interviewQuery)->latest('interview_at')->limit(120)->get();

        return [
            'date_range' => [
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
                'period' => $filters['period'] ?? 'mensual',
                'anonymized' => $anonymize,
            ],
            'summary' => [
                'attentions_total' => (clone $attentionQuery)->count(),
                'derivations_total' => (clone $derivationQuery)->count(),
                'follow_ups_total' => (clone $followUpQuery)->count(),
                'plans_total' => (clone $planQuery)->count(),
                'interviews_total' => (clone $interviewQuery)->count(),
                'confidential_cases_total' => (clone $attentionQuery)
                    ->where(function (Builder $query) {
                        $query
                            ->where('is_confidential_case', true)
                            ->orWhereIn('confidentiality_level', ['confidencial', 'alta_confidencialidad']);
                    })
                    ->count(),
            ],
            'attentions_by_professional' => (clone $attentionQuery)
                ->selectRaw('professional_role_name as label, COUNT(*) as total')
                ->groupBy('label')
                ->orderByDesc('total')
                ->get(),
            'attentions_by_course' => (clone $attentionQuery)
                ->selectRaw('COALESCE(course_name_snapshot, "Sin curso") as label, COUNT(*) as total')
                ->groupBy('label')
                ->orderByDesc('total')
                ->get(),
            'attentions_by_type' => (clone $attentionQuery)
                ->selectRaw('COALESCE(attention_type_label, "Sin tipo") as label, COUNT(*) as total')
                ->groupBy('label')
                ->orderByDesc('total')
                ->get(),
            'attentions_by_motive' => (clone $attentionQuery)
                ->selectRaw('COALESCE(motive_label, reason_summary) as label, COUNT(*) as total')
                ->groupBy('label')
                ->orderByDesc('total')
                ->limit(10)
                ->get(),
            'attentions_by_status' => (clone $attentionQuery)
                ->selectRaw('status as label, COUNT(*) as total')
                ->groupBy('label')
                ->orderByDesc('total')
                ->get(),
            'attentions_by_area' => (clone $attentionQuery)
                ->selectRaw('COALESCE(professional_area_name, "Sin área") as label, COUNT(*) as total')
                ->groupBy('label')
                ->orderByDesc('total')
                ->get(),
            'derivations_by_area' => (clone $derivationQuery)
                ->selectRaw('destination_area_name as label, COUNT(*) as total')
                ->groupBy('label')
                ->orderByDesc('total')
                ->get(),
            'follow_ups_by_status' => (clone $followUpQuery)
                ->selectRaw('status as label, COUNT(*) as total')
                ->groupBy('label')
                ->orderByDesc('total')
                ->get(),
            'plans_by_status' => (clone $planQuery)
                ->selectRaw('status as label, COUNT(*) as total')
                ->groupBy('label')
                ->orderByDesc('total')
                ->get(),
            'confidentiality_breakdown' => (clone $attentionQuery)
                ->selectRaw('confidentiality_level as label, COUNT(*) as total')
                ->groupBy('label')
                ->orderByDesc('total')
                ->get(),
            'detail_rows' => [
                'attentions' => $this->transformAttentionRows($attentions, $anonymize),
                'derivations' => $this->transformDerivationRows($derivations, $anonymize),
                'follow_ups' => $followUps,
                'plans' => $plans,
                'interviews' => $this->transformInterviewRows($interviews, $anonymize),
            ],
        ];
    }

    private function applyFilters(
        array $filters,
        Builder $attentionQuery,
        Builder $derivationQuery,
        Builder $followUpQuery,
        Builder $planQuery,
        Builder $interviewQuery,
    ): void {
        $courseSectionId = $filters['course_section_id'] ?? null;
        $studentId = $filters['student_profile_id'] ?? null;
        $professionalId = $filters['professional_id'] ?? null;
        $roleName = trim((string) ($filters['professional_role_name'] ?? ''));
        $attentionType = trim((string) ($filters['attention_type_label'] ?? ''));
        $motive = trim((string) ($filters['motive_label'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $area = trim((string) ($filters['professional_area_name'] ?? ''));
        $confidentiality = trim((string) ($filters['confidentiality_level'] ?? ''));

        $attentionQuery
            ->when($courseSectionId, fn ($query) => $query->where('course_section_id', $courseSectionId))
            ->when($studentId, fn ($query) => $query->where('student_profile_id', $studentId))
            ->when($professionalId, fn ($query) => $query->where('attended_by_user_id', $professionalId))
            ->when($roleName !== '', fn ($query) => $query->where('professional_role_name', $roleName))
            ->when($attentionType !== '', fn ($query) => $query->where('attention_type_label', $attentionType))
            ->when($motive !== '', fn ($query) => $query->where('motive_label', $motive))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($area !== '', fn ($query) => $query->where('professional_area_name', $area))
            ->when($confidentiality !== '', fn ($query) => $query->where('confidentiality_level', $confidentiality));

        $derivationQuery
            ->when($studentId, fn ($query) => $query->where('student_profile_id', $studentId))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($area !== '', fn ($query) => $query->where('destination_area_name', $area))
            ->when($confidentiality !== '', fn ($query) => $query->where('confidentiality_level', $confidentiality));

        $followUpQuery
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($studentId, fn ($query) => $query->where('student_profile_id', $studentId));

        $planQuery
            ->when($studentId, fn ($query) => $query->where('student_profile_id', $studentId))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($area !== '', fn ($query) => $query->where('area_name', $area))
            ->when($confidentiality !== '', fn ($query) => $query->where('confidentiality_level', $confidentiality));

        $interviewQuery
            ->when($studentId, fn ($query) => $query->where('student_profile_id', $studentId))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($confidentiality !== '', fn ($query) => $query->where('confidentiality_level', $confidentiality));
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(array $filters): array
    {
        $period = trim((string) ($filters['period'] ?? 'mensual'));
        $from = trim((string) ($filters['from'] ?? ''));
        $to = trim((string) ($filters['to'] ?? ''));

        if ($from !== '' || $to !== '') {
            return [
                $from !== '' ? Carbon::parse($from)->startOfDay() : now()->startOfMonth(),
                $to !== '' ? Carbon::parse($to)->endOfDay() : now()->endOfDay(),
            ];
        }

        return match ($period) {
            'diario' => [now()->startOfDay(), now()->endOfDay()],
            'semanal' => [now()->startOfWeek(), now()->endOfWeek()],
            'semestral' => [now()->copy()->subMonths(6)->startOfDay(), now()->endOfDay()],
            'anual' => [now()->startOfYear(), now()->endOfYear()],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };
    }

    private function transformAttentionRows($rows, bool $anonymize)
    {
        return $rows->map(function (ApoyoAtencion $attention) use ($anonymize) {
            if (!$anonymize) {
                return $attention;
            }

            $clone = $attention->replicate();
            $clone->student_full_name_snapshot = 'Estudiante anonimizado';
            $clone->student_rut_snapshot = null;
            $clone->setRelation('student', null);

            return $clone;
        })->values();
    }

    private function transformDerivationRows($rows, bool $anonymize)
    {
        return $rows->map(function (ApoyoDerivacion $derivation) use ($anonymize) {
            if (!$anonymize) {
                return $derivation;
            }

            $derivation->setRelation('student', null);

            return $derivation;
        })->values();
    }

    private function transformInterviewRows($rows, bool $anonymize)
    {
        return $rows->map(function (ApoyoEntrevista $interview) use ($anonymize) {
            if (!$anonymize) {
                return $interview;
            }

            $interview->setRelation('student', null);

            return $interview;
        })->values();
    }
}
