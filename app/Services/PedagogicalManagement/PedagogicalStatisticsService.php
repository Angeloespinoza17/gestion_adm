<?php

namespace App\Services\PedagogicalManagement;

use App\Models\LibroDigital\School;
use App\Models\PedagogicalManagement\PedagogicalInstrument;
use App\Models\User;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PedagogicalStatisticsService
{
    private const ATTENTION_STATUSES = ['partially_meets', 'does_not_meet', 'not_evidenced'];

    private const APPROVED_DECISIONS = ['approved', 'approved_with_observations'];

    public function __construct(private readonly PedagogicalInstrumentAccessService $access) {}

    /** @param array<string,mixed> $filters @return array<string,mixed> */
    public function dashboard(User $user, School $school, array $filters): array
    {
        $cacheVersion = (string) Cache::get('pedagogical-statistics:version', 'initial');
        $cacheKey = 'pedagogical-statistics:dashboard:'.hash('sha256', json_encode([
            $user->id, $school->id, $filters, $cacheVersion,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');

        return Cache::remember(
            $cacheKey,
            max(30, (int) config('pedagogical_management.statistics.cache_seconds', 300)),
            fn (): array => $this->buildDashboard($user, $school, $filters),
        );
    }

    /** @return array<string,mixed> */
    public function instrumentTrajectory(User $user, PedagogicalInstrument $instrument): array
    {
        abort_unless($this->access->canView($user, $instrument), 403);

        $snapshots = DB::table('pedagogical_report_snapshots as prs')
            ->where('prs.instrument_id', $instrument->id)
            ->orderBy('prs.file_version')
            ->orderBy('prs.reviewed_at')
            ->get();
        $snapshotIds = $snapshots->pluck('id')->all();
        $criteria = $snapshotIds === [] ? collect() : DB::table('pedagogical_report_criterion_snapshots')
            ->whereIn('snapshot_id', $snapshotIds)
            ->orderBy('code')
            ->get()
            ->groupBy('snapshot_id');
        $miscellaneous = $snapshotIds === [] ? collect() : DB::table('pedagogical_report_misc_snapshots')
            ->whereIn('snapshot_id', $snapshotIds)
            ->orderBy('position')
            ->get()
            ->groupBy('snapshot_id');

        $versions = $snapshots->map(function ($snapshot) use ($criteria, $miscellaneous): array {
            return [
                'snapshot_id' => (int) $snapshot->id,
                'file_version' => (int) $snapshot->file_version,
                'decision' => $snapshot->decision,
                'compliance_percentage' => (float) $snapshot->compliance_percentage,
                'evidence_coverage_percentage' => (float) $snapshot->evidence_coverage_percentage,
                'criteria_total' => (int) $snapshot->criteria_total,
                'applicable_count' => (int) $snapshot->applicable_count,
                'meets' => (int) $snapshot->meets_count,
                'partially_meets' => (int) $snapshot->partially_meets_count,
                'does_not_meet' => (int) $snapshot->does_not_meet_count,
                'not_evidenced' => (int) $snapshot->not_evidenced_count,
                'not_applicable' => (int) $snapshot->not_applicable_count,
                'miscellaneous_count' => (int) $snapshot->miscellaneous_count,
                'submitted_at' => $snapshot->submitted_at,
                'reviewed_at' => $snapshot->reviewed_at,
                'prompt_version' => $snapshot->prompt_version,
                'rubric_version' => $snapshot->rubric_version,
                'rubric_hash' => $snapshot->rubric_hash,
                'criteria' => collect($criteria->get($snapshot->id, []))->map(fn ($criterion): array => [
                    'code' => $criterion->code,
                    'dimension' => $criterion->dimension,
                    'criterion' => $criterion->criterion,
                    'status' => $criterion->status,
                ])->values()->all(),
                'miscellaneous' => collect($miscellaneous->get($snapshot->id, []))->map(fn ($finding): array => [
                    'category' => $finding->category,
                    'severity' => $finding->severity,
                    'title' => $finding->title,
                ])->values()->all(),
            ];
        })->values();

        $transitions = $this->versionTransitions($versions);
        $instrument->loadMissing(['owner:id,name', 'subject:id,name', 'courses:id,display_name,education_level_id']);

        return [
            'instrument' => [
                'id' => $instrument->uuid,
                'title' => $instrument->title,
                'teacher' => $instrument->owner?->name,
                'subject' => $instrument->subject?->name,
                'courses' => $instrument->courses->map(fn ($course): array => ['id' => $course->id, 'name' => $course->display_name])->values(),
            ],
            'summary' => [
                'reviewed_versions' => $versions->count(),
                'first_score' => $versions->first()['compliance_percentage'] ?? null,
                'latest_score' => $versions->last()['compliance_percentage'] ?? null,
                'improvement_pp' => $this->comparableDelta($snapshots),
                'rubric_compatible' => $snapshots->pluck('rubric_hash')->unique()->count() <= 1,
            ],
            'versions' => $versions,
            'transitions' => $transitions,
        ];
    }

    /** @param array<string,mixed> $filters @return array<string,mixed> */
    private function buildDashboard(User $user, School $school, array $filters): array
    {
        $rows = $this->snapshotQuery($user, $school, $filters)
            ->join('pedagogical_instruments as pi', 'pi.id', '=', 'prs.instrument_id')
            ->join('users as owners', 'owners.id', '=', 'prs.owner_user_id')
            ->join('schedule_subjects as subjects', 'subjects.id', '=', 'prs.subject_id')
            ->join('academic_years as years', 'years.id', '=', 'prs.academic_year_id')
            ->select([
                'prs.*', 'pi.uuid as instrument_uuid', 'pi.title as instrument_title',
                'owners.name as owner_name', 'subjects.name as subject_name',
                'years.year as academic_year',
            ])->orderBy('prs.reviewed_at')->get();

        $criteria = $this->criteriaRows($user, $school, $filters);
        $miscellaneous = $this->miscellaneousRows($user, $school, $filters);
        $courses = $this->courseMap($rows->pluck('instrument_id')->unique()->all());
        $instrumentGroups = $this->instrumentGroups($rows);
        $summary = $this->summary($rows, $instrumentGroups);
        $criteriaMetrics = $this->criteriaMetrics($criteria);
        $minimumReports = (int) config('pedagogical_management.statistics.minimum_reports', 5);
        $minimumPairs = (int) config('pedagogical_management.statistics.minimum_comparable_pairs', 3);

        return [
            'meta' => [
                'school' => ['id' => $school->id, 'name' => $school->name],
                'generated_at' => now()->toIso8601String(),
                'report_count' => $rows->count(),
                'comparable_pairs' => $summary['comparable_pairs'],
                'sample_sufficient' => $rows->count() >= $minimumReports && $summary['comparable_pairs'] >= $minimumPairs,
                'minimum_reports' => $minimumReports,
                'minimum_comparable_pairs' => $minimumPairs,
                'prompt_versions' => $rows->pluck('prompt_version')->filter()->unique()->values(),
                'rubric_versions' => $rows->pluck('rubric_version')->filter()->unique()->values(),
                'methodology' => [
                    'official_source' => 'Informe completado vinculado a la resolución de coordinación.',
                    'compliance_formula' => '(Cumple + 0,5 × Cumple parcialmente) / criterios aplicables.',
                    'comparison_rule' => 'Sólo compara versiones consecutivas del mismo instrumento con la misma pauta.',
                ],
            ],
            'filters' => $filters,
            'filter_options' => $this->filterOptions($user, $school),
            'summary' => $summary,
            'trend' => $this->trend($rows),
            'decisions' => $this->decisionDistribution($rows),
            'dimensions' => $this->dimensionEvolution($criteria, $instrumentGroups),
            'criteria' => $criteriaMetrics,
            'priorities' => collect($criteriaMetrics)->sortByDesc('opportunity_index')->take(8)->values(),
            'miscellaneous' => $this->miscellaneousMetrics($miscellaneous, max(1, $rows->count())),
            'teachers' => $this->teacherTrajectories($rows, $criteria),
            'instruments' => $this->instrumentTrajectories($instrumentGroups, $criteria, $courses),
        ];
    }

    /** @param array<string,mixed> $filters */
    private function snapshotQuery(User $user, School $school, array $filters): QueryBuilder
    {
        $visible = $this->access->visibleQuery($user)->select('pedagogical_instruments.id');
        $query = DB::table('pedagogical_report_snapshots as prs')
            ->where('prs.school_id', $school->id)
            ->whereIn('prs.instrument_id', $visible);

        return $this->applyFilters($query, $filters);
    }

    /** @param array<string,mixed> $filters */
    private function applyFilters(QueryBuilder $query, array $filters): QueryBuilder
    {
        $query
            ->when($filters['academic_year_id'] ?? null, fn (QueryBuilder $builder, $value) => $builder->where('prs.academic_year_id', (int) $value))
            ->when($filters['owner_user_id'] ?? null, fn (QueryBuilder $builder, $value) => $builder->where('prs.owner_user_id', (int) $value))
            ->when($filters['subject_id'] ?? null, fn (QueryBuilder $builder, $value) => $builder->where('prs.subject_id', (int) $value))
            ->when($filters['decision'] ?? null, fn (QueryBuilder $builder, $value) => $builder->where('prs.decision', $value))
            ->when($filters['prompt_version'] ?? null, fn (QueryBuilder $builder, $value) => $builder->where('prs.prompt_version', $value))
            ->when($filters['from_date'] ?? null, fn (QueryBuilder $builder, $value) => $builder->whereDate('prs.reviewed_at', '>=', $value))
            ->when($filters['to_date'] ?? null, fn (QueryBuilder $builder, $value) => $builder->whereDate('prs.reviewed_at', '<=', $value));

        $courseId = isset($filters['course_id']) ? (int) $filters['course_id'] : 0;
        $levelId = isset($filters['education_level_id']) ? (int) $filters['education_level_id'] : 0;
        if ($courseId || $levelId) {
            $query->whereExists(function (QueryBuilder $courses) use ($courseId, $levelId): void {
                $courses->selectRaw('1')
                    ->from('pedagogical_instrument_courses as pic')
                    ->join('course_sections as cs', 'cs.id', '=', 'pic.course_id')
                    ->whereColumn('pic.instrument_id', 'prs.instrument_id')
                    ->when($courseId, fn (QueryBuilder $builder) => $builder->where('pic.course_id', $courseId))
                    ->when($levelId, fn (QueryBuilder $builder) => $builder->where('cs.education_level_id', $levelId));
            });
        }

        return $query;
    }

    /** @param array<string,mixed> $filters */
    private function criteriaRows(User $user, School $school, array $filters): Collection
    {
        $query = DB::table('pedagogical_report_criterion_snapshots as criteria')
            ->join('pedagogical_report_snapshots as prs', 'prs.id', '=', 'criteria.snapshot_id')
            ->select([
                'criteria.*', 'prs.instrument_id', 'prs.owner_user_id', 'prs.file_version',
                'prs.reviewed_at', 'prs.rubric_version', 'prs.rubric_hash',
            ]);
        $visible = $this->access->visibleQuery($user)->select('pedagogical_instruments.id');
        $query->where('prs.school_id', $school->id)->whereIn('prs.instrument_id', $visible);

        return $this->applyFilters($query, $filters)->orderBy('prs.reviewed_at')->get();
    }

    /** @param array<string,mixed> $filters */
    private function miscellaneousRows(User $user, School $school, array $filters): Collection
    {
        $query = DB::table('pedagogical_report_misc_snapshots as findings')
            ->join('pedagogical_report_snapshots as prs', 'prs.id', '=', 'findings.snapshot_id')
            ->select(['findings.*', 'prs.instrument_id', 'prs.owner_user_id']);
        $visible = $this->access->visibleQuery($user)->select('pedagogical_instruments.id');
        $query->where('prs.school_id', $school->id)->whereIn('prs.instrument_id', $visible);

        return $this->applyFilters($query, $filters)->get();
    }

    private function instrumentGroups(Collection $rows): Collection
    {
        return $rows->groupBy('instrument_id')->map(fn (Collection $group) => $group
            ->sortBy(fn ($row): string => sprintf('%010d-%s', $row->file_version, $row->reviewed_at))
            ->values());
    }

    /** @return array<string,mixed> */
    private function summary(Collection $rows, Collection $instrumentGroups): array
    {
        $firstSnapshots = $instrumentGroups->map->first()->filter();
        $latestSnapshots = $instrumentGroups->map->last()->filter();
        $firstApproved = $firstSnapshots->whereIn('decision', self::APPROVED_DECISIONS)->count();
        $improvements = $instrumentGroups->map(fn (Collection $group) => $this->comparableDelta($group))->filter(fn ($value) => $value !== null)->values();
        $rectifications = $rows->where('decision', 'rectification_requested');
        $closedRectifications = 0;
        foreach ($rectifications as $rectification) {
            $closedRectifications += $instrumentGroups->get($rectification->instrument_id, collect())
                ->contains(fn ($candidate): bool => (int) $candidate->file_version > (int) $rectification->file_version
                    && in_array($candidate->decision, self::APPROVED_DECISIONS, true)) ? 1 : 0;
        }
        $rectificationDays = $this->rectificationDelays($instrumentGroups);
        $comparablePairs = $instrumentGroups->sum(fn (Collection $group): int => $this->countComparablePairs($group));

        return [
            'official_reports' => $rows->count(),
            'instruments' => $instrumentGroups->count(),
            'teachers' => $rows->pluck('owner_user_id')->unique()->count(),
            'first_pass_approval_rate' => $this->percentage($firstApproved, $firstSnapshots->count()),
            'current_compliance_percentage' => $this->median($latestSnapshots->pluck('compliance_percentage')),
            'overall_compliance_percentage' => $this->average($rows->pluck('compliance_percentage')),
            'evidence_coverage_percentage' => $this->average($rows->pluck('evidence_coverage_percentage')),
            'median_improvement_pp' => $this->median($improvements),
            'median_rectification_days' => $this->median($rectificationDays),
            'rectification_rate' => $this->percentage($rectifications->count(), $rows->count()),
            'rectification_closure_rate' => $this->percentage($closedRectifications, $rectifications->count()),
            'comparable_instruments' => $improvements->count(),
            'comparable_pairs' => $comparablePairs,
            'decisions' => [
                'approved' => $rows->where('decision', 'approved')->count(),
                'approved_with_observations' => $rows->where('decision', 'approved_with_observations')->count(),
                'rectification_requested' => $rectifications->count(),
            ],
        ];
    }

    private function trend(Collection $rows): array
    {
        return $rows->groupBy(fn ($row): string => Carbon::parse($row->reviewed_at)->timezone('America/Santiago')->format('Y-m'))
            ->map(function (Collection $group, string $period): array {
                $date = Carbon::createFromFormat('Y-m-d', $period.'-01', 'America/Santiago');

                return [
                    'period' => $period,
                    'label' => ucfirst($date->locale('es')->translatedFormat('M Y')),
                    'reports' => $group->count(),
                    'median_compliance' => $this->median($group->pluck('compliance_percentage')),
                    'average_compliance' => $this->average($group->pluck('compliance_percentage')),
                    'evidence_coverage' => $this->average($group->pluck('evidence_coverage_percentage')),
                ];
            })->sortKeys()->values()->all();
    }

    private function decisionDistribution(Collection $rows): array
    {
        $labels = [
            'approved' => 'Aprobados',
            'approved_with_observations' => 'Aprobados con observaciones',
            'rectification_requested' => 'Rectificación solicitada',
        ];

        return collect($labels)->map(fn (string $label, string $decision): array => [
            'decision' => $decision,
            'label' => $label,
            'count' => $rows->where('decision', $decision)->count(),
        ])->values()->all();
    }

    private function criteriaMetrics(Collection $criteria): array
    {
        $transitions = $this->criterionTransitionMetrics($criteria);
        $catalog = collect(config('pedagogical_management.review_criteria', []));
        $minimumReports = (int) config('pedagogical_management.statistics.minimum_reports', 5);
        $minimumPairs = (int) config('pedagogical_management.statistics.minimum_comparable_pairs', 3);

        return $catalog->map(function (array $definition) use ($criteria, $transitions, $minimumReports, $minimumPairs): array {
            $rows = $criteria->where('code', $definition['code']);
            $applicable = $rows->where('status', '!=', 'not_applicable');
            $evidenced = $applicable->where('status', '!=', 'not_evidenced');
            $affected = $applicable->whereIn('status', self::ATTENTION_STATUSES);
            $transition = $transitions[$definition['code']] ?? [
                'comparable_pairs' => 0, 'attention_pairs' => 0, 'persistent' => 0,
                'resolved' => 0, 'previous_meets_pairs' => 0, 'regressions' => 0,
            ];
            $deficit = $evidenced->count() > 0
                ? (($evidenced->where('status', 'does_not_meet')->count() + ($evidenced->where('status', 'partially_meets')->count() * .5)) / $evidenced->count()) * 100
                : 0;
            $attentionRate = $this->percentage($affected->count(), $applicable->count());
            $persistence = $this->percentage($transition['persistent'], $transition['attention_pairs']);
            $resolution = $this->percentage($transition['resolved'], $transition['attention_pairs']);
            $reach = $this->percentage($affected->pluck('owner_user_id')->unique()->count(), $applicable->pluck('owner_user_id')->unique()->count());
            $evidenceGap = $this->percentage($applicable->where('status', 'not_evidenced')->count(), $applicable->count());
            $opportunity = round(($deficit * .45) + (($persistence ?? 0) * .30) + (($reach ?? 0) * .20) + (($evidenceGap ?? 0) * .05), 1);

            return [
                'code' => $definition['code'],
                'dimension' => $definition['dimension'],
                'applicability' => $definition['applicability'],
                'criterion' => $definition['criterion'],
                'reports' => $rows->count(),
                'applicable' => $applicable->count(),
                'meets' => $rows->where('status', 'meets')->count(),
                'partially_meets' => $rows->where('status', 'partially_meets')->count(),
                'does_not_meet' => $rows->where('status', 'does_not_meet')->count(),
                'not_evidenced' => $rows->where('status', 'not_evidenced')->count(),
                'not_applicable' => $rows->where('status', 'not_applicable')->count(),
                'attention_rate' => $attentionRate,
                'quality_deficit_rate' => round($deficit, 1),
                'evidence_gap_rate' => $evidenceGap,
                'reach_rate' => $reach,
                'persistence_rate' => $persistence,
                'resolution_rate' => $resolution,
                'regression_rate' => $this->percentage($transition['regressions'], $transition['previous_meets_pairs']),
                'comparable_pairs' => $transition['comparable_pairs'],
                'affected_teachers' => $affected->pluck('owner_user_id')->unique()->count(),
                'opportunity_index' => $opportunity,
                'sample_sufficient' => $applicable->count() >= $minimumReports && $transition['comparable_pairs'] >= $minimumPairs,
            ];
        })->values()->all();
    }

    /** @return array<string,array<string,int>> */
    private function criterionTransitionMetrics(Collection $criteria): array
    {
        $metrics = [];
        $criteria->groupBy(fn ($row): string => $row->instrument_id.'|'.$row->code)
            ->each(function (Collection $rows) use (&$metrics): void {
                $ordered = $rows->sortBy(fn ($row): string => sprintf('%010d-%s', $row->file_version, $row->reviewed_at))->values();
                for ($index = 1; $index < $ordered->count(); $index++) {
                    $previous = $ordered[$index - 1];
                    $current = $ordered[$index];
                    if ($previous->rubric_hash !== $current->rubric_hash) {
                        continue;
                    }
                    $code = $current->code;
                    $metrics[$code] ??= [
                        'comparable_pairs' => 0, 'attention_pairs' => 0, 'persistent' => 0,
                        'resolved' => 0, 'previous_meets_pairs' => 0, 'regressions' => 0,
                    ];
                    $metrics[$code]['comparable_pairs']++;
                    if (in_array($previous->status, self::ATTENTION_STATUSES, true)) {
                        $metrics[$code]['attention_pairs']++;
                        if (in_array($current->status, self::ATTENTION_STATUSES, true)) {
                            $metrics[$code]['persistent']++;
                        }
                        if ($current->status === 'meets') {
                            $metrics[$code]['resolved']++;
                        }
                    }
                    if ($previous->status === 'meets') {
                        $metrics[$code]['previous_meets_pairs']++;
                        if (in_array($current->status, self::ATTENTION_STATUSES, true)) {
                            $metrics[$code]['regressions']++;
                        }
                    }
                }
            });

        return $metrics;
    }

    private function dimensionEvolution(Collection $criteria, Collection $instrumentGroups): array
    {
        $eligible = $instrumentGroups->filter(function (Collection $group): bool {
            $first = $group->first();
            $last = $group->last();

            return $first && $last && $first->rubric_hash === $last->rubric_hash;
        });
        $firstIds = $eligible->map->first()->pluck('id');
        $lastIds = $eligible->map->last()->pluck('id');
        $dimensions = collect(config('pedagogical_management.review_criteria', []))->pluck('dimension')->unique()->values();

        return $dimensions->map(function (string $dimension) use ($criteria, $firstIds, $lastIds, $eligible): array {
            $firstScore = $this->criterionScore($criteria->where('dimension', $dimension)->whereIn('snapshot_id', $firstIds));
            $latestScore = $this->criterionScore($criteria->where('dimension', $dimension)->whereIn('snapshot_id', $lastIds));

            return [
                'dimension' => $dimension,
                'first_score' => $firstScore,
                'latest_score' => $latestScore,
                'delta_pp' => $firstScore !== null && $latestScore !== null ? round($latestScore - $firstScore, 1) : null,
                'instruments' => $eligible->count(),
                'comparable_instruments' => $eligible->filter(fn (Collection $group): bool => $group->count() > 1)->count(),
            ];
        })->all();
    }

    private function teacherTrajectories(Collection $rows, Collection $criteria): array
    {
        return $rows->groupBy('owner_user_id')->map(function (Collection $teacherRows, $ownerId) use ($criteria): array {
            $groups = $this->instrumentGroups($teacherRows);
            $first = $groups->map->first()->filter();
            $latest = $groups->map->last()->filter();
            $improvements = $groups->map(fn (Collection $group) => $this->comparableDelta($group))->filter(fn ($value) => $value !== null);
            $teacherCriteria = $criteria->where('owner_user_id', $ownerId);

            return [
                'id' => (int) $ownerId,
                'name' => $teacherRows->first()->owner_name,
                'reports' => $teacherRows->count(),
                'instruments' => $groups->count(),
                'first_score' => $this->average($first->pluck('compliance_percentage')),
                'latest_score' => $this->average($latest->pluck('compliance_percentage')),
                'improvement_pp' => $this->median($improvements),
                'first_pass_approval_rate' => $this->percentage($first->whereIn('decision', self::APPROVED_DECISIONS)->count(), $first->count()),
                'median_rectification_days' => $this->median($this->rectificationDelays($groups)),
                'persistent_criteria' => $this->persistentCriterionCodes($teacherCriteria),
            ];
        })->sortBy(fn (array $teacher): array => [$teacher['latest_score'] ?? 101, $teacher['name']])->values()->take(100)->all();
    }

    private function instrumentTrajectories(Collection $instrumentGroups, Collection $criteria, Collection $courses): array
    {
        return $instrumentGroups->map(function (Collection $group) use ($criteria, $courses): array {
            $first = $group->first();
            $latest = $group->last();
            $instrumentCriteria = $criteria->where('instrument_id', $first->instrument_id);

            return [
                'id' => $first->instrument_uuid,
                'title' => $first->instrument_title,
                'teacher' => ['id' => (int) $first->owner_user_id, 'name' => $first->owner_name],
                'subject' => ['id' => (int) $first->subject_id, 'name' => $first->subject_name],
                'courses' => $courses->get($first->instrument_id, collect())->values(),
                'reviewed_versions' => $group->count(),
                'first_score' => (float) $first->compliance_percentage,
                'latest_score' => (float) $latest->compliance_percentage,
                'improvement_pp' => $this->comparableDelta($group),
                'latest_decision' => $latest->decision,
                'latest_reviewed_at' => $latest->reviewed_at,
                'rubric_compatible' => $group->pluck('rubric_hash')->unique()->count() <= 1,
                'persistent_criteria' => $this->persistentCriterionCodes($instrumentCriteria),
            ];
        })->sortByDesc('latest_reviewed_at')->values()->take(100)->all();
    }

    private function miscellaneousMetrics(Collection $rows, int $reportCount): array
    {
        $labels = [
            'arithmetic' => 'Puntajes y cálculos',
            'internal_consistency' => 'Consistencia interna',
            'wording' => 'Redacción',
            'presentation' => 'Presentación',
            'other' => 'Otros hallazgos',
        ];

        return collect($labels)->map(function (string $label, string $category) use ($rows, $reportCount): array {
            $categoryRows = $rows->where('category', $category);

            return [
                'category' => $category,
                'label' => $label,
                'findings' => $categoryRows->count(),
                'affected_reports' => $categoryRows->pluck('snapshot_id')->unique()->count(),
                'affected_teachers' => $categoryRows->pluck('owner_user_id')->unique()->count(),
                'incidence_rate' => $this->percentage($categoryRows->pluck('snapshot_id')->unique()->count(), $reportCount),
                'critical' => $categoryRows->where('severity', 'critical')->count(),
                'important' => $categoryRows->where('severity', 'important')->count(),
                'suggestion' => $categoryRows->where('severity', 'suggestion')->count(),
            ];
        })->values()->all();
    }

    private function filterOptions(User $user, School $school): array
    {
        $visibleIds = $this->access->visibleQuery($user)
            ->where('school_id', $school->id)
            ->select('pedagogical_instruments.id');
        $base = fn () => DB::table('pedagogical_instruments as pi')->where('pi.school_id', $school->id)->whereIn('pi.id', clone $visibleIds);

        $years = $base()->join('academic_years as ay', 'ay.id', '=', 'pi.academic_year_id')
            ->select(['ay.id', 'ay.name', 'ay.year', 'ay.is_active'])->distinct()->orderByDesc('ay.year')->get();
        $owners = $base()->join('users as users', 'users.id', '=', 'pi.owner_user_id')
            ->select(['users.id', 'users.name'])->distinct()->orderBy('users.name')->get();
        $subjects = $base()->join('schedule_subjects as subjects', 'subjects.id', '=', 'pi.subject_id')
            ->select(['subjects.id', 'subjects.name', 'subjects.color'])->distinct()->orderBy('subjects.name')->get();
        $courses = $base()->join('pedagogical_instrument_courses as pic', 'pic.instrument_id', '=', 'pi.id')
            ->join('course_sections as courses', 'courses.id', '=', 'pic.course_id')
            ->leftJoin('education_levels as levels', 'levels.id', '=', 'courses.education_level_id')
            ->select(['courses.id', 'courses.display_name as name', 'courses.education_level_id', 'levels.name as level_name'])
            ->distinct()->orderBy('courses.display_name')->get();

        return [
            'academic_years' => $years,
            'owners' => $owners,
            'subjects' => $subjects,
            'courses' => $courses,
            'levels' => $courses->filter(fn ($course) => $course->education_level_id)
                ->unique('education_level_id')->map(fn ($course): array => [
                    'id' => (int) $course->education_level_id,
                    'name' => $course->level_name,
                ])->values(),
        ];
    }

    private function courseMap(array $instrumentIds): Collection
    {
        if ($instrumentIds === []) {
            return collect();
        }

        return DB::table('pedagogical_instrument_courses as pic')
            ->join('course_sections as courses', 'courses.id', '=', 'pic.course_id')
            ->whereIn('pic.instrument_id', $instrumentIds)
            ->select(['pic.instrument_id', 'courses.id', 'courses.display_name as name'])
            ->orderBy('courses.display_name')->get()->groupBy('instrument_id')
            ->map(fn (Collection $items) => $items->map(fn ($item): array => ['id' => (int) $item->id, 'name' => $item->name]));
    }

    private function comparableDelta(Collection $group): ?float
    {
        if ($group->count() < 2) {
            return null;
        }
        $first = $group->first();
        $last = $group->last();
        if (! $first || ! $last || $first->rubric_hash !== $last->rubric_hash) {
            return null;
        }

        return round((float) $last->compliance_percentage - (float) $first->compliance_percentage, 1);
    }

    private function countComparablePairs(Collection $group): int
    {
        $ordered = $group->values();
        $count = 0;
        for ($index = 1; $index < $ordered->count(); $index++) {
            if ($ordered[$index - 1]->rubric_hash === $ordered[$index]->rubric_hash) {
                $count++;
            }
        }

        return $count;
    }

    private function rectificationDelays(Collection $instrumentGroups): Collection
    {
        $delays = collect();
        foreach ($instrumentGroups as $group) {
            $ordered = $group->values();
            for ($index = 0; $index < $ordered->count(); $index++) {
                if ($ordered[$index]->decision !== 'rectification_requested') {
                    continue;
                }
                $next = $ordered->slice($index + 1)->first(fn ($candidate): bool => (int) $candidate->file_version > (int) $ordered[$index]->file_version);
                if ($next?->submitted_at && $ordered[$index]->reviewed_at) {
                    $hours = Carbon::parse($ordered[$index]->reviewed_at)->diffInHours(Carbon::parse($next->submitted_at), false);
                    if ($hours >= 0) {
                        $delays->push(round($hours / 24, 1));
                    }
                }
            }
        }

        return $delays;
    }

    private function persistentCriterionCodes(Collection $criteria): array
    {
        $counts = collect();
        $criteria->groupBy(fn ($row): string => $row->instrument_id.'|'.$row->code)->each(function (Collection $rows) use ($counts): void {
            $ordered = $rows->sortBy('file_version')->values();
            for ($index = 1; $index < $ordered->count(); $index++) {
                if ($ordered[$index - 1]->rubric_hash === $ordered[$index]->rubric_hash
                    && in_array($ordered[$index - 1]->status, self::ATTENTION_STATUSES, true)
                    && in_array($ordered[$index]->status, self::ATTENTION_STATUSES, true)) {
                    $counts->put($ordered[$index]->code, (int) $counts->get($ordered[$index]->code, 0) + 1);
                }
            }
        });

        return $counts->sortDesc()->take(3)->keys()->values()->all();
    }

    private function criterionScore(Collection $criteria): ?float
    {
        $applicable = $criteria->where('status', '!=', 'not_applicable');
        if ($applicable->isEmpty()) {
            return null;
        }

        return round((($applicable->where('status', 'meets')->count() + ($applicable->where('status', 'partially_meets')->count() * .5)) / $applicable->count()) * 100, 1);
    }

    private function versionTransitions(Collection $versions): array
    {
        $transitions = [];
        for ($index = 1; $index < $versions->count(); $index++) {
            $previous = $versions[$index - 1];
            $current = $versions[$index];
            $compatible = $previous['rubric_hash'] === $current['rubric_hash'];
            $previousCriteria = collect($previous['criteria'])->keyBy('code');
            $currentCriteria = collect($current['criteria'])->keyBy('code');
            $resolved = [];
            $persistent = [];
            $regressed = [];
            $newAttention = [];
            if ($compatible) {
                foreach ($currentCriteria as $code => $criterion) {
                    $before = $previousCriteria->get($code);
                    if (! $before) {
                        continue;
                    }
                    $beforeAttention = in_array($before['status'], self::ATTENTION_STATUSES, true);
                    $nowAttention = in_array($criterion['status'], self::ATTENTION_STATUSES, true);
                    if ($beforeAttention && $criterion['status'] === 'meets') {
                        $resolved[] = $code;
                    } elseif ($beforeAttention && $nowAttention) {
                        $persistent[] = $code;
                    } elseif ($before['status'] === 'meets' && $nowAttention) {
                        $regressed[] = $code;
                    } elseif (! $beforeAttention && $nowAttention) {
                        $newAttention[] = $code;
                    }
                }
            }
            $transitions[] = [
                'from_version' => $previous['file_version'],
                'to_version' => $current['file_version'],
                'compatible' => $compatible,
                'score_delta_pp' => $compatible ? round($current['compliance_percentage'] - $previous['compliance_percentage'], 1) : null,
                'resolved' => $resolved,
                'persistent' => $persistent,
                'regressed' => $regressed,
                'new_attention' => $newAttention,
            ];
        }

        return $transitions;
    }

    private function percentage(int|float $numerator, int|float $denominator): ?float
    {
        return $denominator > 0 ? round(($numerator / $denominator) * 100, 1) : null;
    }

    private function average(Collection $values): ?float
    {
        $numeric = $values->filter(fn ($value) => is_numeric($value))->map(fn ($value) => (float) $value);

        return $numeric->isEmpty() ? null : round($numeric->average(), 1);
    }

    private function median(Collection $values): ?float
    {
        $numeric = $values->filter(fn ($value) => is_numeric($value))->map(fn ($value) => (float) $value)->sort()->values();
        if ($numeric->isEmpty()) {
            return null;
        }
        $middle = intdiv($numeric->count(), 2);
        $median = $numeric->count() % 2
            ? $numeric[$middle]
            : ($numeric[$middle - 1] + $numeric[$middle]) / 2;

        return round($median, 1);
    }
}
