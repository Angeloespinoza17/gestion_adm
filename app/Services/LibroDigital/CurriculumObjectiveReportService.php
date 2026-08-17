<?php

namespace App\Services\LibroDigital;

use App\Enums\LibroDigital\BookStatus;
use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Models\LibroDigital\Book;
use App\Models\LibroDigital\LearningObjective;
use App\Models\LibroDigital\LearningObjectiveSource;
use App\Models\LibroDigital\ReportExport;
use App\Models\LibroDigital\School;
use App\Services\Attendance\AttendancePdfBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CurriculumObjectiveReportService
{
    public const XLSX_MAX_ROWS = 10000;

    public const PDF_MAX_ROWS = 750;

    public function __construct(
        private readonly CurriculumExplorerService $explorer,
        private readonly CurriculumCourseScopeResolver $courseScopes,
        private readonly BookCurriculumScopeResolver $bookScopes,
        private readonly CanonicalJson $canonical,
        private readonly AttendancePdfBuilder $pdf,
        private readonly CurriculumObjectivesXlsxBuilder $xlsx,
    ) {}

    /**
     * Validate and canonicalize the public filter set before a report row is
     * created. Derived course fields are kept in the sealed filter snapshot.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function normalizeFilters(School $school, ?Book $book, array $filters, string $format): array
    {
        if (! in_array($format, ['pdf', 'xlsx'], true)) {
            throw new LibroDigitalException(
                'El explorador curricular solo admite exportaciones PDF o XLSX.',
                'LCD_CURRICULUM_EXPORT_FORMAT_INVALID',
                422,
            );
        }

        $yearId = (int) ($filters['academic_year_id'] ?? 0);
        if ($yearId < 1) {
            throw new LibroDigitalException(
                'Selecciona el año académico de la exportación curricular.',
                'LCD_CURRICULUM_EXPORT_YEAR_REQUIRED',
                422,
            );
        }

        $context = $this->explorer->context($school, $yearId);
        if (($context['catalog_ids'] ?? []) === []) {
            throw new LibroDigitalException(
                (string) data_get($context, 'compliance_blocker.message', 'No existe un catálogo curricular oficial activado para exportar.'),
                (string) data_get($context, 'compliance_blocker.code', 'LCD_CURRICULUM_EXPORT_NOT_AVAILABLE'),
                409,
            );
        }

        if ($book && ((int) $book->school_id !== (int) $school->id || (int) $book->academic_year_id !== $yearId)) {
            throw new LibroDigitalException(
                'El libro no pertenece al establecimiento y año académico seleccionados.',
                'LCD_CURRICULUM_EXPORT_BOOK_SCOPE_INVALID',
                403,
            );
        }
        if ($book?->status === BookStatus::Archived) {
            throw new LibroDigitalException(
                'Los libros archivados no admiten nuevas exportaciones curriculares.',
                'LCD_CURRICULUM_EXPORT_BOOK_ARCHIVED',
                422,
            );
        }

        $normalized = collect($filters)
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->reject(fn ($value) => $value === null || $value === '')
            ->all();
        foreach (['level_code', 'grade_code', 'curriculum_track', 'objective_type', 'axis_code'] as $key) {
            if (isset($normalized[$key])) {
                $normalized[$key] = mb_strtoupper((string) $normalized[$key]);
            }
        }
        $normalized['academic_year_id'] = $yearId;
        $normalized['status'] = mb_strtolower((string) ($normalized['status'] ?? ($book ? 'active' : 'all')));
        if ($book && $normalized['status'] !== 'active') {
            throw new LibroDigitalException(
                'Las exportaciones asociadas a un libro solo incluyen objetivos activos y seleccionables.',
                'LCD_CURRICULUM_EXPORT_BOOK_STATUS_INVALID',
                422,
                [['field' => 'filters.status', 'allowed' => ['active']]],
            );
        }

        $normalized = $this->courseScopes->apply($school, $yearId, $normalized, $book);

        $query = $this->scopedQuery($school, $book, $normalized, $context);
        $count = (clone $query)->reorder()->count();
        $this->assertWithinFormatLimit($format, $count);

        return $normalized;
    }

    /** @return array{metadata:array<string,mixed>,sections:array{},curriculum_manifest:array<string,mixed>} */
    public function snapshot(ReportExport $export): array
    {
        $export->loadMissing(['school', 'academicYear', 'book.courseSection.educationLevel', 'requester']);
        $filters = $export->filters_snapshot ?? [];
        $context = $this->explorer->context($export->school, (int) $export->academic_year_id);
        if (($context['catalog_ids'] ?? []) === []) {
            throw new LibroDigitalException(
                'La instantánea no puede sellarse porque el catálogo curricular dejó de estar activado.',
                'LCD_CURRICULUM_EXPORT_CONTEXT_CHANGED',
                409,
            );
        }

        $scan = $this->scan($export, $context);
        $this->assertWithinFormatLimit((string) $export->format, $scan['objective_count']);

        return [
            'metadata' => $this->metadata(
                $export,
                $context,
                $filters,
                $scan['objective_count'],
                $scan['active_count'],
                $scan['relationship_count'],
                $scan['fingerprint'],
            ),
            'sections' => [],
            'curriculum_manifest' => [
                'schema_version' => 1,
                ...$scan,
                'context_hash' => $this->contextHash($context),
                'filters_hash' => $this->canonical->hash($filters),
                'captured_at' => now('UTC')->toIso8601String(),
                'pdf_max_rows' => self::PDF_MAX_ROWS,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{0:string,1:string,2:string}
     */
    public function generate(ReportExport $export, array $snapshot): array
    {
        $manifest = $snapshot['curriculum_manifest'] ?? null;
        $metadata = $snapshot['metadata'] ?? null;
        if (! is_array($manifest) || ! is_array($metadata) || (int) ($manifest['schema_version'] ?? 0) !== 1) {
            throw new RuntimeException('La instantánea curricular sellada tiene un formato inválido.');
        }

        return DB::transaction(function () use ($export, $manifest, $metadata): array {
            $export->loadMissing(['school', 'academicYear', 'book.courseSection.educationLevel', 'requester']);
            $context = $this->explorer->context($export->school, (int) $export->academic_year_id);
            $current = $this->scan($export, $context);
            $this->assertWithinFormatLimit((string) $export->format, $current['objective_count']);
            $stable = hash_equals((string) ($manifest['fingerprint'] ?? ''), $current['fingerprint'])
                && hash_equals((string) ($manifest['context_hash'] ?? ''), $this->contextHash($context))
                && hash_equals((string) ($manifest['filters_hash'] ?? ''), $this->canonical->hash($export->filters_snapshot ?? []))
                && (int) ($manifest['objective_count'] ?? -1) === $current['objective_count']
                && (int) ($manifest['relationship_count'] ?? -1) === $current['relationship_count'];
            if (! $stable) {
                throw new LibroDigitalException(
                    'El corpus curricular cambió después de solicitar la exportación; genera un nuevo informe para sellar una instantánea vigente.',
                    'LCD_CURRICULUM_EXPORT_SNAPSHOT_STALE',
                    409,
                );
            }

            if ($export->format === 'xlsx') {
                $writtenHash = hash_init('sha256');
                $written = ['objective_count' => 0, 'active_count' => 0, 'relationship_count' => 0];
                $contents = $this->xlsx->build(
                    $metadata,
                    $this->fingerprintedRows($this->objectiveRows($export, $context), 'O', $writtenHash, $written),
                    $this->fingerprintedRows($this->sourceRows($export, $context), 'S', $writtenHash, $written),
                );
                $written['fingerprint'] = hash_final($writtenHash);
                $this->assertManifestMatches($manifest, $written);

                return [$contents, 'xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
            }
            if ($export->format !== 'pdf') {
                throw new RuntimeException('Formato curricular no soportado.');
            }
            if ($current['objective_count'] > self::PDF_MAX_ROWS) {
                throw new LibroDigitalException(
                    'El PDF excede el máximo seguro de '.self::PDF_MAX_ROWS.' objetivos; aplica filtros o usa XLSX.',
                    'LCD_CURRICULUM_EXPORT_PDF_LIMIT_EXCEEDED',
                    422,
                );
            }

            $objectives = $this->scopedQuery($export->school, $export->book, $export->filters_snapshot ?? [], $context)->get();
            $sections = [[
                'title' => 'Objetivos curriculares',
                'layout' => 'curriculum_objectives',
                'rows' => $objectives->map(fn (LearningObjective $objective): array => $this->pdfObjective($objective))->all(),
            ]];
            $dashboard = [
                'summary' => ['target_rate' => null],
                'branding' => [
                    'organization_name' => $export->school->legal_name ?: $export->school->name,
                    'report_trace' => 'RBD '.$export->school->rbd.' · ID '.$export->public_id.' · fuente '.substr((string) $export->source_snapshot_hash, 0, 16),
                    'source_label' => 'Catálogo curricular, instantánea sellada '.substr((string) $export->source_snapshot_hash, 0, 16)
                        .' · Unicode NFC + glifos ToUnicode',
                    'watermark' => $export->draft_watermark ? 'BORRADOR · NO VALIDADO PARA FISCALIZACIÓN' : '',
                ],
            ];

            $contents = $this->pdf->build($export->title, $metadata, $sections, $dashboard);
            $this->assertManifestMatches($manifest, $this->scan($export, $context));

            return [$contents, 'pdf', 'application/pdf'];
        }, 3);
    }

    /**
     * @param  iterable<array-key, list<mixed>>  $rows
     * @param  resource|\HashContext  $hash
     * @param  array{objective_count:int,active_count:int,relationship_count:int}  $counts
     * @return \Generator<int, list<mixed>>
     */
    private function fingerprintedRows(iterable $rows, string $prefix, mixed $hash, array &$counts): \Generator
    {
        foreach ($rows as $row) {
            hash_update($hash, $prefix."\0".$this->canonical->encode($row)."\n");
            if ($prefix === 'O') {
                $counts['objective_count']++;
                if (($row[5] ?? null) === 'Activo') {
                    $counts['active_count']++;
                }
            } else {
                $counts['relationship_count']++;
            }
            yield $row;
        }
    }

    /** @param array<string, mixed> $manifest @param array<string, mixed> $actual */
    private function assertManifestMatches(array $manifest, array $actual): void
    {
        if (! hash_equals((string) ($manifest['fingerprint'] ?? ''), (string) ($actual['fingerprint'] ?? ''))
            || (int) ($manifest['objective_count'] ?? -1) !== (int) ($actual['objective_count'] ?? -2)
            || (int) ($manifest['active_count'] ?? -1) !== (int) ($actual['active_count'] ?? -2)
            || (int) ($manifest['relationship_count'] ?? -1) !== (int) ($actual['relationship_count'] ?? -2)) {
            throw new LibroDigitalException(
                'Las filas generadas no coinciden con la instantánea curricular sellada; no se publicó el archivo.',
                'LCD_CURRICULUM_EXPORT_SNAPSHOT_STALE',
                409,
            );
        }
    }

    /** @param array<string, mixed> $context @return array{objective_count:int,active_count:int,relationship_count:int,fingerprint:string} */
    private function scan(ReportExport $export, array $context): array
    {
        $hash = hash_init('sha256');
        $objectives = 0;
        $active = 0;
        foreach ($this->objectiveRows($export, $context) as $row) {
            hash_update($hash, "O\0".$this->canonical->encode($row)."\n");
            $objectives++;
            if (($row[5] ?? null) === 'Activo') {
                $active++;
            }
        }

        $relationships = 0;
        foreach ($this->sourceRows($export, $context) as $row) {
            hash_update($hash, "S\0".$this->canonical->encode($row)."\n");
            $relationships++;
        }

        return [
            'objective_count' => $objectives,
            'active_count' => $active,
            'relationship_count' => $relationships,
            'fingerprint' => hash_final($hash),
        ];
    }

    /** @param array<string, mixed> $context */
    private function contextHash(array $context): string
    {
        return $this->canonical->hash([
            'catalog_status' => $context['catalog_status'] ?? null,
            'catalogs' => collect($context['catalogs'] ?? [])->map(fn (array $catalog): array => [
                'public_id' => $catalog['public_id'] ?? null,
                'code' => $catalog['code'] ?? null,
                'version' => $catalog['version'] ?? null,
                'source_hash' => $catalog['source_hash'] ?? null,
                'active' => $catalog['active'] ?? null,
            ])->values()->all(),
            'activations' => collect($context['activations'] ?? [])->map(fn (array $activation): array => [
                'public_id' => $activation['public_id'] ?? null,
                'status' => $activation['status'] ?? null,
                'activation_version' => $activation['activation_version'] ?? null,
                'decision_hash' => $activation['decision_hash'] ?? null,
                'effective_from' => $activation['effective_from'] ?? null,
                'effective_to' => $activation['effective_to'] ?? null,
            ])->values()->all(),
            'latest_import' => array_intersect_key((array) ($context['latest_import'] ?? []), array_flip([
                'public_id', 'status', 'source_hash', 'manifest_hash', 'objective_count', 'completed_at',
            ])),
            'evidence' => $context['evidence'] ?? [],
        ]);
    }

    /** @param array<string, mixed> $context @return \Generator<int, list<mixed>> */
    private function objectiveRows(ReportExport $export, array $context): \Generator
    {
        $query = $this->scopedQuery($export->school, $export->book, $export->filters_snapshot ?? [], $context);
        $query->setEagerLoads([]);
        $query->select('lcd_learning_objectives.*')
            ->addSelect([
                'export_subject_code' => DB::table('schedule_subjects')->select('code')
                    ->whereColumn('schedule_subjects.id', 'lcd_learning_objectives.schedule_subject_id')->limit(1),
                'export_subject_name' => DB::table('schedule_subjects')->select('name')
                    ->whereColumn('schedule_subjects.id', 'lcd_learning_objectives.schedule_subject_id')->limit(1),
                'export_catalog_code' => DB::table('lcd_curriculum_catalogs')->select('code')
                    ->whereColumn('lcd_curriculum_catalogs.id', 'lcd_learning_objectives.curriculum_catalog_id')->limit(1),
                'export_catalog_version' => DB::table('lcd_curriculum_catalogs')->select('version')
                    ->whereColumn('lcd_curriculum_catalogs.id', 'lcd_learning_objectives.curriculum_catalog_id')->limit(1),
                'export_catalog_authority' => DB::table('lcd_curriculum_catalogs')->select('authority')
                    ->whereColumn('lcd_curriculum_catalogs.id', 'lcd_learning_objectives.curriculum_catalog_id')->limit(1),
                'export_catalog_hash' => DB::table('lcd_curriculum_catalogs')->select('source_hash')
                    ->whereColumn('lcd_curriculum_catalogs.id', 'lcd_learning_objectives.curriculum_catalog_id')->limit(1),
            ])
            ->withCount([
                'objectiveSources as export_source_count',
                'objectiveSources as export_canonical_count' => fn (Builder $relationship) => $relationship->where('source_role', 'canonical_text'),
                'objectiveSources as export_verified_count' => fn (Builder $relationship) => $relationship
                    ->whereHas('curriculumSource', fn (Builder $source) => $source
                        ->where('status', 'verified')
                        ->whereNotNull('declared_sha256')
                        ->whereColumn('declared_sha256', 'verified_sha256')),
            ]);

        foreach ($query->cursor() as $objective) {
            $sourceCount = (int) $objective->getAttribute('export_source_count');
            yield [
                (int) $objective->id,
                (string) $objective->public_id,
                (string) $objective->objective_key,
                (string) $objective->code,
                (string) $objective->objective_type,
                $objective->active ? 'Activo' : 'Inactivo',
                $objective->level_code,
                $objective->grade_code,
                $objective->curriculum_track ?: 'Común / sin track',
                $objective->getAttribute('export_subject_code') ?: 'GENERAL',
                $objective->getAttribute('export_subject_name') ?: 'Transversal / sin asignatura',
                $objective->axis_code,
                (string) $objective->description,
                $this->indicators($objective->indicators),
                $objective->unit_code,
                $objective->source_page,
                $objective->getAttribute('export_catalog_code'),
                $objective->getAttribute('export_catalog_version'),
                $objective->getAttribute('export_catalog_authority'),
                $objective->getAttribute('export_catalog_hash'),
                $sourceCount,
                (int) $objective->getAttribute('export_canonical_count'),
                (int) $objective->getAttribute('export_verified_count'),
                $sourceCount > 0 ? "Ver hoja Fuentes ({$sourceCount} relaciones)" : 'Sin fuente relacionada',
            ];
        }
    }

    /** @param array<string, mixed> $context @return \Generator<int, list<mixed>> */
    private function sourceRows(ReportExport $export, array $context): \Generator
    {
        $filtered = $this->scopedQuery($export->school, $export->book, $export->filters_snapshot ?? [], $context);
        $filtered->setEagerLoads([])->reorder()->select('lcd_learning_objectives.id');
        $evidence = $this->explorer->sourceEvidence($context);

        $rows = DB::table('lcd_learning_objective_sources as relationships')
            ->joinSub($filtered->toBase(), 'filtered_objectives', fn ($join) => $join
                ->on('filtered_objectives.id', '=', 'relationships.learning_objective_id'))
            ->join('lcd_learning_objectives as objectives', 'objectives.id', '=', 'relationships.learning_objective_id')
            ->leftJoin('schedule_subjects as subjects', 'subjects.id', '=', 'objectives.schedule_subject_id')
            ->join('lcd_curriculum_sources as sources', 'sources.id', '=', 'relationships.curriculum_source_id')
            ->orderByRaw('CASE WHEN objectives.grade_code = ? THEN 0 WHEN objectives.grade_code = ? THEN 1 WHEN objectives.grade_code LIKE ? THEN 2 ELSE 3 END', ['NT1', 'NT2', '%B'])
            ->orderBy('objectives.grade_code')
            ->orderBy('objectives.schedule_subject_id')
            ->orderBy('objectives.objective_type')
            ->orderBy('objectives.code')
            ->orderBy('objectives.id')
            ->orderBy('relationships.source_role')
            ->orderBy('relationships.curriculum_source_id')
            ->orderBy('relationships.id')
            ->select([
                'objectives.public_id as objective_public_id',
                'objectives.code as objective_code',
                'objectives.objective_key',
                'objectives.grade_code',
                'subjects.code as subject_code',
                'sources.public_id as source_public_id',
                'sources.source_key',
                'sources.source_scope',
                'sources.source_name',
                'sources.authority',
                'sources.document_number',
                'sources.source_url',
                'sources.status as source_status',
                'sources.declared_sha256',
                'sources.verified_sha256',
                'relationships.source_role',
                'relationships.source_locator',
                'relationships.relationship_hash',
            ])
            ->cursor();

        foreach ($rows as $row) {
            $matches = filled($row->declared_sha256)
                && filled($row->verified_sha256)
                && hash_equals(mb_strtolower((string) $row->declared_sha256), mb_strtolower((string) $row->verified_sha256));
            $evidenceKey = $row->source_key.'|'.mb_strtolower((string) $row->verified_sha256);
            $sourceEvidence = $evidence[$evidenceKey] ?? null;
            yield [
                $row->objective_public_id,
                $row->objective_code,
                $row->objective_key,
                $row->subject_code ?: 'GENERAL',
                $row->grade_code,
                $row->source_public_id,
                $row->source_key,
                $row->source_scope,
                $row->source_name,
                $row->authority,
                $row->document_number,
                $this->publicUrl($row->source_url),
                $row->source_role,
                $row->source_locator,
                $row->source_status,
                $row->declared_sha256,
                $row->verified_sha256,
                $matches ? 'Sí' : 'No',
                $row->relationship_hash,
                $sourceEvidence['status'] ?? 'missing',
                $sourceEvidence['verified_at'] ?? null,
                $sourceEvidence['sha256'] ?? null,
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $context
     */
    private function scopedQuery(School $school, ?Book $book, array $filters, array $context): Builder
    {
        $queryFilters = $filters;
        unset($queryFilters['academic_year_id'], $queryFilters['course_section_id'], $queryFilters['course_label']);

        $catalogIds = collect($context['catalog_ids'] ?? [])->map(fn ($id): int => (int) $id)->all();
        $scopes = [];
        if ($book) {
            [$scopes, $catalogIds] = $this->resolvedBookScopes($book, $filters, $catalogIds);
            // Book scope already incorporates the selected subject plus transversal
            // objectives; applying the global subject predicate would remove those.
            unset($queryFilters['schedule_subject_id'], $queryFilters['subject_code'], $queryFilters['subject']);
        }

        if (isset($filters['catalog_id']) && ! in_array((int) $filters['catalog_id'], $catalogIds, true)) {
            throw new LibroDigitalException(
                'El catálogo solicitado no pertenece al alcance curricular activado.',
                'LCD_CURRICULUM_EXPORT_CATALOG_SCOPE_INVALID',
                422,
            );
        }

        $request = Request::create('/curriculum/objectives', 'GET', $queryFilters);
        $query = $this->explorer->exportQuery($request, $catalogIds);
        if ($scopes !== []) {
            $query->where(function (Builder $outer) use ($scopes): void {
                foreach ($scopes as $scope) {
                    $outer->orWhere(function (Builder $item) use ($scope): void {
                        $item->whereIn('curriculum_catalog_id', $scope['catalog_ids'])
                            ->where('grade_code', $scope['grade_code'])
                            ->when(
                                $scope['curriculum_track'] === null,
                                fn (Builder $track) => $track->whereNull('curriculum_track'),
                                fn (Builder $track) => $track->where('curriculum_track', $scope['curriculum_track']),
                            )
                            ->where(fn (Builder $subject) => $subject
                                ->whereNull('schedule_subject_id')
                                ->orWhere('schedule_subject_id', $scope['subject_id']));
                    });
                }
            });
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<int>  $eligibleCatalogIds
     * @return array{0:list<array{subject_id:int,grade_code:string,curriculum_track:?string,catalog_ids:list<int>}>,1:list<int>}
     */
    private function resolvedBookScopes(Book $book, array $filters, array $eligibleCatalogIds): array
    {
        $groups = $this->bookScopes->eligibleTeachingGroups($book)->whereNotNull('schedule_subject_id');
        $subjectIds = $groups->pluck('schedule_subject_id')->map(fn ($id): int => (int) $id)->unique()->values();

        if (isset($filters['schedule_subject_id'])) {
            $subjectIds = $subjectIds->filter(fn (int $id): bool => $id === (int) $filters['schedule_subject_id'])->values();
        } elseif (isset($filters['subject_code'])) {
            $needle = mb_strtolower((string) $filters['subject_code']);
            $subjectIds = $groups->filter(fn ($group): bool => mb_strtolower((string) $group->subject?->code) === $needle)
                ->pluck('schedule_subject_id')->map(fn ($id): int => (int) $id)->unique()->values();
        } elseif (isset($filters['subject'])) {
            $needle = mb_strtolower((string) $filters['subject']);
            $subjectIds = $groups->filter(fn ($group): bool => in_array($needle, [
                mb_strtolower((string) $group->subject?->code),
                mb_strtolower((string) $group->subject?->name),
                (string) $group->subject?->id,
            ], true))->pluck('schedule_subject_id')->map(fn ($id): int => (int) $id)->unique()->values();
        }

        if ($subjectIds->isEmpty()) {
            throw new LibroDigitalException(
                'La asignatura solicitada no pertenece al libro seleccionado.',
                'LCD_CURRICULUM_EXPORT_BOOK_SUBJECT_INVALID',
                422,
            );
        }

        $scopes = $subjectIds->map(function (int $subjectId) use ($book, $filters, $eligibleCatalogIds): array {
            $scope = $this->bookScopes->resolve($book, $subjectId, isset($filters['catalog_id']) ? (int) $filters['catalog_id'] : null);

            return [
                'subject_id' => $subjectId,
                'grade_code' => $scope['grade_code'],
                'curriculum_track' => $scope['curriculum_track'],
                // A subject link is necessary but never sufficient: only the
                // corpus whose activation, import batch and SHA-256 passed the
                // explorer eligibility gate may enter a book export.
                'catalog_ids' => array_values(array_intersect($scope['catalog_ids'], $eligibleCatalogIds)),
            ];
        })->filter(fn (array $scope): bool => $scope['catalog_ids'] !== [])->values();
        if ($scopes->isEmpty()) {
            throw new LibroDigitalException(
                'El libro no posee vínculos curriculares oficiales activados para la asignatura solicitada.',
                'LCD_CURRICULUM_EXPORT_BOOK_SCOPE_INVALID',
                409,
            );
        }

        return [
            $scopes->all(),
            $scopes->pluck('catalog_ids')->flatten()->map(fn ($id): int => (int) $id)->unique()->values()->all(),
        ];
    }

    /** @return list<mixed> */
    private function objectiveRow(LearningObjective $objective): array
    {
        $relationships = $objective->objectiveSources;
        $verified = $relationships->filter(fn (LearningObjectiveSource $relationship): bool => $this->sourceHashMatches($relationship))->count();
        $sources = $relationships->map(fn (LearningObjectiveSource $relationship): string => implode(' · ', array_filter([
            $relationship->curriculumSource?->source_key,
            $relationship->source_role,
            $relationship->source_locator,
        ])))->implode("\n");

        return [
            $objective->id,
            $objective->public_id,
            $objective->objective_key,
            $objective->code,
            $objective->objective_type,
            $objective->active ? 'Activo' : 'Inactivo',
            $objective->level_code,
            $objective->grade_code,
            $objective->curriculum_track ?: 'Común / sin track',
            $objective->subject?->code ?: 'GENERAL',
            $objective->subject?->name ?: 'Transversal / sin asignatura',
            $objective->axis_code,
            $objective->description,
            $this->indicators($objective->indicators),
            $objective->unit_code,
            $objective->source_page,
            $objective->curriculumCatalog?->code,
            $objective->curriculumCatalog?->version,
            $objective->curriculumCatalog?->authority,
            $objective->curriculumCatalog?->source_hash,
            $relationships->count(),
            $relationships->where('source_role', 'canonical_text')->count(),
            $verified,
            $sources,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $evidence
     * @return list<mixed>
     */
    private function sourceRow(LearningObjective $objective, LearningObjectiveSource $relationship, array $evidence): array
    {
        $source = $relationship->curriculumSource;
        $evidenceKey = ($source?->source_key ?? '').'|'.mb_strtolower((string) $source?->verified_sha256);
        $sourceEvidence = $evidence[$evidenceKey] ?? null;

        return [
            $objective->public_id,
            $objective->code,
            $objective->objective_key,
            $objective->subject?->code ?: 'GENERAL',
            $objective->grade_code,
            $source?->public_id,
            $source?->source_key,
            $source?->source_scope,
            $source?->source_name,
            $source?->authority,
            $source?->document_number,
            $this->publicUrl($source?->source_url),
            $relationship->source_role,
            $relationship->source_locator,
            $source?->status,
            $source?->declared_sha256,
            $source?->verified_sha256,
            $this->sourceHashMatches($relationship) ? 'Sí' : 'No',
            $relationship->relationship_hash,
            $sourceEvidence['status'] ?? 'missing',
            $sourceEvidence['verified_at'] ?? null,
            $sourceEvidence['sha256'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function pdfObjective(LearningObjective $objective): array
    {
        return [
            'group' => implode(' · ', array_filter([
                $objective->level_code,
                $objective->grade_code,
                $objective->curriculum_track ?: 'Común',
                $objective->subject?->name ?: 'Transversal',
            ])),
            'code' => $objective->code,
            'type' => $objective->objective_type,
            'status' => $objective->active ? 'Activo' : 'Inactivo',
            'axis' => $objective->axis_code,
            'unit' => $objective->unit_code,
            'description' => $objective->description,
            'indicators' => $this->indicators($objective->indicators),
            'source_page' => $objective->source_page,
            'catalog' => implode(' · ', array_filter([
                $objective->curriculumCatalog?->code,
                $objective->curriculumCatalog?->version,
            ])),
            'sources' => $objective->objectiveSources
                ->sortBy(fn (LearningObjectiveSource $relationship): string => implode('|', [
                    $relationship->source_role,
                    str_pad((string) $relationship->curriculum_source_id, 20, '0', STR_PAD_LEFT),
                    str_pad((string) $relationship->id, 20, '0', STR_PAD_LEFT),
                ]))
                ->map(fn (LearningObjectiveSource $relationship): string => implode(' · ', array_filter([
                    $relationship->curriculumSource?->source_name,
                    $relationship->curriculumSource?->document_number,
                    $relationship->source_role,
                    $relationship->source_locator,
                    $this->sourceHashMatches($relationship) ? 'hash verificado' : 'hash pendiente',
                    filled($relationship->curriculumSource?->verified_sha256) ? 'SHA-256 '.$relationship->curriculumSource?->verified_sha256 : null,
                ])))->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function metadata(ReportExport $export, array $context, array $filters, int $count, int $active, int $relationships, string $contentHash): array
    {
        $timezone = (string) ($export->school?->timezone ?: config('libro_digital.timezone', 'America/Santiago'));
        $catalogs = collect($context['catalogs'] ?? []);
        $activations = collect($context['activations'] ?? []);

        return [
            'establecimiento' => $export->school?->legal_name ?: $export->school?->name,
            'RBD' => $export->school?->rbd,
            'periodo' => $export->academicYear?->name ?? (string) $export->academic_year_id,
            'año académico' => $export->academicYear?->name ?? (string) $export->academic_year_id,
            'tipo de reporte' => $export->title,
            'ID del reporte' => $export->public_id,
            'generado por' => $export->requester?->name ?? 'Sistema',
            'fecha' => now($timezone)->format('d-m-Y H:i:s'),
            'zona horaria' => $timezone,
            'filtros' => $this->filterSummary($filters, $export->book),
            'objetivos exportados' => $count,
            'objetivos activos' => $active,
            'objetivos inactivos' => $count - $active,
            'relaciones de fuente' => $relationships,
            'paginación' => "Exportación completa, sin paginación API: {$count} registros filtrados.",
            'catálogos activados' => $catalogs->map(fn (array $catalog): string => ($catalog['code'] ?? '-').' '.($catalog['version'] ?? '-'))->implode(' | '),
            'hashes de catálogo' => $catalogs->pluck('source_hash')->filter()->implode(' | '),
            'activaciones' => $activations->map(fn (array $activation): string => implode(':', array_filter([
                $activation['public_id'] ?? null,
                $activation['status'] ?? null,
                $activation['decision_hash'] ?? null,
            ])))->implode(' | '),
            'estado de importación' => (string) ($context['catalog_status'] ?? '-'),
            'último lote' => implode(' · ', array_filter([
                data_get($context, 'latest_import.public_id'),
                data_get($context, 'latest_import.status'),
                data_get($context, 'latest_import.manifest_hash'),
            ])),
            'evidencia oficial' => $this->canonical->encode($context['evidence'] ?? []),
            'SHA-256 del contenido curricular' => $contentHash,
            'integridad' => 'Instantánea fuente cifrada y sellada; el hash de instantánea y el hash final del archivo constan en el registro de exportación.',
        ];
    }

    /** @param array<string, mixed> $filters */
    private function filterSummary(array $filters, ?Book $book): string
    {
        $labels = [
            'course_label' => 'Curso',
            'level_code' => 'Nivel',
            'grade_code' => 'Grado',
            'curriculum_track' => 'Tipo de enseñanza',
            'subject_code' => 'Asignatura',
            'schedule_subject_id' => 'Asignatura ID',
            'objective_type' => 'Tipo de objetivo',
            'axis_code' => 'Eje',
            'status' => 'Estado',
            'source' => 'Fuente',
            'query' => 'Búsqueda',
            'catalog_id' => 'Catálogo ID',
        ];
        $parts = $book ? ['Libro '.$book->code] : [];
        foreach ($labels as $key => $label) {
            if (isset($filters[$key]) && $filters[$key] !== '') {
                $parts[] = $label.': '.$filters[$key];
            }
        }

        return $parts === [] ? 'Sin filtros adicionales' : implode(' · ', $parts);
    }

    /** @return list<string> */
    private function objectiveHeaders(): array
    {
        return [
            'ID interno', 'ID público', 'Clave objetiva SHA-256', 'Código', 'Tipo', 'Estado', 'Nivel', 'Grado',
            'Tipo de enseñanza', 'Código asignatura', 'Asignatura', 'Eje', 'Descripción oficial', 'Indicadores',
            'Unidad', 'Página / localizador', 'Código catálogo', 'Versión catálogo', 'Autoridad', 'Hash catálogo',
            'N° fuentes', 'Fuentes canónicas', 'Fuentes verificadas', 'Resumen de fuentes',
        ];
    }

    /** @return list<string> */
    private function sourceHeaders(): array
    {
        return [
            'ID público objetivo', 'Código objetivo', 'Clave objetiva SHA-256', 'Asignatura', 'Grado',
            'ID público fuente', 'Clave fuente', 'Alcance', 'Nombre fuente', 'Autoridad', 'Documento', 'URL pública',
            'Rol', 'Localizador', 'Estado fuente', 'SHA-256 declarado', 'SHA-256 verificado', 'Hashes coinciden',
            'Hash relación', 'Estado evidencia', 'Evidencia verificada', 'SHA-256 evidencia',
        ];
    }

    public static function maximumRows(): int
    {
        return min(self::XLSX_MAX_ROWS, max(1, (int) config('libro_digital.reports.curriculum_max_rows', self::XLSX_MAX_ROWS)));
    }

    /**
     * Shared read-only query for the book explorer. The controller prepares the
     * same normalized grade/course/status filters used by report requests; this
     * method then applies the exact eligible-catalog and active-group scope.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $context
     */
    public function explorerQuery(School $school, Book $book, array $filters, array $context): Builder
    {
        return $this->scopedQuery($school, $book, $filters, $context);
    }

    private function assertWithinFormatLimit(string $format, int $count): void
    {
        $limit = self::maximumRows();
        if ($count > $limit) {
            throw new LibroDigitalException(
                "La exportación contiene {$count} objetivos y supera el límite seguro de {$limit}; aplica más filtros.",
                'LCD_CURRICULUM_EXPORT_LIMIT_EXCEEDED',
                422,
                [['field' => 'filters', 'filtered_objectives' => $count, 'maximum' => $limit]],
            );
        }
        if ($format === 'pdf' && $count > self::PDF_MAX_ROWS) {
            throw new LibroDigitalException(
                'El PDF curricular admite hasta '.self::PDF_MAX_ROWS." objetivos y los filtros actuales seleccionan {$count}; acota nivel, grado o asignatura, o descarga el XLSX completo.",
                'LCD_CURRICULUM_EXPORT_PDF_LIMIT_EXCEEDED',
                422,
                [['field' => 'filters', 'filtered_objectives' => $count, 'maximum' => self::PDF_MAX_ROWS, 'alternative_format' => 'xlsx']],
            );
        }
    }

    private function indicators(mixed $indicators): string
    {
        if (! is_array($indicators)) {
            return trim((string) $indicators);
        }

        return collect($indicators)->map(function ($value, $key): string {
            if (is_array($value)) {
                $value = $this->canonical->encode($value);
            }

            return is_int($key) ? (string) $value : $key.': '.$value;
        })->implode("\n");
    }

    private function sourceHashMatches(LearningObjectiveSource $relationship): bool
    {
        $source = $relationship->curriculumSource;

        return $source
            && filled($source->declared_sha256)
            && filled($source->verified_sha256)
            && hash_equals(mb_strtolower((string) $source->declared_sha256), mb_strtolower((string) $source->verified_sha256));
    }

    private function publicUrl(mixed $value): ?string
    {
        $url = trim((string) $value);
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return in_array(mb_strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true) ? $url : null;
    }
}
