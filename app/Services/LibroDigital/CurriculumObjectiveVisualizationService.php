<?php

namespace App\Services\LibroDigital;

use App\Http\Requests\LibroDigital\CurriculumObjectiveVisualizationRequest;
use App\Models\LibroDigital\Book;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CurriculumObjectiveVisualizationService
{
    private const FILTER_KEYS = [
        'school_id', 'academic_year_id', 'book_id', 'course_section_id', 'course_label',
        'level_code', 'grade_code', 'curriculum_track', 'schedule_subject_id',
        'subject_code', 'subject', 'catalog_id', 'objective_type', 'axis_code',
        'status', 'source', 'query', 'scope', 'view', 'hierarchy', 'max_depth',
        'include_leaves', 'root_node',
    ];

    public function __construct(
        private readonly CurriculumHierarchyBuilder $hierarchies,
        private readonly CurriculumGraphBuilder $graphs,
        private readonly CanonicalJson $canonical,
    ) {}

    /** @param array<string, mixed> $context @return array<string, mixed> */
    public function build(
        Builder $filteredQuery,
        CurriculumObjectiveVisualizationRequest $request,
        array $context,
        ?Book $book = null,
    ): array {
        $ttl = max(0, min(3600, (int) config('libro_digital.curriculum_visualizations.cache_ttl_seconds', 600)));
        $cacheKey = $this->cacheKey($request, $context, $book);
        $resolver = fn (): array => $this->buildUncached($filteredQuery, $request);

        return $ttl > 0 ? Cache::remember($cacheKey, $ttl, $resolver) : $resolver();
    }

    /** @param array<string, mixed> $context @return array<string, mixed> */
    public function empty(CurriculumObjectiveVisualizationRequest $request, array $context): array
    {
        $scope = $request->string('scope', 'filtered')->toString() ?: 'filtered';
        $view = $request->string('view', 'treemap')->toString() ?: 'treemap';
        $hierarchy = $this->effectiveHierarchy($request);
        $root = $this->hierarchies->build([], $scope);
        $requestedRoot = $request->string('root_node')->toString();
        if ($requestedRoot !== '' && $requestedRoot !== $root['id']) {
            throw ValidationException::withMessages([
                'root_node' => 'El nodo solicitado no pertenece al resultado curricular actual.',
            ]);
        }
        $graph = $this->graphs->build($root, $this->graphNodeLimit($view));

        return [
            'meta' => [
                'scope' => $scope,
                'view' => $view,
                'total_objectives' => 0,
                'filtered_total_objectives' => 0,
                'total_subjects' => 0,
                'total_curricular_groups' => 0,
                'available_objectives' => 0,
                'unavailable_objectives' => 0,
                'canonical_source_conflicts' => 0,
                'hierarchy' => $hierarchy,
                'max_depth' => count($hierarchy),
                'leaves_included' => false,
                'aggregated' => false,
                'leaf_threshold' => $this->leafThreshold(),
                'root_node' => $root['id'],
                'root_path' => [],
                'graph_node_limit' => $graph['node_limit'],
                'graph_total_nodes' => $graph['total_nodes'],
                'graph_truncated' => $graph['truncated'],
                'catalog_status' => $context['catalog_status'] ?? null,
            ],
            'root' => $root,
            'graph' => $graph,
        ];
    }

    /** @return array<string, mixed> */
    private function buildUncached(
        Builder $filteredQuery,
        CurriculumObjectiveVisualizationRequest $request,
    ): array {
        $scope = $request->string('scope', 'filtered')->toString() ?: 'filtered';
        $view = $request->string('view', 'treemap')->toString() ?: 'treemap';
        $hierarchy = $this->effectiveHierarchy($request);
        $buildHierarchy = $view === 'radial_tree'
            ? $request->hierarchyDimensions()
            : $hierarchy;
        $categoryDimensions = array_values(array_filter(
            $buildHierarchy,
            fn (string $dimension): bool => $dimension !== 'objective',
        ));
        $objectiveRequested = end($buildHierarchy) === 'objective';
        $includeLeavesRequested = $request->boolean('include_leaves', true);
        $leafThreshold = $this->leafThreshold();
        $baseQuery = $this->withoutPresentation($filteredQuery);
        $baseSummary = $this->summary($baseQuery);
        $leavesIncluded = $objectiveRequested
            && $includeLeavesRequested
            && $baseSummary['total_objectives'] <= $leafThreshold;

        $rows = $leavesIncluded
            ? $this->leafRows($baseQuery, $categoryDimensions, $leafThreshold)
            : $this->aggregateRows($baseQuery, $categoryDimensions, $baseSummary);
        if ($leavesIncluded && $rows->count() > $leafThreshold) {
            $leavesIncluded = false;
            $rows = $this->aggregateRows($baseQuery, $categoryDimensions, $baseSummary);
        }
        $tree = $this->hierarchies->build($rows, $scope);
        $currentQuery = $baseQuery;
        $currentSummary = $baseSummary;

        $requestedRoot = $request->string('root_node')->toString();
        if ($requestedRoot !== '') {
            $selected = $this->hierarchies->find($tree, $requestedRoot);
            if ($selected === null) {
                throw ValidationException::withMessages([
                    'root_node' => 'El nodo solicitado no pertenece al resultado curricular actual.',
                ]);
            }

            $currentQuery = $this->applyNodeFilters(
                $baseQuery,
                (array) data_get($selected, 'metadata.filters', []),
            );
            $currentSummary = $this->summary($currentQuery);
            $canExpandLeaves = $objectiveRequested
                && $includeLeavesRequested
                && $currentSummary['total_objectives'] <= $leafThreshold;

            if ($canExpandLeaves && ! $this->hierarchies->containsType($selected, 'objective')) {
                $expandedRows = $this->leafRows($currentQuery, $categoryDimensions, $leafThreshold);
                if ($expandedRows->count() <= $leafThreshold) {
                    $expandedTree = $this->hierarchies->build($expandedRows, $scope);
                    $selected = $this->hierarchies->find($expandedTree, $requestedRoot);
                    if ($selected === null) {
                        throw ValidationException::withMessages([
                            'root_node' => 'El nodo solicitado dejó de pertenecer al resultado curricular actual.',
                        ]);
                    }
                    $leavesIncluded = true;
                }
            } else {
                $leavesIncluded = $this->hierarchies->containsType($selected, 'objective');
            }

            $tree = $this->hierarchies->rebase($selected);
        }

        if ($view === 'radial_tree') {
            $tree = $this->hierarchies->limitDepth($tree, count($hierarchy));
            $leavesIncluded = $objectiveRequested
                && $this->hierarchies->containsType($tree, 'objective');
        }

        $graph = $this->graphs->build($tree, $this->graphNodeLimit($view));
        $aggregated = $objectiveRequested
            && $currentSummary['total_objectives'] > 0
            && ! $this->hierarchies->containsType($tree, 'objective');

        return [
            'meta' => [
                'scope' => $scope,
                'view' => $view,
                'total_objectives' => $currentSummary['total_objectives'],
                'filtered_total_objectives' => $baseSummary['total_objectives'],
                'total_subjects' => $currentSummary['total_subjects'],
                'total_curricular_groups' => $currentSummary['total_curricular_groups'],
                'available_objectives' => $currentSummary['available_objectives'],
                'unavailable_objectives' => $currentSummary['unavailable_objectives'],
                'canonical_source_conflicts' => $currentSummary['canonical_source_conflicts'],
                'hierarchy' => $hierarchy,
                'max_depth' => count($hierarchy),
                'leaves_included' => $leavesIncluded,
                'aggregated' => $aggregated,
                'leaf_threshold' => $leafThreshold,
                'root_node' => $tree['id'],
                'root_path' => $tree['path'],
                'graph_node_limit' => $graph['node_limit'],
                'graph_total_nodes' => $graph['total_nodes'],
                'graph_truncated' => $graph['truncated'],
            ],
            'root' => $tree,
            'graph' => $graph,
        ];
    }

    /** @return list<string> */
    private function effectiveHierarchy(CurriculumObjectiveVisualizationRequest $request): array
    {
        $hierarchy = $request->hierarchyDimensions();
        $view = $request->string('view', 'treemap')->toString() ?: 'treemap';
        $viewMaximum = match ($view) {
            'radial_tree' => $request->filled('root_node') ? 5 : 3,
            'sankey' => 4,
            default => 5,
        };
        $defaultDepth = min(count($hierarchy), $viewMaximum);
        $maximum = min(count($hierarchy), $request->integer('max_depth', $defaultDepth), $viewMaximum, 5);

        return array_slice($hierarchy, 0, max(1, $maximum));
    }

    /** @return array{total_objectives:int,total_subjects:int,total_curricular_groups:int,available_objectives:int,unavailable_objectives:int,canonical_source_conflicts:int} */
    private function summary(Builder $query): array
    {
        $rows = $this->rowsQuery($query);
        $row = $rows->selectRaw(
            'COUNT(DISTINCT objectives.id) as total_objectives, '
            .'COUNT(DISTINCT objectives.schedule_subject_id) as subject_count, '
            .'MAX(CASE WHEN objectives.schedule_subject_id IS NULL THEN 1 ELSE 0 END) as has_general_subject, '
            ."COUNT(DISTINCT CASE WHEN objectives.axis_code IS NULL OR objectives.axis_code = '' OR objectives.axis_code = 'SIN_CODIGO' THEN '__UNCLASSIFIED__' ELSE objectives.axis_code END) as curricular_group_count, "
            .'COUNT(DISTINCT CASE WHEN objectives.active = 1 THEN objectives.id ELSE NULL END) as available_objectives'
        )->first();
        $total = (int) ($row?->total_objectives ?? 0);
        $available = (int) ($row?->available_objectives ?? 0);

        return [
            'total_objectives' => $total,
            'total_subjects' => (int) ($row?->subject_count ?? 0) + (int) ($row?->has_general_subject ?? 0),
            'total_curricular_groups' => (int) ($row?->curricular_group_count ?? 0),
            'available_objectives' => $available,
            'unavailable_objectives' => $total - $available,
            'canonical_source_conflicts' => $this->canonicalSourceConflicts($query),
        ];
    }

    /**
     * @param  list<string>  $dimensions
     * @param  array<string, int>  $summary
     * @return Collection<int, array<string, mixed>>
     */
    private function aggregateRows(Builder $query, array $dimensions, array $summary): Collection
    {
        if ($dimensions === []) {
            return collect([[
                'path' => [],
                'objective_count' => (int) $summary['total_objectives'],
                'available_count' => (int) $summary['available_objectives'],
                'unavailable_count' => (int) $summary['unavailable_objectives'],
            ]]);
        }

        $rows = $this->rowsQuery($query);
        [$selects, $groups] = $this->dimensionColumns($dimensions);
        $rows->select($selects)
            ->selectRaw(
                'COUNT(DISTINCT objectives.id) as objective_count, '
                .'COUNT(DISTINCT CASE WHEN objectives.active = 1 THEN objectives.id ELSE NULL END) as available_count'
            )
            ->groupBy(...$groups);
        foreach (array_values(array_unique($groups)) as $column) {
            $rows->orderBy($column);
        }

        return $rows->get()->map(function ($row) use ($dimensions): array {
            $available = (int) $row->available_count;
            $total = (int) $row->objective_count;

            return [
                'path' => $this->path($row, $dimensions),
                'objective_count' => $total,
                'available_count' => $available,
                'unavailable_count' => $total - $available,
            ];
        });
    }

    /** @param list<string> $dimensions @return Collection<int, array<string, mixed>> */
    private function leafRows(Builder $query, array $dimensions, int $threshold): Collection
    {
        $rows = $this->rowsQuery($query);
        [$selects] = $this->dimensionColumns($dimensions);
        $rows->select([
            ...$selects,
            'objectives.public_id as objective_public_id',
            'objectives.code as objective_code',
            'objectives.description as objective_description',
            'objectives.objective_type as objective_kind',
            'objectives.level_code as objective_level_code',
            'objectives.grade_code as objective_grade_code',
            'objectives.curriculum_track as objective_curriculum_track',
            'objectives.axis_code as objective_axis_code',
            'objectives.active as objective_active',
            'subjects.code as objective_subject_code',
            'subjects.name as objective_subject_name',
        ])->orderBy('objectives.id')->limit($threshold + 1);

        return $rows->get()->map(function ($row) use ($dimensions): array {
            $active = (bool) $row->objective_active;
            $path = $this->path($row, $dimensions);
            $path[] = [
                'type' => 'objective',
                'key' => (string) $row->objective_public_id,
                'filter_value' => (string) $row->objective_public_id,
                'entity_id' => (string) $row->objective_public_id,
                'name' => (string) $row->objective_code,
                'short_name' => (string) $row->objective_code,
                'code' => (string) $row->objective_code,
                'description' => (string) $row->objective_description,
                'metadata' => [
                    'objective_type' => $row->objective_kind,
                    'level_code' => $row->objective_level_code,
                    'grade_code' => $row->objective_grade_code,
                    'curriculum_track' => $row->objective_curriculum_track,
                    'curricular_group' => $row->objective_axis_code,
                    'subject_code' => $row->objective_subject_code,
                    'subject_name' => $row->objective_subject_name,
                    'status' => $active ? 'active' : 'inactive',
                ],
            ];

            return [
                'path' => $path,
                'objective_count' => 1,
                'available_count' => $active ? 1 : 0,
                'unavailable_count' => $active ? 0 : 1,
            ];
        });
    }

    /** @param list<string> $dimensions @return array{0:list<string>,1:list<string>} */
    private function dimensionColumns(array $dimensions): array
    {
        $selects = [
            'objectives.level_code as context_level_code',
            'objectives.curriculum_track as context_curriculum_track',
        ];
        $groups = ['objectives.level_code', 'objectives.curriculum_track'];
        foreach ($dimensions as $index => $dimension) {
            foreach ($this->dimensionColumnMap($dimension) as $suffix => $column) {
                $selects[] = $column.' as d'.$index.'_'.$suffix;
                $groups[] = $column;
            }
        }

        return [$selects, array_values(array_unique($groups))];
    }

    /** @return array<string, string> */
    private function dimensionColumnMap(string $dimension): array
    {
        return match ($dimension) {
            'catalog' => [
                'entity_id' => 'catalogs.id', 'public_id' => 'catalogs.public_id', 'code' => 'catalogs.code',
                'name' => 'catalogs.name', 'version' => 'catalogs.version', 'authority' => 'catalogs.authority',
            ],
            'education_level' => ['value' => 'objectives.level_code'],
            'grade' => ['value' => 'objectives.grade_code'],
            'formation' => ['value' => 'objectives.curriculum_track'],
            'subject' => [
                'entity_id' => 'subjects.id', 'code' => 'subjects.code', 'name' => 'subjects.name',
                'area' => 'subjects.area', 'color' => 'subjects.color',
            ],
            'curricular_group' => ['value' => 'objectives.axis_code'],
            'objective_type' => ['value' => 'objectives.objective_type'],
            'source' => [
                'entity_id' => 'sources.id', 'public_id' => 'sources.public_id', 'code' => 'sources.source_key',
                'name' => 'sources.source_name', 'authority' => 'sources.authority', 'status' => 'sources.status',
            ],
            'status' => ['value' => 'objectives.active'],
            default => throw new \InvalidArgumentException('Dimensión curricular no soportada.'),
        };
    }

    /** @param list<string> $dimensions @return list<array<string, mixed>> */
    private function path(object $row, array $dimensions): array
    {
        $path = [];
        foreach ($dimensions as $index => $dimension) {
            $descriptor = $this->descriptor($row, $dimension, $index);
            if ($dimension === 'curricular_group' && $this->isParvulariaRow($row)) {
                $matchingSubject = collect($path)->first(fn (array $item): bool => ($item['type'] ?? null) === 'subject'
                    && $this->normalizedLabel((string) ($item['name'] ?? '')) === $this->normalizedLabel((string) $descriptor['name']));
                if ($matchingSubject !== null) {
                    continue;
                }
            }
            $path[] = $descriptor;
        }

        return $path;
    }

    /** @return array<string, mixed> */
    private function descriptor(object $row, string $dimension, int $index): array
    {
        $value = fn (string $suffix) => data_get($row, 'd'.$index.'_'.$suffix);

        return match ($dimension) {
            'catalog' => [
                'type' => 'catalog', 'key' => (int) $value('entity_id'), 'filter_value' => (int) $value('entity_id'),
                'entity_id' => (int) $value('entity_id'), 'name' => (string) ($value('name') ?: $value('code')),
                'short_name' => $value('code'), 'code' => $value('code'), 'description' => null,
                'metadata' => ['public_id' => $value('public_id'), 'version' => $value('version'), 'authority' => $value('authority')],
            ],
            'education_level' => $this->codeDescriptor('education_level', $value('value'), $this->levelLabel($value('value'))),
            'grade' => $this->codeDescriptor('grade', $value('value'), $this->gradeLabel($value('value'))),
            'formation' => $this->codeDescriptor('formation', $value('value'), $this->formationLabel($value('value'))),
            'subject' => [
                'type' => 'subject', 'key' => $value('entity_id') ?? '__GENERAL__',
                'filter_value' => $value('entity_id') === null ? '__GENERAL__' : (int) $value('entity_id'),
                'entity_id' => $value('entity_id') === null ? null : (int) $value('entity_id'),
                'name' => (string) ($value('name') ?: 'Transversales / sin asignatura'),
                'short_name' => $value('code') ?: 'GENERAL', 'code' => $value('code') ?: 'GENERAL', 'description' => null,
                'metadata' => [
                    'area' => $value('area'),
                    'color' => $value('color'),
                    'classification_label' => $this->isParvulariaRow($row) ? 'Núcleo' : 'Asignatura',
                    'semantic_type' => $this->isParvulariaRow($row) ? 'nucleus' : 'subject',
                ],
            ],
            'curricular_group' => [
                ...$this->codeDescriptor(
                    'curricular_group',
                    $this->normalizedCurricularGroup($value('value')),
                    $this->curricularGroupLabel($value('value')),
                ),
                'metadata' => ['classification_label' => 'Agrupador curricular'],
            ],
            'objective_type' => $this->codeDescriptor('objective_type', $value('value'), $this->objectiveTypeLabel($value('value'))),
            'source' => [
                'type' => 'source', 'key' => $value('public_id') ?? '__UNCLASSIFIED__',
                'filter_value' => $value('public_id') ?? '__UNCLASSIFIED__',
                'entity_id' => $value('public_id'), 'name' => (string) ($value('name') ?: 'Sin fuente canónica'),
                'short_name' => $value('code'), 'code' => $value('code'), 'description' => null,
                'metadata' => ['internal_id' => $value('entity_id'), 'authority' => $value('authority'), 'status' => $value('status')],
            ],
            'status' => [
                'type' => 'status', 'key' => (bool) $value('value') ? 'active' : 'inactive',
                'filter_value' => (bool) $value('value') ? 'active' : 'inactive', 'entity_id' => null,
                'name' => (bool) $value('value') ? 'Disponible' : 'No disponible',
                'short_name' => null, 'code' => (bool) $value('value') ? 'active' : 'inactive', 'description' => null,
                'metadata' => [],
            ],
            default => throw new \InvalidArgumentException('Dimensión curricular no soportada.'),
        };
    }

    /** @return array<string, mixed> */
    private function codeDescriptor(string $type, mixed $value, string $label): array
    {
        $key = $value === null || $value === '' ? '__UNCLASSIFIED__' : (string) $value;

        return [
            'type' => $type,
            'key' => $key,
            'filter_value' => $key,
            'entity_id' => $value,
            'name' => $label,
            'short_name' => $value,
            'code' => $value,
            'description' => null,
            'metadata' => [],
        ];
    }

    private function rowsQuery(Builder $query): QueryBuilder
    {
        $ids = $this->filteredIds($query);
        $canonical = DB::table('lcd_learning_objective_sources')
            ->where('source_role', 'canonical_text')
            ->selectRaw('learning_objective_id, MIN(curriculum_source_id) as curriculum_source_id')
            ->groupBy('learning_objective_id');

        return DB::query()->fromSub($ids->toBase(), 'filtered_objectives')
            ->join('lcd_learning_objectives as objectives', 'objectives.id', '=', 'filtered_objectives.id')
            ->leftJoin('lcd_curriculum_catalogs as catalogs', 'catalogs.id', '=', 'objectives.curriculum_catalog_id')
            ->leftJoin('schedule_subjects as subjects', 'subjects.id', '=', 'objectives.schedule_subject_id')
            ->leftJoinSub($canonical, 'canonical_relationship', fn ($join) => $join
                ->on('canonical_relationship.learning_objective_id', '=', 'objectives.id'))
            ->leftJoin('lcd_curriculum_sources as sources', 'sources.id', '=', 'canonical_relationship.curriculum_source_id');
    }

    private function filteredIds(Builder $query): Builder
    {
        return $this->withoutPresentation($query)
            ->select('lcd_learning_objectives.id')
            ->distinct();
    }

    private function withoutPresentation(Builder $query): Builder
    {
        $query = clone $query;
        $query->setEagerLoads([]);

        return $query->reorder();
    }

    private function canonicalSourceConflicts(Builder $query): int
    {
        $ids = $this->filteredIds($query);
        $conflicts = DB::table('lcd_learning_objective_sources as relationships')
            ->joinSub($ids->toBase(), 'filtered_objectives', fn ($join) => $join
                ->on('filtered_objectives.id', '=', 'relationships.learning_objective_id'))
            ->where('relationships.source_role', 'canonical_text')
            ->selectRaw('relationships.learning_objective_id, COUNT(*) as aggregate')
            ->groupBy('relationships.learning_objective_id')
            ->havingRaw('COUNT(*) > 1');

        return DB::query()->fromSub($conflicts, 'canonical_conflicts')->count();
    }

    /** @param array<string, mixed> $filters */
    private function applyNodeFilters(Builder $query, array $filters): Builder
    {
        $query = $this->withoutPresentation($query);
        foreach ($filters as $dimension => $value) {
            match ($dimension) {
                'catalog' => $query->where('curriculum_catalog_id', (int) $value),
                'education_level' => $this->applyNullableCode($query, 'level_code', $value),
                'grade' => $this->applyNullableCode($query, 'grade_code', $value),
                'formation' => $this->applyNullableCode($query, 'curriculum_track', $value),
                'subject' => $value === '__GENERAL__'
                    ? $query->whereNull('schedule_subject_id')
                    : $query->where('schedule_subject_id', (int) $value),
                'curricular_group' => $this->applyCurricularGroupFilter($query, $value),
                'objective_type' => $this->applyNullableCode($query, 'objective_type', $value),
                'source' => $this->applySourceFilter($query, $value),
                'status' => $query->where('active', $value === 'active'),
                'objective' => $query->where('public_id', (string) $value),
                default => throw ValidationException::withMessages(['root_node' => 'El nodo contiene una dimensión no permitida.']),
            };
        }

        return $query;
    }

    private function applyNullableCode(Builder $query, string $column, mixed $value): Builder
    {
        return $value === '__UNCLASSIFIED__'
            ? $query->where(fn (Builder $item) => $item->whereNull($column)->orWhere($column, ''))
            : $query->where($column, (string) $value);
    }

    private function applyCurricularGroupFilter(Builder $query, mixed $value): Builder
    {
        return $value === '__UNCLASSIFIED__'
            ? $query->where(fn (Builder $item) => $item
                ->whereNull('axis_code')
                ->orWhere('axis_code', '')
                ->orWhere('axis_code', 'SIN_CODIGO'))
            : $query->where('axis_code', (string) $value);
    }

    private function applySourceFilter(Builder $query, mixed $value): Builder
    {
        if ($value === '__UNCLASSIFIED__') {
            return $query->whereDoesntHave('objectiveSources', fn (Builder $relationship) => $relationship
                ->where('source_role', 'canonical_text'));
        }

        return $query->whereHas('objectiveSources', fn (Builder $relationship) => $relationship
            ->where('source_role', 'canonical_text')
            ->whereHas('curriculumSource', fn (Builder $source) => $source->where('public_id', (string) $value)));
    }

    /** @param array<string, mixed> $context */
    private function cacheKey(
        CurriculumObjectiveVisualizationRequest $request,
        array $context,
        ?Book $book,
    ): string {
        $version = [
            'school_id' => data_get($context, 'school.id'),
            'academic_year_id' => data_get($context, 'academic_year.id'),
            'catalogs' => collect($context['catalogs'] ?? [])->map(fn (array $catalog): array => [
                'public_id' => $catalog['public_id'] ?? null,
                'version' => $catalog['version'] ?? null,
                'source_hash' => $catalog['source_hash'] ?? null,
                'active' => $catalog['active'] ?? null,
            ])->values()->all(),
            'activations' => collect($context['activations'] ?? [])->map(fn (array $activation): array => [
                'public_id' => $activation['public_id'] ?? null,
                'activation_version' => $activation['activation_version'] ?? null,
                'decision_hash' => $activation['decision_hash'] ?? null,
                'status' => $activation['status'] ?? null,
            ])->values()->all(),
            'book' => $book ? [
                'id' => $book->id,
                'public_id' => $book->public_id,
                'lock_version' => $book->lock_version,
                'updated_at' => $book->updated_at?->toIso8601String(),
            ] : null,
            'filters' => $request->only(self::FILTER_KEYS),
        ];

        return 'lcd:curriculum-visualization:v1:'.$this->canonical->hash($version);
    }

    private function levelLabel(mixed $value): string
    {
        return match ((string) $value) {
            'PARVULARIA' => 'Educación Parvularia',
            'BASICA' => 'Educación Básica',
            'MEDIA' => 'Educación Media',
            default => $this->labelFromCode($value),
        };
    }

    private function gradeLabel(mixed $value): string
    {
        return match ((string) $value) {
            'NT1' => 'NT1 · Prekínder',
            'NT2' => 'NT2 · Kínder',
            default => $this->labelFromCode($value),
        };
    }

    private function formationLabel(mixed $value): string
    {
        return match ((string) $value) {
            'PARVULARIA' => 'Educación Parvularia',
            'GENERAL' => 'Formación General',
            'HC' => 'Humanístico-Científica',
            'TP' => 'Técnico-Profesional',
            'ARTISTICA' => 'Artística',
            default => $this->labelFromCode($value),
        };
    }

    private function objectiveTypeLabel(mixed $value): string
    {
        return match ((string) $value) {
            'OA' => 'Objetivo de Aprendizaje',
            'OAT' => 'Objetivo de Aprendizaje Transversal',
            'OAH' => 'Objetivo de habilidad',
            'OAA' => 'Objetivo de actitud',
            'OAG' => 'Objetivo de Aprendizaje Genérico',
            'OAC' => 'Objetivo de Aprendizaje Complementario',
            default => $this->labelFromCode($value),
        };
    }

    private function curricularGroupLabel(mixed $value): string
    {
        return in_array((string) $value, ['', 'SIN_CODIGO'], true) || $value === null
            ? 'Sin clasificación principal'
            : $this->labelFromCode($value);
    }

    private function labelFromCode(mixed $value): string
    {
        if ($value === null || trim((string) $value) === '') {
            return 'Sin clasificación';
        }

        return mb_convert_case(str_replace('_', ' ', trim((string) $value)), MB_CASE_TITLE, 'UTF-8');
    }

    private function normalizedLabel(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', Str::lower(Str::ascii($value))) ?? '';
    }

    private function normalizedCurricularGroup(mixed $value): mixed
    {
        return in_array((string) $value, ['', 'SIN_CODIGO'], true) || $value === null
            ? null
            : $value;
    }

    private function isParvulariaRow(object $row): bool
    {
        return (string) data_get($row, 'context_level_code') === 'PARVULARIA'
            || (string) data_get($row, 'context_curriculum_track') === 'PARVULARIA';
    }

    private function leafThreshold(): int
    {
        return min(500, max(1, (int) config('libro_digital.curriculum_visualizations.leaf_threshold', 500)));
    }

    private function graphNodeLimit(string $view): int
    {
        $configured = min(300, max(1, (int) config('libro_digital.curriculum_visualizations.graph_node_limit', 300)));

        return $view === 'mind_map' ? min(250, $configured) : $configured;
    }
}
