<?php

namespace App\Services\Convivencia;

use App\Models\Convivencia\ConvivenciaCase;
use App\Models\Convivencia\ConvivenciaDailyLog;
use App\Models\Convivencia\ConvivenciaDerivation;
use App\Models\Convivencia\ConvivenciaIdpsResult;
use App\Models\Convivencia\ConvivenciaInterview;
use App\Models\Convivencia\ConvivenciaMeasure;
use App\Models\Convivencia\ConvivenciaSociogram;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ConvivenciaReportService
{
    public function __construct(
        private readonly ConvivenciaAccessService $accessService,
    ) {
    }

    public function buildCourseReport(User $user, array $filters): array
    {
        $courseSectionId = $filters['course_section_id'] ?? null;

        $caseQuery = $this->accessService->applyCaseVisibility(ConvivenciaCase::query(), $user);
        $dailyLogQuery = $this->accessService->applyDailyLogVisibility(ConvivenciaDailyLog::query(), $user);
        $derivationQuery = $this->accessService->applyDerivationVisibility(ConvivenciaDerivation::query(), $user);
        $interviewQuery = $this->accessService->applyInterviewVisibility(ConvivenciaInterview::query(), $user);
        $measureQuery = $this->accessService->applyMeasureVisibility(ConvivenciaMeasure::query(), $user);
        $sociogramQuery = $this->accessService->applySociogramVisibility(ConvivenciaSociogram::query(), $user);
        $idpsQuery = ConvivenciaIdpsResult::query();

        $this->applyCourseFilters($caseQuery, $filters, 'opened_at');
        $this->applyCourseFilters($dailyLogQuery, $filters, 'happened_at');
        $this->applyCourseFilters($derivationQuery, $filters, 'derived_at');
        $this->applyCourseFilters($interviewQuery, $filters, 'interview_at');
        $this->applyCourseFilters($measureQuery, $filters, 'assigned_at');
        $this->applyCourseFilters($sociogramQuery, $filters, 'applied_on');
        $this->applyCourseFilters($idpsQuery, $filters, null);

        $latestSociogram = $courseSectionId
            ? (clone $sociogramQuery)->where('course_section_id', $courseSectionId)->latest('applied_on')->first()
            : (clone $sociogramQuery)->latest('applied_on')->first();

        $climateIdps = (clone $idpsQuery)
            ->when($courseSectionId, fn ($query) => $query->where('course_section_id', $courseSectionId))
            ->whereHas('dimension', fn ($query) => $query->where('code', 'clima_convivencia'))
            ->latest('id')
            ->first();

        return [
            'summary' => [
                'climate' => $climateIdps ? [
                    'score' => $climateIdps->score,
                    'percentage' => $climateIdps->percentage,
                    'observations' => $climateIdps->qualitative_observations,
                ] : null,
                'conflicts_registered' => (clone $dailyLogQuery)->count(),
                'open_cases' => (clone $caseQuery)->whereNotIn('status', ['cerrado', 'archivado'])->count(),
                'closed_cases' => (clone $caseQuery)->where('status', 'cerrado')->count(),
                'tardiness' => (clone $dailyLogQuery)
                    ->where(function (Builder $query) {
                        $query
                            ->where('daily_log_type_label', 'like', '%Atraso%')
                            ->orWhereHas('type', fn ($sub) => $sub->where('code', 'atraso'));
                    })
                    ->count(),
                'derivations' => (clone $derivationQuery)->count(),
                'interviews' => (clone $interviewQuery)->count(),
                'measures' => (clone $measureQuery)->count(),
                'attendance_note' => 'Sin integración directa con asistencia en la arquitectura actual.',
                'alerts' => [
                    'overdue_measures' => (clone $measureQuery)->whereIn('status', ['asignada', 'en_proceso', 'reprogramada'])->where('due_at', '<', now())->count(),
                    'open_cases' => (clone $caseQuery)->whereNotIn('status', ['cerrado', 'archivado'])->count(),
                ],
            ],
            'sociogram' => $latestSociogram ? [
                'title' => $latestSociogram->title,
                'applied_on' => $latestSociogram->applied_on,
                'summary' => $latestSociogram->result_summary,
                'interpretation' => $latestSociogram->interpretation,
            ] : null,
            'lists' => [
                'cases' => (clone $caseQuery)->latest('opened_at')->limit(50)->get(['id', 'folio', 'opened_at', 'classification_label', 'criticality_label', 'status']),
                'daily_logs' => (clone $dailyLogQuery)->latest('happened_at')->limit(50)->get(['id', 'happened_at', 'daily_log_type_label', 'description', 'status']),
                'derivations' => (clone $derivationQuery)->latest('derived_at')->limit(50)->get(['id', 'scope', 'derived_at', 'destination_label', 'status', 'priority_level']),
                'interviews' => (clone $interviewQuery)->latest('interview_at')->limit(50)->get(['id', 'interview_at', 'interview_type_label', 'motive', 'follow_up_status']),
                'measures' => (clone $measureQuery)->latest('assigned_at')->limit(50)->get(['id', 'assigned_at', 'measure_type_label', 'status', 'due_at']),
            ],
        ];
    }

    private function applyCourseFilters(Builder $query, array $filters, ?string $dateColumn): void
    {
        $query
            ->when($filters['academic_year_id'] ?? null, fn ($builder, $value) => $builder->where('academic_year_id', $value))
            ->when($filters['course_section_id'] ?? null, fn ($builder, $value) => $builder->where('course_section_id', $value))
            ->when($filters['education_level_id'] ?? null, fn ($builder, $value) => $builder->whereHas('courseSection', fn ($sub) => $sub->where('education_level_id', $value)));

        if (($filters['semester'] ?? null) && $dateColumn) {
            $semester = (int) $filters['semester'];
            $query->whereMonth($dateColumn, $semester === 1 ? '<=' : '>=', $semester === 1 ? 6 : 7);
        }

        if (($filters['month'] ?? null) && $dateColumn) {
            $query->whereMonth($dateColumn, (int) $filters['month']);
        }

        if ($dateColumn) {
            $query
                ->when($filters['from'] ?? null, fn ($builder, $value) => $builder->whereDate($dateColumn, '>=', $value))
                ->when($filters['to'] ?? null, fn ($builder, $value) => $builder->whereDate($dateColumn, '<=', $value));
        }
    }
}
