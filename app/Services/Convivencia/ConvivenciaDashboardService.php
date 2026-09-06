<?php

namespace App\Services\Convivencia;

use App\Models\Convivencia\ConvivenciaCase;
use App\Models\Convivencia\ConvivenciaComplaint;
use App\Models\Convivencia\ConvivenciaDailyLog;
use App\Models\Convivencia\ConvivenciaDerivation;
use App\Models\Convivencia\ConvivenciaInterview;
use App\Models\Convivencia\ConvivenciaMeasure;
use App\Models\Convivencia\ConvivenciaProtocolActivation;
use App\Models\Convivencia\ConvivenciaProtocolActivationPart;
use App\Models\Convivencia\ConvivenciaProtocolActivationStep;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ConvivenciaDashboardService
{
    public function __construct(private readonly ConvivenciaAccessService $accessService) {}

    public function build(User $user, array $filters = []): array
    {
        $caseQuery = $this->accessService->applyCaseVisibility(ConvivenciaCase::query(), $user);
        $complaintQuery = $this->accessService->applyComplaintVisibility(ConvivenciaComplaint::query(), $user);
        $derivationQuery = $this->accessService->applyDerivationVisibility(ConvivenciaDerivation::query(), $user);
        $measureQuery = $this->accessService->applyMeasureVisibility(ConvivenciaMeasure::query(), $user);
        $interviewQuery = $this->accessService->applyInterviewVisibility(ConvivenciaInterview::query(), $user);
        $dailyLogQuery = $this->accessService->applyDailyLogVisibility(ConvivenciaDailyLog::query(), $user);
        $activationQuery = $this->accessService->applyProtocolActivationVisibility(ConvivenciaProtocolActivation::query(), $user);

        $this->applyFilters($caseQuery, $filters, 'cases');
        $this->applyFilters($complaintQuery, $filters, 'complaints');
        $this->applyFilters($derivationQuery, $filters, 'derivations');
        $this->applyFilters($measureQuery, $filters, 'measures');
        $this->applyFilters($interviewQuery, $filters, 'interviews');
        $this->applyFilters($dailyLogQuery, $filters, 'daily_logs');
        $this->applyActivationFilters($activationQuery, $filters);

        $visibleActivationIds = (clone $activationQuery)->select('convivencia_protocol_activations.id');
        $runtimeStepQuery = ConvivenciaProtocolActivationStep::query()->whereIn('activation_id', clone $visibleActivationIds);
        $runtimePartQuery = ConvivenciaProtocolActivationPart::query()->whereIn('activation_id', clone $visibleActivationIds);

        $monthStart = now()->copy()->subMonths(11)->startOfMonth();
        $months = collect(CarbonPeriod::create($monthStart, '1 month', now()->startOfMonth()))
            ->map(fn ($month) => $month->format('Y-m'));
        $monthExpression = $this->monthExpression('convivencia_cases.opened_at');
        $casesByMonth = (clone $caseQuery)
            ->selectRaw("{$monthExpression} as label, COUNT(*) as total")
            ->where('convivencia_cases.opened_at', '>=', $monthStart)
            ->groupByRaw($monthExpression)
            ->pluck('total', 'label');

        $overdueSteps = (clone $runtimeStepQuery)->whereIn('status', ['in_progress', 'blocked'])
            ->whereNotNull('due_at')->where('due_at', '<', now())->count();
        $dueSoonSteps = (clone $runtimeStepQuery)->whereIn('status', ['in_progress', 'blocked'])
            ->whereBetween('due_at', [now(), now()->copy()->addHours(48)])->count();
        $overdueParts = (clone $runtimePartQuery)->whereIn('status', ['pending', 'in_progress'])
            ->whereNotNull('due_at')->where('due_at', '<', now())->count();
        $dueSoonParts = (clone $runtimePartQuery)->whereIn('status', ['pending', 'in_progress'])
            ->whereBetween('due_at', [now(), now()->copy()->addHours(48)])->count();

        $comparableSteps = (clone $runtimeStepQuery)->where('status', 'completed')
            ->whereNotNull('due_at')->whereNotNull('completed_at')->count();
        $onTimeSteps = (clone $runtimeStepQuery)->where('status', 'completed')
            ->whereNotNull('due_at')->whereNotNull('completed_at')
            ->whereColumn('completed_at', '<=', 'due_at')->count();
        $protocolCompliance = $comparableSteps > 0 ? round(($onTimeSteps / $comparableSteps) * 100, 2) : 0.0;

        $closureDurations = (clone $activationQuery)
            ->where('convivencia_protocol_activations.status', 'cerrado')
            ->whereNotNull('activated_at')->whereNotNull('closed_at')
            ->latest('closed_at')->limit(5000)->get(['activated_at', 'closed_at'])
            ->map(fn (ConvivenciaProtocolActivation $activation) => max(
                0,
                $activation->activated_at->diffInSeconds($activation->closed_at) / 3600
            ));

        $partsByCategory = (clone $runtimePartQuery)
            ->selectRaw('category as label, COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_total")
            ->groupBy('category')->orderByDesc('total')->get();
        $activationsByProtocol = (clone $activationQuery)
            ->leftJoin('convivencia_protocols', 'convivencia_protocols.id', '=', 'convivencia_protocol_activations.protocol_id')
            ->selectRaw("convivencia_protocol_activations.protocol_id, COALESCE(convivencia_protocols.name, 'Protocolo archivado') as label, COUNT(*) as total")
            ->groupBy('convivencia_protocol_activations.protocol_id', 'convivencia_protocols.name')
            ->orderByDesc('total')->limit(15)->get();
        $currentStages = (clone $activationQuery)
            ->leftJoin('convivencia_protocol_activation_steps as current_runtime_step', 'current_runtime_step.id', '=', 'convivencia_protocol_activations.current_activation_step_id')
            ->selectRaw("COALESCE(current_runtime_step.stage_name, convivencia_protocol_activations.current_stage_name, 'Sin etapa') as label, COUNT(*) as total")
            ->groupBy('current_runtime_step.stage_name', 'convivencia_protocol_activations.current_stage_name')
            ->orderByDesc('total')->limit(15)->get();
        $bottlenecks = (clone $runtimeStepQuery)
            ->selectRaw("COALESCE(stage_name, 'Sin etapa') as label, COUNT(*) as executions_total")
            ->selectRaw("SUM(CASE WHEN status IN ('in_progress', 'blocked') THEN 1 ELSE 0 END) as active_total")
            ->selectRaw("SUM(CASE WHEN status IN ('in_progress', 'blocked') AND due_at IS NOT NULL AND due_at < ? THEN 1 ELSE 0 END) as overdue_total", [now()])
            ->groupBy('stage_name')
            ->havingRaw("SUM(CASE WHEN status IN ('in_progress', 'blocked') THEN 1 ELSE 0 END) > 0")
            ->orderByDesc('overdue_total')->orderByDesc('active_total')->limit(10)->get()
            ->map(function ($item) {
                $item->total = (int) ($item->overdue_total ?: $item->active_total);

                return $item;
            });

        return [
            'metrics' => [
                'open_cases' => (clone $caseQuery)->whereNotIn('convivencia_cases.status', ['cerrado', 'archivado'])->count(),
                'closed_cases' => (clone $caseQuery)->where('convivencia_cases.status', 'cerrado')->count(),
                'internal_derivations_pending' => (clone $derivationQuery)->where('scope', 'internal')->whereIn('status', ['ingresada', 'recibida', 'en_revision', 'en_intervencion'])->count(),
                'external_derivations_pending' => (clone $derivationQuery)->where('scope', 'external')->whereIn('status', ['ingresada', 'recibida', 'en_revision', 'en_intervencion'])->count(),
                'pending_measures' => (clone $measureQuery)->whereIn('status', ['asignada', 'en_proceso', 'reprogramada'])->count(),
                'interviews_done' => (clone $interviewQuery)->count(),
                'complaints_received' => (clone $complaintQuery)->count(),
                'active_protocols' => (clone $activationQuery)->whereIn('convivencia_protocol_activations.status', ['activo', 'en_seguimiento', 'vencido'])->count(),
                'daily_events' => (clone $dailyLogQuery)->count(),
                'overdue_followups' => $this->overdueFollowups($caseQuery, $derivationQuery, $measureQuery),
                'overdue_protocol_steps' => $overdueSteps,
                'due_soon_protocol_steps' => $dueSoonSteps,
                'overdue_protocol_parts' => $overdueParts,
                'due_soon_protocol_parts' => $dueSoonParts,
                'protocol_compliance_percentage' => $protocolCompliance,
                'on_time_protocol_steps' => $onTimeSteps,
                'comparable_protocol_steps' => $comparableSteps,
                'average_protocol_closure_hours' => $closureDurations->isNotEmpty() ? round($closureDurations->average(), 2) : null,
                'median_protocol_closure_hours' => $this->median($closureDurations),
                'protocol_closure_sample_size' => $closureDurations->count(),
            ],
            'charts' => [
                'cases_by_classification' => (clone $caseQuery)
                    ->selectRaw("COALESCE(classification_label, 'Sin clasificación') as label, COUNT(*) as total")
                    ->groupBy('classification_label')->orderByDesc('total')->get(),
                'cases_by_course' => (clone $caseQuery)
                    ->leftJoin('course_sections', 'course_sections.id', '=', 'convivencia_cases.course_section_id')
                    ->selectRaw("COALESCE(course_sections.display_name, 'Sin curso') as label, COUNT(*) as total")
                    ->groupBy('course_sections.display_name')->orderByDesc('total')->get(),
                'cases_by_level' => (clone $caseQuery)
                    ->leftJoin('course_sections', 'course_sections.id', '=', 'convivencia_cases.course_section_id')
                    ->leftJoin('education_levels', 'education_levels.id', '=', 'course_sections.education_level_id')
                    ->selectRaw("COALESCE(education_levels.name, 'Sin nivel') as label, COUNT(*) as total")
                    ->groupBy('education_levels.name')->orderByDesc('total')->get(),
                'cases_by_status' => (clone $caseQuery)->selectRaw('convivencia_cases.status as label, COUNT(*) as total')
                    ->groupBy('convivencia_cases.status')->orderByDesc('total')->get(),
                'cases_by_criticality' => (clone $caseQuery)
                    ->selectRaw("COALESCE(criticality_label, 'Sin criticidad') as label, COUNT(*) as total")
                    ->groupBy('criticality_label')->orderByDesc('total')->get(),
                'monthly_trend' => [
                    'labels' => $months->values(),
                    'series' => $months->map(fn ($month) => (int) ($casesByMonth[$month] ?? 0))->values(),
                ],
                'activations_by_protocol' => $activationsByProtocol,
                'current_stage_distribution' => $currentStages,
                'parts_by_category' => $partsByCategory,
                'components_by_category' => $partsByCategory,
                'bottlenecks' => $bottlenecks,
                'protocol_steps_by_status' => (clone $runtimeStepQuery)->selectRaw('status as label, COUNT(*) as total')
                    ->groupBy('status')->orderByDesc('total')->get(),
                'protocol_parts_by_status' => (clone $runtimePartQuery)->selectRaw('status as label, COUNT(*) as total')
                    ->groupBy('status')->orderByDesc('total')->get(),
            ],
            'alerts' => [
                ['type' => 'overdue', 'title' => 'Etapas de protocolo vencidas', 'total' => $overdueSteps],
                ['type' => 'due_soon', 'title' => 'Etapas próximas a vencer', 'total' => $dueSoonSteps],
                ['type' => 'overdue', 'title' => 'Partes de protocolo vencidas', 'total' => $overdueParts],
            ],
            'recent' => [
                'cases' => (clone $caseQuery)->latest('opened_at')->limit(6)->get(['id', 'folio', 'opened_at', 'classification_label', 'criticality_label', 'status']),
                'complaints' => (clone $complaintQuery)->latest('received_at')->limit(6)->get(['id', 'folio', 'received_at', 'complainant_type', 'situation_type_label', 'status']),
                'protocols' => (clone $activationQuery)->with('protocol:id,name,deleted_at')->latest('activated_at')->limit(6)
                    ->get(['id', 'protocol_id', 'case_id', 'complaint_id', 'activated_at', 'status', 'current_stage_name', 'progress_percentage']),
            ],
            'filters' => $filters,
        ];
    }

    private function applyFilters(Builder $query, array $filters, string $type): void
    {
        $table = $query->getModel()->getTable();
        $yearId = $filters['academic_year_id'] ?? null;
        $courseSectionId = $filters['course_section_id'] ?? null;
        $educationLevelId = $filters['education_level_id'] ?? null;
        $status = $filters['status'] ?? null;

        $directAcademicYearTables = [
            'convivencia_cases',
            'convivencia_complaints',
            'convivencia_derivations',
            'convivencia_daily_logs',
        ];

        $query
            ->when($yearId && in_array($table, $directAcademicYearTables, true), fn ($builder) => $builder->where("{$table}.academic_year_id", $yearId))
            ->when($yearId && in_array($table, ['convivencia_measures', 'convivencia_interviews'], true), function (Builder $builder) use ($yearId) {
                $builder->where(function (Builder $context) use ($yearId) {
                    $context->whereHas('case', fn ($case) => $case->where('academic_year_id', $yearId))
                        ->orWhereHas('courseSection', fn ($course) => $course->where('academic_year_id', $yearId));
                });
            })
            ->when($courseSectionId, fn ($builder) => $builder->where("{$table}.course_section_id", $courseSectionId))
            ->when($status, fn ($builder) => $builder->where("{$table}.status", $status))
            ->when(($filters['criticality_label'] ?? null) && $table === 'convivencia_cases', fn ($builder) => $builder->where('convivencia_cases.criticality_label', $filters['criticality_label']))
            ->when(($filters['classification_label'] ?? null) && $table === 'convivencia_cases', fn ($builder) => $builder->where('convivencia_cases.classification_label', $filters['classification_label']))
            ->when($educationLevelId, fn ($builder) => $builder->whereHas('courseSection', fn ($sub) => $sub->where('education_level_id', $educationLevelId)));

        $dateColumn = match ($type) {
            'cases' => 'opened_at',
            'complaints' => 'received_at',
            'derivations' => 'derived_at',
            'measures' => 'assigned_at',
            'interviews' => 'interview_at',
            'daily_logs' => 'happened_at',
            default => null,
        };
        if ($dateColumn) {
            $query
                ->when($filters['semester'] ?? null, fn ($builder, $value) => $builder->whereMonth("{$table}.{$dateColumn}", (int) $value === 1 ? '<=' : '>=', (int) $value === 1 ? 6 : 7))
                ->when($filters['month'] ?? null, fn ($builder, $value) => $builder->whereMonth("{$table}.{$dateColumn}", (int) $value))
                ->when($filters['from'] ?? null, fn ($builder, $value) => $builder->whereDate("{$table}.{$dateColumn}", '>=', $value))
                ->when($filters['to'] ?? null, fn ($builder, $value) => $builder->whereDate("{$table}.{$dateColumn}", '<=', $value));
        }
    }

    private function applyActivationFilters(Builder $query, array $filters): void
    {
        $yearId = $filters['academic_year_id'] ?? null;
        $courseSectionId = $filters['course_section_id'] ?? null;
        $educationLevelId = $filters['education_level_id'] ?? null;
        $criticality = $filters['criticality_label'] ?? null;
        $classification = $filters['classification_label'] ?? null;

        $query
            ->when($filters['status'] ?? null, fn ($builder, $value) => $builder->where('convivencia_protocol_activations.status', $value))
            ->when($filters['semester'] ?? null, fn ($builder, $value) => $builder->whereMonth('convivencia_protocol_activations.activated_at', (int) $value === 1 ? '<=' : '>=', (int) $value === 1 ? 6 : 7))
            ->when($filters['month'] ?? null, fn ($builder, $value) => $builder->whereMonth('convivencia_protocol_activations.activated_at', (int) $value))
            ->when($filters['from'] ?? null, fn ($builder, $value) => $builder->whereDate('convivencia_protocol_activations.activated_at', '>=', $value))
            ->when($filters['to'] ?? null, fn ($builder, $value) => $builder->whereDate('convivencia_protocol_activations.activated_at', '<=', $value))
            ->when($yearId || $courseSectionId || $educationLevelId || $criticality || $classification, function (Builder $builder) use ($yearId, $courseSectionId, $educationLevelId, $criticality, $classification) {
                $builder->where(function (Builder $context) use ($yearId, $courseSectionId, $educationLevelId, $criticality, $classification) {
                    $context->whereHas('case', function (Builder $caseQuery) use ($yearId, $courseSectionId, $educationLevelId, $criticality, $classification) {
                        $caseQuery
                            ->when($yearId, fn ($query) => $query->where('academic_year_id', $yearId))
                            ->when($courseSectionId, fn ($query) => $query->where('course_section_id', $courseSectionId))
                            ->when($criticality, fn ($query) => $query->where('criticality_label', $criticality))
                            ->when($classification, fn ($query) => $query->where('classification_label', $classification))
                            ->when($educationLevelId, fn ($query) => $query->whereHas('courseSection', fn ($course) => $course->where('education_level_id', $educationLevelId)));
                    });
                    if (! $criticality && ! $classification) {
                        $context->orWhereHas('complaint', function (Builder $complaintQuery) use ($yearId, $courseSectionId, $educationLevelId) {
                            $complaintQuery
                                ->when($yearId, fn ($query) => $query->where('academic_year_id', $yearId))
                                ->when($courseSectionId, fn ($query) => $query->where('course_section_id', $courseSectionId))
                                ->when($educationLevelId, fn ($query) => $query->whereHas('courseSection', fn ($course) => $course->where('education_level_id', $educationLevelId)));
                        });
                    }
                });
            });
    }

    private function overdueFollowups(Builder $caseQuery, Builder $derivationQuery, Builder $measureQuery): int
    {
        return (clone $caseQuery)->whereNotIn('convivencia_cases.status', ['cerrado', 'archivado'])
            ->whereNotNull('follow_up_due_at')->where('follow_up_due_at', '<', now())->count()
            + (clone $derivationQuery)->whereIn('status', ['ingresada', 'recibida', 'en_revision', 'en_intervencion'])
                ->whereNotNull('response_due_at')->where('response_due_at', '<', now())->count()
            + (clone $measureQuery)->whereIn('status', ['asignada', 'en_proceso', 'reprogramada'])
                ->whereNotNull('due_at')->where('due_at', '<', now())->count();
    }

    private function monthExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            'pgsql' => "TO_CHAR({$column}, 'YYYY-MM')",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }

    private function median(Collection $values): ?float
    {
        if ($values->isEmpty()) {
            return null;
        }
        $sorted = $values->sort()->values();
        $middle = intdiv($sorted->count(), 2);
        $median = $sorted->count() % 2 ? $sorted[$middle] : ($sorted[$middle - 1] + $sorted[$middle]) / 2;

        return round($median, 2);
    }
}
