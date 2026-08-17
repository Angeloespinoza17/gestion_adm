<?php

namespace App\Services\LibroDigital;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Models\AcademicYear;
use App\Models\LibroDigital\CurriculumCatalogActivation;
use App\Models\LibroDigital\CurriculumImportBatch;
use App\Models\LibroDigital\CurriculumImportEvidence;
use App\Models\LibroDigital\LearningObjective;
use App\Models\LibroDigital\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CurriculumExplorerService
{
    private const REQUIRED_TABLES = [
        'lcd_curriculum_catalogs',
        'lcd_curriculum_import_batches',
        'lcd_curriculum_import_evidences',
        'lcd_curriculum_catalog_activations',
        'lcd_learning_objectives',
        'lcd_curriculum_sources',
        'lcd_learning_objective_sources',
    ];

    /**
     * @return array{
     *   school:array<string,mixed>,academic_year:array<string,mixed>,schema_ready:bool,
     *   catalog_status:string,catalog_ids:list<int>,catalogs:list<array<string,mixed>>,
     *   latest_import:?array<string,mixed>,activations:list<array<string,mixed>>,
     *   evidence:array<string,int>,compliance_blocker:?array<string,string>
     * }
     */
    public function context(School $school, int $academicYearId): array
    {
        /** @var AcademicYear|null $academicYear */
        $academicYear = $school->academicYears()
            ->where('academic_years.id', $academicYearId)
            ->wherePivot('active', true)
            ->first();
        if (! $academicYear) {
            throw new LibroDigitalException(
                'El año académico no está habilitado para el establecimiento seleccionado.',
                'LCD_CURRICULUM_SCHOOL_YEAR_REQUIRED',
                422,
            );
        }

        $base = [
            'school' => [
                'id' => $school->id,
                'public_id' => $school->public_id,
                'name' => $school->name,
                'rbd' => $school->rbd,
            ],
            'academic_year' => [
                'id' => $academicYear->id,
                'name' => $academicYear->name,
                'year' => (int) $academicYear->year,
                'is_active' => (bool) $academicYear->is_active,
                'is_closed' => (bool) $academicYear->is_closed,
            ],
            'catalog_ids' => [],
            'catalogs' => [],
            'latest_import' => null,
            'activations' => [],
            'evidence' => ['total' => 0, 'verified' => 0, 'pending' => 0, 'rejected' => 0],
        ];

        if (! $this->schemaReady()) {
            return [
                ...$base,
                'schema_ready' => false,
                'catalog_status' => 'schema_pending',
                'compliance_blocker' => [
                    'code' => 'COMPLIANCE_BLOCKER_CURRICULUM_SCHEMA_PENDING',
                    'message' => 'El esquema curricular todavía no está disponible en esta instalación.',
                ],
            ];
        }

        $latestImport = CurriculumImportBatch::query()
            ->where('school_id', $school->id)
            ->where('academic_year_id', $academicYear->id)
            ->latest('id')
            ->first();
        $activations = CurriculumCatalogActivation::query()
            ->where('school_id', $school->id)
            ->where('academic_year_id', $academicYear->id)
            ->where('status', CurriculumCatalogActivation::STATUS_ACTIVATED)
            ->with([
                'curriculumCatalog:id,public_id,code,name,version,authority,source_hash,active,effective_from,effective_to',
                'importBatch:id,public_id,status,source_hash,manifest_hash,completed_at',
            ])
            ->orderByDesc('activation_version')
            ->get();

        $eligible = $activations->filter(function (CurriculumCatalogActivation $activation): bool {
            $catalog = $activation->curriculumCatalog;
            $batch = $activation->importBatch;

            return $catalog !== null
                && $batch !== null
                && (bool) $catalog->active
                && (string) $batch->status === CurriculumImportBatch::STATUS_ACTIVATED
                && preg_match('/^[a-f0-9]{64}$/i', (string) $catalog->source_hash) === 1;
        })->values();
        $catalogIds = $eligible->pluck('curriculum_catalog_id')->map(fn ($id): int => (int) $id)->unique()->values();
        $importBatchIds = $eligible->pluck('import_batch_id')->map(fn ($id): int => (int) $id)->unique()->values();
        $evidence = $this->evidenceCounts($importBatchIds);

        $status = match (true) {
            $catalogIds->isNotEmpty() => 'activated',
            $activations->isNotEmpty() => 'integrity_blocked',
            $latestImport !== null => (string) $latestImport->status,
            default => 'not_imported',
        };
        $blocker = match ($status) {
            'activated' => null,
            'integrity_blocked' => [
                'code' => 'COMPLIANCE_BLOCKER_CURRICULUM_INTEGRITY_INVALID',
                'message' => 'La activación curricular no supera las condiciones mínimas de integridad.',
            ],
            default => [
                'code' => 'COMPLIANCE_BLOCKER_CURRICULUM_NOT_IMPORTED',
                'message' => 'No existe un catálogo curricular oficial activado para este establecimiento y año académico.',
            ],
        };

        return [
            ...$base,
            'schema_ready' => true,
            'catalog_status' => $status,
            'catalog_ids' => $catalogIds->all(),
            'catalogs' => $eligible->map(fn (CurriculumCatalogActivation $activation): array => [
                'id' => $activation->curriculumCatalog?->id,
                'public_id' => $activation->curriculumCatalog?->public_id,
                'code' => $activation->curriculumCatalog?->code,
                'name' => $activation->curriculumCatalog?->name,
                'version' => $activation->curriculumCatalog?->version,
                'authority' => $activation->curriculumCatalog?->authority,
                'source_hash' => $activation->curriculumCatalog?->source_hash,
                'active' => (bool) $activation->curriculumCatalog?->active,
            ])->all(),
            'latest_import' => $latestImport ? [
                'id' => $latestImport->id,
                'public_id' => $latestImport->public_id,
                'status' => $latestImport->status,
                'catalog_code' => $latestImport->catalog_code,
                'catalog_version' => $latestImport->catalog_version,
                'source_hash' => $latestImport->source_hash,
                'manifest_hash' => $latestImport->manifest_hash,
                'objective_count' => (int) $latestImport->objective_count,
                'error_count' => (int) $latestImport->error_count,
                'warning_count' => (int) $latestImport->warning_count,
                'validated_at' => $latestImport->validated_at?->toIso8601String(),
                'completed_at' => $latestImport->completed_at?->toIso8601String(),
            ] : null,
            'activations' => $activations->map(fn (CurriculumCatalogActivation $activation): array => [
                'id' => $activation->id,
                'public_id' => $activation->public_id,
                'catalog_id' => $activation->curriculum_catalog_id,
                'import_batch_id' => $activation->import_batch_id,
                'status' => $activation->status,
                'activation_version' => (int) $activation->activation_version,
                'effective_from' => $activation->effective_from?->toDateString(),
                'effective_to' => $activation->effective_to?->toDateString(),
                'decision_hash' => $activation->decision_hash,
                'activated_at' => $activation->activated_at?->toIso8601String(),
            ])->all(),
            'evidence' => $evidence,
            'compliance_blocker' => $blocker,
        ];
    }

    /** @param list<int> $catalogIds */
    public function paginate(Request $request, array $catalogIds): LengthAwarePaginator
    {
        return $this->exportQuery($request, $catalogIds)
            ->paginate($request->integer('per_page', 50));
    }

    /**
     * Returns the same filtered, eager-loaded and deterministic query used by the
     * explorer. Exporters intentionally reuse this method so list and files never
     * drift in their filter semantics.
     *
     * @param  list<int>  $catalogIds
     */
    public function exportQuery(Request $request, array $catalogIds): Builder
    {
        return $this->query($request, $catalogIds)
            ->with([
                'curriculumCatalog:id,public_id,code,name,version,authority,source_url,source_hash,effective_from,effective_to,active',
                'subject:id,name,code,area,color,active',
                'objectiveSources.curriculumSource:id,public_id,curriculum_catalog_id,source_key,source_scope,source_name,authority,document_number,source_url,declared_sha256,verified_sha256,effective_from,effective_to,curriculum_track,objective_type,status',
            ])
            ->orderByRaw('CASE WHEN grade_code = ? THEN 0 WHEN grade_code = ? THEN 1 WHEN grade_code LIKE ? THEN 2 ELSE 3 END', ['NT1', 'NT2', '%B'])
            ->orderBy('grade_code')
            ->orderBy('schedule_subject_id')
            ->orderBy('objective_type')
            ->orderBy('code')
            ->orderBy('id');
    }

    /**
     * Shared filtered query without presentation concerns. Aggregations reuse
     * this method so the table, exports and visualizations cannot drift.
     *
     * @param  list<int>  $catalogIds
     */
    public function query(Request $request, array $catalogIds): Builder
    {
        return $this->filteredQuery($request, $this->objectiveQuery($catalogIds));
    }

    /** @param list<int> $catalogIds @return array<string, int> */
    public function summary(Request $request, array $catalogIds): array
    {
        $base = $this->objectiveQuery($catalogIds);
        $total = (clone $base)->count();
        $active = (clone $base)->where('active', true)->count();
        $withSources = (clone $base)->whereHas('objectiveSources')->count();
        $withCanonical = (clone $base)->whereHas('objectiveSources', fn (Builder $query) => $query
            ->where('source_role', 'canonical_text'))->count();

        return [
            'total_objectives' => $total,
            'filtered_objectives' => $this->filteredQuery($request, clone $base)->count(),
            'active_objectives' => $active,
            'inactive_objectives' => $total - $active,
            'objectives_with_sources' => $withSources,
            'objectives_without_sources' => $total - $withSources,
            'objectives_with_canonical_source' => $withCanonical,
            'objectives_missing_canonical_source' => $total - $withCanonical,
            'verified_sources' => DB::table('lcd_curriculum_sources')
                ->whereIn('curriculum_catalog_id', $catalogIds)
                ->where('status', 'verified')
                ->whereNotNull('declared_sha256')
                ->whereColumn('declared_sha256', 'verified_sha256')
                ->count(),
        ];
    }

    /** @param list<int> $catalogIds @return array<string, list<array<string, mixed>>> */
    public function facets(array $catalogIds): array
    {
        if ($catalogIds === []) {
            return $this->emptyFacets();
        }

        $base = $this->objectiveQuery($catalogIds);

        return [
            'levels' => $this->columnFacet(clone $base, 'level_code'),
            'grades' => $this->columnFacet(clone $base, 'grade_code'),
            'curriculum_tracks' => $this->columnFacet(clone $base, 'curriculum_track', includeNull: true),
            'objective_types' => $this->columnFacet(clone $base, 'objective_type'),
            'axes' => $this->columnFacet(clone $base, 'axis_code'),
            'statuses' => [
                ['value' => 'active', 'label' => 'Activo', 'count' => (clone $base)->where('active', true)->count()],
                ['value' => 'inactive', 'label' => 'Inactivo', 'count' => (clone $base)->where('active', false)->count()],
            ],
            'subjects' => $this->subjectFacets($catalogIds),
            'sources' => DB::table('lcd_learning_objective_sources as relationships')
                ->join('lcd_curriculum_sources as sources', 'sources.id', '=', 'relationships.curriculum_source_id')
                ->join('lcd_learning_objectives as objectives', 'objectives.id', '=', 'relationships.learning_objective_id')
                ->whereIn('objectives.curriculum_catalog_id', $catalogIds)
                ->selectRaw('sources.public_id as id, sources.source_key as value, sources.source_name as label, COUNT(DISTINCT objectives.id) as count')
                ->groupBy('sources.public_id', 'sources.source_key', 'sources.source_name')
                ->orderBy('sources.source_name')
                ->get()->map(fn ($row): array => [
                    'id' => (string) $row->id,
                    'value' => (string) $row->value,
                    'label' => (string) $row->label,
                    'count' => (int) $row->count,
                ])->all(),
        ];
    }

    /**
     * KPIs over an already tenant/book-scoped query. The optional filtered
     * query lets the UI keep baseline KPIs while export limits use the exact
     * filtered count shown in the list.
     *
     * @return array<string, int>
     */
    public function summaryForQueries(Builder $base, ?Builder $filtered = null): array
    {
        $base = $this->withoutPresentation($base);
        $filtered = $filtered ? $this->withoutPresentation($filtered) : clone $base;
        $total = (clone $base)->count();
        $active = (clone $base)->where('active', true)->count();
        $withSources = (clone $base)->whereHas('objectiveSources')->count();
        $withCanonical = (clone $base)->whereHas('objectiveSources', fn (Builder $query) => $query
            ->where('source_role', 'canonical_text'))->count();
        $ids = (clone $base)->select('lcd_learning_objectives.id');
        $verifiedSources = DB::table('lcd_learning_objective_sources as relationships')
            ->joinSub($ids->toBase(), 'scoped_objectives', fn ($join) => $join
                ->on('scoped_objectives.id', '=', 'relationships.learning_objective_id'))
            ->join('lcd_curriculum_sources as sources', 'sources.id', '=', 'relationships.curriculum_source_id')
            ->where('sources.status', 'verified')
            ->whereNotNull('sources.declared_sha256')
            ->whereColumn('sources.declared_sha256', 'sources.verified_sha256')
            ->distinct()->count('sources.id');

        return [
            'total_objectives' => $total,
            'filtered_objectives' => (clone $filtered)->count(),
            'active_objectives' => $active,
            'inactive_objectives' => $total - $active,
            'objectives_with_sources' => $withSources,
            'objectives_without_sources' => $total - $withSources,
            'objectives_with_canonical_source' => $withCanonical,
            'objectives_missing_canonical_source' => $total - $withCanonical,
            'verified_sources' => $verifiedSources,
        ];
    }

    /** @return array<string, list<array<string, mixed>>> */
    public function facetsForQuery(Builder $query): array
    {
        $base = $this->withoutPresentation($query);
        $subjectRows = DB::query()->fromSub(
            (clone $base)->select(['lcd_learning_objectives.id', 'lcd_learning_objectives.schedule_subject_id'])->toBase(),
            'objectives',
        )->join('schedule_subjects as subjects', 'subjects.id', '=', 'objectives.schedule_subject_id')
            ->selectRaw('subjects.id as id, subjects.code as value, subjects.name as label, COUNT(*) as count')
            ->groupBy('subjects.id', 'subjects.code', 'subjects.name')
            ->orderBy('subjects.name')->get()->map(fn ($row): array => [
                'id' => (int) $row->id,
                'value' => (string) $row->value,
                'label' => (string) $row->label,
                'count' => (int) $row->count,
            ]);
        $generalCount = (clone $base)->whereNull('schedule_subject_id')->count();
        if ($generalCount > 0) {
            $subjectRows->prepend([
                'id' => null,
                'value' => '__GENERAL__',
                'label' => 'Transversales / sin asignatura',
                'count' => $generalCount,
            ]);
        }

        $ids = (clone $base)->select('lcd_learning_objectives.id');
        $sources = DB::table('lcd_learning_objective_sources as relationships')
            ->joinSub($ids->toBase(), 'objectives', fn ($join) => $join
                ->on('objectives.id', '=', 'relationships.learning_objective_id'))
            ->join('lcd_curriculum_sources as sources', 'sources.id', '=', 'relationships.curriculum_source_id')
            ->selectRaw('sources.public_id as id, sources.source_key as value, sources.source_name as label, COUNT(DISTINCT objectives.id) as count')
            ->groupBy('sources.public_id', 'sources.source_key', 'sources.source_name')
            ->orderBy('sources.source_name')
            ->get()->map(fn ($row): array => [
                'id' => (string) $row->id,
                'value' => (string) $row->value,
                'label' => (string) $row->label,
                'count' => (int) $row->count,
            ])->all();

        return [
            'levels' => $this->columnFacet(clone $base, 'level_code'),
            'grades' => $this->columnFacet(clone $base, 'grade_code'),
            'curriculum_tracks' => $this->columnFacet(clone $base, 'curriculum_track', includeNull: true),
            'objective_types' => $this->columnFacet(clone $base, 'objective_type'),
            'axes' => $this->columnFacet(clone $base, 'axis_code'),
            'statuses' => [
                ['value' => 'active', 'label' => 'Activo', 'count' => (clone $base)->where('active', true)->count()],
                ['value' => 'inactive', 'label' => 'Inactivo', 'count' => (clone $base)->where('active', false)->count()],
            ],
            'subjects' => $subjectRows->values()->all(),
            'sources' => $sources,
        ];
    }

    /** @param list<int> $catalogIds */
    public function find(string $identifier, array $catalogIds): ?LearningObjective
    {
        if ($catalogIds === []) {
            return null;
        }

        return $this->objectiveQuery($catalogIds)
            ->where(function (Builder $query) use ($identifier): void {
                if (ctype_digit($identifier)) {
                    $query->whereKey((int) $identifier)->orWhere('public_id', $identifier);

                    return;
                }

                $query->where('public_id', $identifier);
            })
            ->with([
                'curriculumCatalog:id,public_id,code,name,version,authority,source_url,source_hash,effective_from,effective_to,active',
                'subject:id,name,code,area,color,active',
                'objectiveSources.curriculumSource.normativeSource:id,public_id,title,authority,document_number,source_url,sha256,status,published_on,consulted_at',
            ])
            ->first();
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, array<string, mixed>>
     */
    public function sourceEvidence(array $context): array
    {
        $batchIds = collect($context['activations'] ?? [])
            ->pluck('import_batch_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
        if ($batchIds->isEmpty()) {
            return [];
        }

        return CurriculumImportEvidence::query()
            ->whereIn('import_batch_id', $batchIds)
            ->where('evidence_kind', 'official_source_document')
            ->get([
                'id', 'public_id', 'import_batch_id', 'status', 'title', 'original_name', 'detected_mime_type',
                'size_bytes', 'sha256', 'metadata', 'manifest', 'captured_at', 'verified_at',
            ])
            ->mapWithKeys(function (CurriculumImportEvidence $evidence): array {
                $sourceKey = (string) (data_get($evidence->metadata, 'source_key')
                    ?? data_get($evidence->manifest, 'source_key')
                    ?? '');
                if ($sourceKey === '') {
                    return [];
                }

                return [$sourceKey.'|'.mb_strtolower((string) $evidence->sha256) => [
                    'id' => $evidence->id,
                    'public_id' => $evidence->public_id,
                    'status' => $evidence->status,
                    'title' => $evidence->title,
                    'original_name' => $evidence->original_name,
                    'mime_type' => $evidence->detected_mime_type,
                    'size_bytes' => (int) ($evidence->size_bytes ?? 0),
                    'sha256' => $evidence->sha256,
                    'captured_at' => $evidence->captured_at?->toIso8601String(),
                    'verified_at' => $evidence->verified_at?->toIso8601String(),
                ]];
            })->all();
    }

    /** @param list<int> $catalogIds */
    private function objectiveQuery(array $catalogIds): Builder
    {
        return LearningObjective::query()->whereIn('curriculum_catalog_id', $catalogIds);
    }

    private function filteredQuery(Request $request, Builder $query): Builder
    {
        $status = $request->string('status', 'all')->lower()->toString();
        if ($status === 'active') {
            $query->where('active', true);
        } elseif ($status === 'inactive') {
            $query->where('active', false);
        }

        foreach (['level_code', 'grade_code', 'objective_type', 'axis_code'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->string($field)->trim()->upper()->toString());
            }
        }
        if ($request->filled('curriculum_track')) {
            $track = $request->string('curriculum_track')->trim()->upper()->toString();
            in_array($track, ['COMMON', 'NULL', 'SIN_TRACK'], true)
                ? $query->whereNull('curriculum_track')
                : $query->where('curriculum_track', $track);
        }
        if ($request->filled('catalog_id')) {
            $query->where('curriculum_catalog_id', $request->integer('catalog_id'));
        }
        if ($request->filled('schedule_subject_id')) {
            $query->where('schedule_subject_id', $request->integer('schedule_subject_id'));
        } elseif ($request->filled('subject_code')) {
            $subjectCode = $request->string('subject_code')->trim()->toString();
            in_array(mb_strtoupper($subjectCode), ['__GENERAL__', 'GENERAL', 'TRANSVERSAL'], true)
                ? $query->whereNull('schedule_subject_id')
                : $query->whereHas('subject', fn (Builder $subject) => $subject->where('code', $subjectCode));
        } elseif ($request->filled('subject')) {
            $subjectTerm = $request->string('subject')->trim()->toString();
            $query->whereHas('subject', function (Builder $subject) use ($subjectTerm): void {
                if (ctype_digit($subjectTerm)) {
                    $subject->whereKey((int) $subjectTerm);

                    return;
                }
                $like = $this->like($subjectTerm);
                $subject->where(fn (Builder $match) => $match
                    ->where('code', $subjectTerm)
                    ->orWhere('name', 'like', $like));
            });
        }
        if ($request->filled('source')) {
            $sourceTerm = $request->string('source')->trim()->toString();
            $like = $this->like($sourceTerm);
            $query->whereHas('objectiveSources.curriculumSource', fn (Builder $source) => $source
                ->where(fn (Builder $match) => $match
                    ->where('public_id', $sourceTerm)
                    ->orWhere('source_key', $sourceTerm)
                    ->orWhere('source_name', 'like', $like)
                    ->orWhere('document_number', 'like', $like)
                    ->orWhere('authority', 'like', $like)));
        }
        if ($request->filled('query')) {
            $term = $request->string('query')->trim()->toString();
            $like = $this->like($term);
            $query->where(function (Builder $search) use ($term, $like): void {
                $search->where('code', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('axis_code', 'like', $like)
                    ->orWhere('unit_code', 'like', $like)
                    ->orWhereHas('subject', fn (Builder $subject) => $subject
                        ->where(fn (Builder $match) => $match
                            ->where('code', $term)
                            ->orWhere('name', 'like', $like)))
                    ->orWhereHas('objectiveSources.curriculumSource', fn (Builder $source) => $source
                        ->where(fn (Builder $match) => $match
                            ->where('source_key', $term)
                            ->orWhere('source_name', 'like', $like)
                            ->orWhere('document_number', 'like', $like)));
            });
        }

        return $query;
    }

    private function withoutPresentation(Builder $query): Builder
    {
        $query = clone $query;
        $query->setEagerLoads([]);

        return $query->reorder();
    }

    /** @return list<array{value:?string,label:string,count:int}> */
    private function columnFacet(Builder $query, string $column, bool $includeNull = false): array
    {
        if (! $includeNull) {
            $query->whereNotNull($column)->where($column, '<>', '');
        }

        return $query->selectRaw($column.' as value, COUNT(*) as count')
            ->groupBy($column)
            ->orderBy($column)
            ->get()->map(fn ($row): array => [
                'value' => $row->value === null ? null : (string) $row->value,
                'label' => $row->value === null || $row->value === '' ? 'Sin clasificación' : (string) $row->value,
                'count' => (int) $row->count,
            ])->all();
    }

    /** @param list<int> $catalogIds @return list<array<string, mixed>> */
    private function subjectFacets(array $catalogIds): array
    {
        $subjects = DB::table('lcd_learning_objectives as objectives')
            ->join('schedule_subjects as subjects', 'subjects.id', '=', 'objectives.schedule_subject_id')
            ->whereIn('objectives.curriculum_catalog_id', $catalogIds)
            ->selectRaw('subjects.id as id, subjects.code as value, subjects.name as label, COUNT(*) as count')
            ->groupBy('subjects.id', 'subjects.code', 'subjects.name')
            ->orderBy('subjects.name')
            ->get()->map(fn ($row): array => [
                'id' => (int) $row->id,
                'value' => (string) $row->value,
                'label' => (string) $row->label,
                'count' => (int) $row->count,
            ]);
        $generalCount = DB::table('lcd_learning_objectives')
            ->whereIn('curriculum_catalog_id', $catalogIds)
            ->whereNull('schedule_subject_id')
            ->count();
        if ($generalCount > 0) {
            $subjects->prepend([
                'id' => null,
                'value' => '__GENERAL__',
                'label' => 'Transversales / sin asignatura',
                'count' => $generalCount,
            ]);
        }

        return $subjects->values()->all();
    }

    /** @return array<string, list<mixed>> */
    private function emptyFacets(): array
    {
        return [
            'levels' => [],
            'grades' => [],
            'curriculum_tracks' => [],
            'objective_types' => [],
            'axes' => [],
            'statuses' => [],
            'subjects' => [],
            'sources' => [],
        ];
    }

    /** @param Collection<int, int> $importBatchIds @return array<string, int> */
    private function evidenceCounts(Collection $importBatchIds): array
    {
        if ($importBatchIds->isEmpty()) {
            return ['total' => 0, 'verified' => 0, 'pending' => 0, 'rejected' => 0];
        }

        $counts = DB::table('lcd_curriculum_import_evidences')
            ->whereIn('import_batch_id', $importBatchIds)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'total' => (int) $counts->sum(),
            'verified' => (int) ($counts['verified'] ?? 0),
            'pending' => (int) ($counts['pending_verification'] ?? 0),
            'rejected' => (int) ($counts['rejected'] ?? 0),
        ];
    }

    private function schemaReady(): bool
    {
        return collect(self::REQUIRED_TABLES)->every(fn (string $table): bool => Schema::hasTable($table));
    }

    private function like(string $value): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value).'%';
    }
}
