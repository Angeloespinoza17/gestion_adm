<?php

namespace App\Services\Convivencia;

use App\Models\Convivencia\ConvivenciaCase;
use App\Models\Convivencia\ConvivenciaComplaint;
use App\Models\Convivencia\ConvivenciaDailyLog;
use App\Models\Convivencia\ConvivenciaDerivation;
use App\Models\Convivencia\ConvivenciaIdpsResult;
use App\Models\Convivencia\ConvivenciaInterview;
use App\Models\Convivencia\ConvivenciaMeasure;
use App\Models\Convivencia\ConvivenciaSociogram;
use App\Models\CourseSection;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ConvivenciaReportService
{
    private const PREVIEW_LIMIT = 50;

    public const EXPORT_DATASETS = [
        'cases',
        'complaints',
        'daily_logs',
        'derivations',
        'interviews',
        'measures',
    ];

    /** @var array<string, array{date_column: string, columns: array<int, string>}> */
    private const LIST_CONFIG = [
        'cases' => [
            'date_column' => 'opened_at',
            'columns' => ['id', 'folio', 'opened_at', 'classification_label', 'criticality_label', 'status'],
        ],
        'complaints' => [
            'date_column' => 'received_at',
            'columns' => ['id', 'folio', 'received_at', 'situation_type_label', 'complainant_type', 'status'],
        ],
        'daily_logs' => [
            'date_column' => 'happened_at',
            'columns' => ['id', 'happened_at', 'daily_log_type_label', 'description', 'status'],
        ],
        'derivations' => [
            'date_column' => 'derived_at',
            'columns' => ['id', 'scope', 'derived_at', 'destination_label', 'status', 'priority_level'],
        ],
        'interviews' => [
            'date_column' => 'interview_at',
            'columns' => ['id', 'interview_at', 'interview_type_label', 'motive', 'follow_up_status'],
        ],
        'measures' => [
            'date_column' => 'assigned_at',
            'columns' => ['id', 'assigned_at', 'measure_type_label', 'status', 'due_at'],
        ],
    ];

    public function __construct(
        private readonly ConvivenciaAccessService $accessService,
    ) {}

    public function buildCourseReport(User $user, array $filters): array
    {
        $courseSectionId = $filters['course_section_id'] ?? null;
        $queries = $this->courseReportQueries($user, $filters);
        $caseQuery = $queries['cases'];
        $complaintQuery = $queries['complaints'];
        $dailyLogQuery = $queries['daily_logs'];
        $derivationQuery = $queries['derivations'];
        $interviewQuery = $queries['interviews'];
        $measureQuery = $queries['measures'];
        $sociogramQuery = $queries['sociograms'];
        $idpsQuery = $queries['idps'];

        $totals = [];
        foreach (self::EXPORT_DATASETS as $dataset) {
            $totals[$dataset] = (clone $queries[$dataset])->count();
        }

        $openCases = (clone $caseQuery)->whereNotIn('status', ['cerrado', 'archivado'])->count();
        $closedCases = (clone $caseQuery)->where('status', 'cerrado')->count();
        $completedMeasures = (clone $measureQuery)->whereIn('status', ['cumplida', 'cerrada'])->count();
        $pendingDerivations = (clone $derivationQuery)
            ->whereIn('status', ['ingresada', 'recibida', 'en_revision', 'en_intervencion'])
            ->count();
        $pendingInterviewFollowUps = (clone $interviewQuery)
            ->whereIn('follow_up_status', ['pendiente', 'reprogramado'])
            ->count();
        $convertedComplaints = (clone $complaintQuery)->whereNotNull('case_id')->count();
        $overdueMeasures = (clone $measureQuery)
            ->whereIn('status', ['asignada', 'en_proceso', 'reprogramada'])
            ->where('due_at', '<', now())
            ->count();
        $caseClosureDays = (clone $caseQuery)
            ->where('status', 'cerrado')
            ->whereNotNull('opened_at')
            ->whereNotNull('closed_at')
            ->latest('closed_at')
            ->limit(5000)
            ->get(['opened_at', 'closed_at'])
            ->map(fn (ConvivenciaCase $case) => max(0, $case->opened_at->diffInSeconds($case->closed_at) / 86400));

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
                'conflicts_registered' => $totals['daily_logs'],
                'open_cases' => $openCases,
                'closed_cases' => $closedCases,
                'total_cases' => $totals['cases'],
                'case_resolution_rate' => $totals['cases'] > 0 ? round(($closedCases / $totals['cases']) * 100, 1) : 0.0,
                'average_case_closure_days' => $caseClosureDays->isNotEmpty() ? round($caseClosureDays->average(), 1) : null,
                'tardiness' => (clone $dailyLogQuery)
                    ->where(function (Builder $query) {
                        $query
                            ->where('daily_log_type_label', 'like', '%Atraso%')
                            ->orWhereHas('type', fn ($sub) => $sub->where('code', 'atraso'));
                    })
                    ->count(),
                'derivations' => $totals['derivations'],
                'pending_derivations' => $pendingDerivations,
                'complaints' => $totals['complaints'],
                'complaint_conversion_rate' => $totals['complaints'] > 0 ? round(($convertedComplaints / $totals['complaints']) * 100, 1) : 0.0,
                'interviews' => $totals['interviews'],
                'pending_interview_follow_ups' => $pendingInterviewFollowUps,
                'measures' => $totals['measures'],
                'completed_measures' => $completedMeasures,
                'measure_completion_rate' => $totals['measures'] > 0 ? round(($completedMeasures / $totals['measures']) * 100, 1) : 0.0,
                'attendance_note' => 'Sin integración directa con asistencia en la arquitectura actual.',
                'alerts' => [
                    'overdue_measures' => $overdueMeasures,
                    'open_cases' => $openCases,
                ],
            ],
            'sociogram' => $latestSociogram ? [
                'title' => $latestSociogram->title,
                'applied_on' => $latestSociogram->applied_on,
                'summary' => $latestSociogram->result_summary,
                'interpretation' => $latestSociogram->interpretation,
            ] : null,
            'analytics' => $this->buildAnalytics($queries, $totals, $filters),
            ...$this->buildPreviewLists($queries, $totals),
        ];
    }

    public function paginateCourseReportExport(
        User $user,
        array $filters,
        string $dataset,
        int $perPage = 200,
    ): LengthAwarePaginator {
        if (! in_array($dataset, self::EXPORT_DATASETS, true)) {
            throw new \InvalidArgumentException('El conjunto solicitado no forma parte del reporte de convivencia.');
        }

        $query = $this->courseReportQueries($user, $filters)[$dataset];
        $config = self::LIST_CONFIG[$dataset];

        return $query
            ->orderByDesc($config['date_column'])
            ->orderByDesc('id')
            ->paginate($perPage, $config['columns']);
    }

    /**
     * @param  array<string, Builder>  $queries
     * @param  array<string, int>  $totals
     * @return array{lists: array<string, mixed>, list_meta: array<string, array{shown: int, total: int, truncated: bool, limit: int}>}
     */
    private function buildPreviewLists(array $queries, array $totals): array
    {
        $lists = [];
        $meta = [];

        foreach (self::EXPORT_DATASETS as $dataset) {
            $config = self::LIST_CONFIG[$dataset];
            $lists[$dataset] = (clone $queries[$dataset])
                ->orderByDesc($config['date_column'])
                ->orderByDesc('id')
                ->limit(self::PREVIEW_LIMIT)
                ->get($config['columns']);
            $shown = $lists[$dataset]->count();
            $meta[$dataset] = [
                'shown' => $shown,
                'total' => $totals[$dataset],
                'truncated' => $totals[$dataset] > $shown,
                'limit' => self::PREVIEW_LIMIT,
            ];
        }

        return ['lists' => $lists, 'list_meta' => $meta];
    }

    /** @return array<string, Builder> */
    private function courseReportQueries(User $user, array $filters): array
    {
        $queries = [
            'cases' => $this->accessService->applyCaseVisibility(ConvivenciaCase::query(), $user),
            'complaints' => $this->accessService->applyComplaintVisibility(ConvivenciaComplaint::query(), $user),
            'daily_logs' => $this->accessService->applyDailyLogVisibility(ConvivenciaDailyLog::query(), $user),
            'derivations' => $this->accessService->applyDerivationVisibility(ConvivenciaDerivation::query(), $user),
            'interviews' => $this->accessService->applyInterviewVisibility(ConvivenciaInterview::query(), $user),
            'measures' => $this->accessService->applyMeasureVisibility(ConvivenciaMeasure::query(), $user),
            'sociograms' => $this->accessService->applySociogramVisibility(ConvivenciaSociogram::query(), $user),
            'idps' => $this->accessService->applyIdpsResultVisibility(ConvivenciaIdpsResult::query(), $user),
        ];

        foreach (self::LIST_CONFIG as $dataset => $config) {
            $this->applyCourseFilters($queries[$dataset], $filters, $config['date_column']);
        }
        $this->applyCourseFilters($queries['sociograms'], $filters, 'applied_on');
        $this->applyCourseFilters($queries['idps'], $filters, null);

        return $queries;
    }

    private function applyCourseFilters(Builder $query, array $filters, ?string $dateColumn): void
    {
        $table = $query->getModel()->getTable();
        $academicYearId = $filters['academic_year_id'] ?? null;
        $directAcademicYearTables = [
            'convivencia_cases',
            'convivencia_complaints',
            'convivencia_daily_logs',
            'convivencia_derivations',
            'convivencia_sociograms',
            'convivencia_idps_results',
        ];

        $query
            ->when($academicYearId && in_array($table, $directAcademicYearTables, true), fn ($builder) => $builder->where("{$table}.academic_year_id", $academicYearId))
            ->when($academicYearId && in_array($table, ['convivencia_measures', 'convivencia_interviews'], true), function (Builder $builder) use ($academicYearId) {
                $builder->where(function (Builder $context) use ($academicYearId) {
                    $context->whereHas('case', fn ($case) => $case->where('academic_year_id', $academicYearId))
                        ->orWhereHas('courseSection', fn ($course) => $course->where('academic_year_id', $academicYearId));
                });
            })
            ->when($filters['course_section_id'] ?? null, fn ($builder, $value) => $builder->where("{$table}.course_section_id", $value))
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

    /** @param array<string, Builder> $queries */
    private function buildAnalytics(array $queries, array $totals, array $filters): array
    {
        $caseFacets = $this->facetCounts($queries['cases'], [
            'status' => 'Sin estado',
            'classification_label' => 'Sin clasificación',
            'subclassification_label' => 'Sin subclasificación',
            'criticality_label' => 'Sin criticidad',
            'origin' => 'Sin origen',
        ]);
        $complaintFacets = $this->facetCounts($queries['complaints'], [
            'status' => 'Sin estado',
            'situation_type_label' => 'Sin tipo',
            'complainant_type' => 'Sin denunciante',
        ]);
        $derivationFacets = $this->facetCounts($queries['derivations'], [
            'status' => 'Sin estado',
            'scope' => 'Sin ámbito',
            'priority_level' => 'Sin prioridad',
        ]);
        $interviewFacets = $this->facetCounts($queries['interviews'], [
            'interview_type_label' => 'Sin tipo',
            'follow_up_status' => 'Sin seguimiento',
        ]);
        $measureFacets = $this->facetCounts($queries['measures'], [
            'measure_type_label' => 'Sin tipo',
            'status' => 'Sin estado',
        ]);
        $dailyLogFacets = $this->facetCounts($queries['daily_logs'], [
            'daily_log_type_label' => 'Sin tipo',
            'status' => 'Sin estado',
        ]);

        return [
            'activity_by_type' => collect([
                'Casos' => $totals['cases'],
                'Denuncias' => $totals['complaints'],
                'Bitácora' => $totals['daily_logs'],
                'Derivaciones' => $totals['derivations'],
                'Entrevistas' => $totals['interviews'],
                'Medidas' => $totals['measures'],
            ])->map(fn ($total, $label) => ['label' => $label, 'total' => $total])->values(),
            'cases_by_status' => $caseFacets['status'],
            'cases_by_classification' => $caseFacets['classification_label'],
            'cases_by_subclassification' => $caseFacets['subclassification_label'],
            'cases_by_criticality' => $caseFacets['criticality_label'],
            'cases_by_origin' => $caseFacets['origin'],
            'complaints_by_status' => $complaintFacets['status'],
            'complaints_by_type' => $complaintFacets['situation_type_label'],
            'complaints_by_complainant' => $complaintFacets['complainant_type'],
            'derivations_by_scope' => $derivationFacets['scope'],
            'derivations_by_status' => $derivationFacets['status'],
            'derivations_by_priority' => $derivationFacets['priority_level'],
            'measures_by_status' => $measureFacets['status'],
            'measures_by_type' => $measureFacets['measure_type_label'],
            'interviews_by_follow_up' => $interviewFacets['follow_up_status'],
            'interviews_by_type' => $interviewFacets['interview_type_label'],
            'daily_logs_by_type' => $dailyLogFacets['daily_log_type_label'],
            'daily_logs_by_status' => $dailyLogFacets['status'],
            'monthly_activity' => $this->monthlyActivity($queries),
            'courses' => $this->courseStatistics($queries, $filters),
        ];
    }

    /**
     * Obtiene varias distribuciones de una entidad con una sola consulta UNION ALL.
     * Las columnas provienen únicamente de la lista interna del servicio.
     *
     * @param  array<string, string>  $facets
     * @return array<string, Collection<int, array{label: string, total: int}>>
     */
    private function facetCounts(Builder $query, array $facets): array
    {
        $table = $query->getModel()->getTable();
        $facetQueries = collect($facets)->map(function (string $fallback, string $column) use ($query, $table) {
            return (clone $query)
                ->selectRaw('? as facet, COALESCE('.$table.'.'.$column.', ?) as label, COUNT(*) as total', [$column, $fallback])
                ->groupBy($table.'.'.$column);
        })->values();

        $union = $facetQueries->shift();
        foreach ($facetQueries as $facetQuery) {
            $union->unionAll($facetQuery);
        }

        $rows = DB::query()
            ->fromSub($union, 'convivencia_facet_counts')
            ->orderBy('facet')
            ->orderByDesc('total')
            ->get(['facet', 'label', 'total']);

        return collect($facets)->mapWithKeys(fn (string $fallback, string $column) => [
            $column => $rows
                ->where('facet', $column)
                ->map(fn ($row) => ['label' => (string) $row->label, 'total' => (int) $row->total])
                ->values(),
        ])->all();
    }

    /** @param array<string, Builder> $queries */
    private function monthlyActivity(array $queries): array
    {
        $datasets = [
            'cases' => ['label' => 'Casos', 'date' => 'opened_at'],
            'complaints' => ['label' => 'Denuncias', 'date' => 'received_at'],
            'daily_logs' => ['label' => 'Bitácora', 'date' => 'happened_at'],
            'derivations' => ['label' => 'Derivaciones', 'date' => 'derived_at'],
        ];
        $values = [];
        $months = collect();

        foreach ($datasets as $dataset => $config) {
            $table = $queries[$dataset]->getModel()->getTable();
            $expression = $this->monthExpression("{$table}.{$config['date']}");
            $counts = (clone $queries[$dataset])
                ->selectRaw("{$expression} as month_key, COUNT(*) as total")
                ->whereNotNull("{$table}.{$config['date']}")
                ->groupByRaw($expression)
                ->orderBy('month_key')
                ->pluck('total', 'month_key');
            $values[$dataset] = $counts;
            $months = $months->merge($counts->keys());
        }

        $months = $months->filter()->unique()->sort()->values()->take(-18)->values();

        return [
            'labels' => $months,
            'series' => collect($datasets)->map(fn ($config, $dataset) => [
                'name' => $config['label'],
                'data' => $months->map(fn ($month) => (int) ($values[$dataset][$month] ?? 0))->values(),
            ])->values(),
        ];
    }

    /** @param array<string, Builder> $queries */
    private function courseStatistics(array $queries, array $filters): Collection
    {
        $caseTable = $queries['cases']->getModel()->getTable();
        $measureTable = $queries['measures']->getModel()->getTable();
        $caseRows = (clone $queries['cases'])
            ->whereNotNull("{$caseTable}.course_section_id")
            ->selectRaw("{$caseTable}.course_section_id as course_id, COUNT(*) as total_cases")
            ->selectRaw("SUM(CASE WHEN {$caseTable}.status NOT IN ('cerrado', 'archivado') THEN 1 ELSE 0 END) as open_cases")
            ->selectRaw("SUM(CASE WHEN {$caseTable}.status = 'cerrado' THEN 1 ELSE 0 END) as closed_cases")
            ->groupBy("{$caseTable}.course_section_id")
            ->get()
            ->keyBy('course_id');
        $measureRows = (clone $queries['measures'])
            ->whereNotNull("{$measureTable}.course_section_id")
            ->selectRaw("{$measureTable}.course_section_id as course_id, COUNT(*) as measures")
            ->selectRaw("SUM(CASE WHEN {$measureTable}.status IN ('cumplida', 'cerrada') THEN 1 ELSE 0 END) as completed_measures")
            ->selectRaw("SUM(CASE WHEN {$measureTable}.status IN ('asignada', 'en_proceso', 'reprogramada') AND {$measureTable}.due_at IS NOT NULL AND {$measureTable}.due_at < ? THEN 1 ELSE 0 END) as overdue_measures", [now()])
            ->groupBy("{$measureTable}.course_section_id")
            ->get()
            ->keyBy('course_id');

        $simpleDatasets = ['complaints', 'daily_logs', 'derivations', 'interviews'];
        $simpleCounts = collect($simpleDatasets)->mapWithKeys(function (string $dataset) use ($queries) {
            $table = $queries[$dataset]->getModel()->getTable();
            $counts = (clone $queries[$dataset])
                ->whereNotNull("{$table}.course_section_id")
                ->selectRaw("{$table}.course_section_id as course_id, COUNT(*) as total")
                ->groupBy("{$table}.course_section_id")
                ->pluck('total', 'course_id');

            return [$dataset => $counts];
        });

        $courseIds = collect([$caseRows->keys(), $measureRows->keys(), ...$simpleCounts->values()])
            ->flatten()
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique();

        $courses = CourseSection::query()
            ->with(['educationLevel:id,name', 'academicYear:id,name,year'])
            ->when($filters['academic_year_id'] ?? null, fn ($query, $value) => $query->where('academic_year_id', $value))
            ->when($filters['education_level_id'] ?? null, fn ($query, $value) => $query->where('education_level_id', $value))
            ->when($filters['course_section_id'] ?? null, fn ($query, $value) => $query->whereKey($value))
            ->when(! ($filters['academic_year_id'] ?? null) && ! ($filters['course_section_id'] ?? null), fn ($query) => $query->whereIn('id', $courseIds))
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'education_level_id', 'academic_year_id']);

        return $courses->map(function (CourseSection $course) use ($caseRows, $measureRows, $simpleCounts) {
            $cases = $caseRows->get($course->id);
            $measures = $measureRows->get($course->id);
            $totalCases = (int) ($cases?->total_cases ?? 0);
            $closedCases = (int) ($cases?->closed_cases ?? 0);
            $measureTotal = (int) ($measures?->measures ?? 0);
            $completedMeasures = (int) ($measures?->completed_measures ?? 0);
            $complaints = (int) ($simpleCounts['complaints'][$course->id] ?? 0);
            $dailyEvents = (int) ($simpleCounts['daily_logs'][$course->id] ?? 0);
            $derivations = (int) ($simpleCounts['derivations'][$course->id] ?? 0);
            $interviews = (int) ($simpleCounts['interviews'][$course->id] ?? 0);

            return [
                'course_id' => $course->id,
                'course' => $course->display_name,
                'education_level' => $course->educationLevel?->name,
                'academic_year' => $course->academicYear?->year ?? $course->academicYear?->name,
                'total_cases' => $totalCases,
                'open_cases' => (int) ($cases?->open_cases ?? 0),
                'closed_cases' => $closedCases,
                'resolution_rate' => $totalCases > 0 ? round(($closedCases / $totalCases) * 100, 1) : 0.0,
                'complaints' => $complaints,
                'daily_events' => $dailyEvents,
                'derivations' => $derivations,
                'interviews' => $interviews,
                'measures' => $measureTotal,
                'completed_measures' => $completedMeasures,
                'measure_completion_rate' => $measureTotal > 0 ? round(($completedMeasures / $measureTotal) * 100, 1) : 0.0,
                'overdue_measures' => (int) ($measures?->overdue_measures ?? 0),
                'activity_total' => $totalCases + $complaints + $dailyEvents + $derivations + $interviews + $measureTotal,
            ];
        })->sortByDesc('activity_total')->values();
    }

    private function monthExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            'pgsql' => "TO_CHAR({$column}, 'YYYY-MM')",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }
}
