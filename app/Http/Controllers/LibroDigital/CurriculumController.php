<?php

namespace App\Http\Controllers\LibroDigital;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Http\Requests\LibroDigital\CurriculumObjectiveVisualizationRequest;
use App\Http\Requests\LibroDigital\ListCurriculumObjectivesRequest;
use App\Http\Requests\LibroDigital\ShowCurriculumObjectiveRequest;
use App\Http\Resources\LibroDigital\CurriculumObjectiveDetailResource;
use App\Http\Resources\LibroDigital\CurriculumObjectiveResource;
use App\Models\LibroDigital\LearningObjective;
use App\Models\LibroDigital\School;
use App\Services\LibroDigital\CurriculumCourseScopeResolver;
use App\Services\LibroDigital\CurriculumExplorerService;
use App\Services\LibroDigital\CurriculumObjectiveReportService;
use App\Services\LibroDigital\CurriculumObjectiveVisualizationService;
use App\Services\LibroDigital\LibroDigitalAccessContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CurriculumController extends LibroDigitalController
{
    public function __construct(
        LibroDigitalAccessContext $access,
        private readonly CurriculumExplorerService $explorer,
        private readonly CurriculumCourseScopeResolver $courseScopes,
        private readonly CurriculumObjectiveReportService $curriculumReports,
        private readonly CurriculumObjectiveVisualizationService $curriculumVisualizations,
    ) {
        parent::__construct($access);
    }

    public function objectives(ListCurriculumObjectivesRequest $request): JsonResponse
    {
        if (! $request->filled('book_id')) {
            return $this->explorerObjectives($request);
        }

        return $this->bookObjectives($request);
    }

    public function visualization(CurriculumObjectiveVisualizationRequest $request): JsonResponse
    {
        $school = $this->explorerSchool($request);
        $book = null;
        if ($request->filled('book_id')) {
            $book = $this->book($request->input('book_id'));
            if ((int) $book->school_id !== (int) $school->id
                || (int) $book->academic_year_id !== $request->integer('academic_year_id')) {
                throw new LibroDigitalException(
                    'El libro no pertenece al contexto académico seleccionado.',
                    'LCD_CURRICULUM_SCOPE_INVALID',
                    403,
                );
            }
            $status = $request->string('status', 'active')->lower()->toString();
            if ($status !== 'active') {
                throw new LibroDigitalException(
                    'La vista asociada a un libro solo incluye objetivos activos y seleccionables.',
                    'LCD_CURRICULUM_EXPORT_BOOK_STATUS_INVALID',
                    422,
                    [['field' => 'status', 'allowed' => ['active']]],
                );
            }
            $request->merge($this->courseScopes->apply(
                $school,
                $request->integer('academic_year_id'),
                [...$request->all(), 'status' => 'active'],
                $book,
            ));
        } elseif ($request->filled('course_section_id')) {
            $request->merge($this->courseScopes->apply(
                $school,
                $request->integer('academic_year_id'),
                $request->all(),
            ));
        }

        $context = $this->explorer->context($school, $request->integer('academic_year_id'));
        if (($context['catalog_ids'] ?? []) === []) {
            return response()->json($this->curriculumVisualizations->empty($request, $context));
        }

        $scope = $request->string('scope', 'filtered')->lower()->toString();
        if ($book) {
            $filters = $request->only([
                'academic_year_id', 'course_section_id', 'course_label', 'level_code', 'grade_code', 'curriculum_track',
                'schedule_subject_id', 'subject_code', 'subject', 'catalog_id', 'objective_type', 'axis_code', 'status', 'source', 'query',
            ]);
            if ($scope === 'catalog') {
                $filters = array_intersect_key($filters, array_flip([
                    'academic_year_id', 'course_section_id', 'course_label', 'level_code', 'grade_code', 'curriculum_track',
                    'schedule_subject_id', 'subject_code', 'subject', 'status',
                ]));
            }
            $query = $this->curriculumReports->explorerQuery($school, $book, $filters, $context);
        } elseif ($scope === 'catalog') {
            $catalogRequest = Request::create('/curriculum/objectives/visualization', 'GET', ['status' => 'all']);
            $query = $this->explorer->query($catalogRequest, $context['catalog_ids']);
        } else {
            $query = $this->explorer->query($request, $context['catalog_ids']);
        }

        return response()->json($this->curriculumVisualizations->build(
            $query,
            $request,
            $context,
            $book,
        ));
    }

    private function bookObjectives(ListCurriculumObjectivesRequest $request): JsonResponse
    {
        $school = $this->explorerSchool($request);
        $book = $this->book($request->input('book_id'));
        if ((int) $book->school_id !== (int) $school->id || (int) $book->academic_year_id !== $request->integer('academic_year_id')) {
            throw new LibroDigitalException('El libro no pertenece al contexto académico seleccionado.', 'LCD_CURRICULUM_SCOPE_INVALID', 403);
        }
        $status = $request->string('status', 'active')->lower()->toString();
        if ($status !== 'active') {
            throw new LibroDigitalException(
                'La vista asociada a un libro solo incluye objetivos activos y seleccionables.',
                'LCD_CURRICULUM_EXPORT_BOOK_STATUS_INVALID',
                422,
                [['field' => 'status', 'allowed' => ['active']]],
            );
        }
        $request->merge($this->courseScopes->apply(
            $school,
            $request->integer('academic_year_id'),
            [...$request->all(), 'status' => 'active'],
            $book,
        ));

        $context = $this->explorer->context($school, $request->integer('academic_year_id'));
        if ($context['catalog_ids'] === []) {
            $summary = $this->emptyExplorerSummary();

            return $this->collectionResponse([], [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $request->integer('per_page', 50),
                'total' => 0,
                'summary' => $summary,
                'kpis' => $summary,
                'facets' => $this->explorer->facets([]),
                'facets_scope' => 'book_active_scope',
                'context' => $context,
                'export_limits' => $this->exportLimits(0),
                'compliance_blocker' => $context['compliance_blocker'],
            ]);
        }

        $filters = $request->only([
            'academic_year_id', 'course_section_id', 'course_label', 'level_code', 'grade_code', 'curriculum_track',
            'schedule_subject_id', 'subject_code', 'subject', 'catalog_id', 'objective_type', 'axis_code', 'status', 'source', 'query',
        ]);
        $scopeFilters = array_intersect_key($filters, array_flip([
            'academic_year_id', 'course_section_id', 'course_label', 'level_code', 'grade_code', 'curriculum_track',
            'schedule_subject_id', 'subject_code', 'subject', 'status',
        ]));
        $scopeQuery = $this->curriculumReports->explorerQuery($school, $book, $scopeFilters, $context);
        $filteredQuery = $this->curriculumReports->explorerQuery($school, $book, $filters, $context);
        $summary = $this->explorer->summaryForQueries($scopeQuery, $filteredQuery);
        $facets = $this->explorer->facetsForQuery($scopeQuery);
        $objectives = (clone $filteredQuery)->paginate($request->integer('per_page', 50));

        return $this->collectionResponse(
            $objectives->getCollection()
                ->map(fn ($objective): array => (new CurriculumObjectiveResource($objective))->resolve($request))
                ->all(),
            [
                'current_page' => $objectives->currentPage(),
                'last_page' => $objectives->lastPage(),
                'per_page' => $objectives->perPage(),
                'total' => $objectives->total(),
                'from' => $objectives->firstItem(),
                'to' => $objectives->lastItem(),
                'summary' => $summary,
                'kpis' => $summary,
                'facets' => $facets,
                'facets_scope' => 'book_active_scope',
                'context' => $context,
                'export_limits' => $this->exportLimits((int) $summary['filtered_objectives']),
                'compliance_blocker' => null,
            ],
        );
    }

    public function show(ShowCurriculumObjectiveRequest $request, string $objective): JsonResponse
    {
        $school = $this->explorerSchool($request);
        $context = $this->explorer->context($school, $request->integer('academic_year_id'));
        $catalogIds = $context['catalog_ids'];
        if ($catalogIds === []) {
            return response()->json([
                'data' => null,
                'meta' => [
                    'context' => $context,
                    'compliance_blocker' => $context['compliance_blocker'],
                ],
            ]);
        }

        $model = $this->explorer->find($objective, $catalogIds);
        if (! $model) {
            throw new LibroDigitalException(
                'El objetivo no pertenece al catálogo curricular activo del contexto seleccionado.',
                'LCD_CURRICULUM_OBJECTIVE_NOT_AVAILABLE',
                404,
            );
        }

        $payload = (new CurriculumObjectiveDetailResource($model))->resolve($request);
        $evidence = $this->explorer->sourceEvidence($context);
        $payload['sources'] = collect($payload['sources'] ?? [])->map(function (array $source) use ($evidence): array {
            $evidenceKey = ($source['source_key'] ?? '').'|'.mb_strtolower((string) ($source['verified_sha256'] ?? ''));
            $source['evidence'] = $evidence[$evidenceKey] ?? [
                'status' => 'missing',
                'message' => 'No se encontró evidencia oficial archivada para esta fuente en la activación seleccionada.',
            ];

            return $source;
        })->all();
        $payload['import_state'] = [
            'catalog_status' => $context['catalog_status'],
            'latest_import' => $context['latest_import'],
            'activations' => $context['activations'],
            'evidence' => $context['evidence'],
        ];

        return response()->json([
            'data' => $payload,
            'meta' => [
                'context' => $context,
                'compliance_blocker' => $context['compliance_blocker'],
            ],
        ]);
    }

    private function explorerObjectives(ListCurriculumObjectivesRequest $request): JsonResponse
    {
        $school = $this->explorerSchool($request);
        if ($request->filled('course_section_id')) {
            $request->merge($this->courseScopes->apply(
                $school,
                $request->integer('academic_year_id'),
                $request->all(),
            ));
        }
        $context = $this->explorer->context($school, $request->integer('academic_year_id'));
        $catalogIds = $context['catalog_ids'];
        if ($catalogIds === []) {
            $summary = $this->emptyExplorerSummary();

            return $this->collectionResponse([], [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $request->integer('per_page', 50),
                'total' => 0,
                'summary' => $summary,
                'kpis' => $summary,
                'facets' => $this->explorer->facets([]),
                'facets_scope' => 'active_catalogs',
                'context' => $context,
                'export_limits' => $this->exportLimits((int) $summary['filtered_objectives']),
                'compliance_blocker' => $context['compliance_blocker'],
            ]);
        }

        $objectives = $this->explorer->paginate($request, $catalogIds);
        $summary = $this->explorer->summary($request, $catalogIds);

        return $this->collectionResponse(
            $objectives->getCollection()
                ->map(fn (LearningObjective $objective): array => (new CurriculumObjectiveResource($objective))->resolve($request))
                ->all(),
            [
                'current_page' => $objectives->currentPage(),
                'last_page' => $objectives->lastPage(),
                'per_page' => $objectives->perPage(),
                'total' => $objectives->total(),
                'from' => $objectives->firstItem(),
                'to' => $objectives->lastItem(),
                'summary' => $summary,
                'kpis' => $summary,
                'facets' => $this->explorer->facets($catalogIds),
                'facets_scope' => 'active_catalogs',
                'context' => $context,
                'export_limits' => $this->exportLimits((int) $summary['filtered_objectives']),
                'compliance_blocker' => $context['compliance_blocker'],
            ],
        );
    }

    /** @return array<string, mixed> */
    private function exportLimits(int $filteredObjectives): array
    {
        $xlsxMaximum = CurriculumObjectiveReportService::maximumRows();

        return [
            'filtered_objectives' => $filteredObjectives,
            'pdf' => [
                'max_rows' => CurriculumObjectiveReportService::PDF_MAX_ROWS,
                'allowed' => $filteredObjectives <= CurriculumObjectiveReportService::PDF_MAX_ROWS,
            ],
            'xlsx' => [
                'max_rows' => $xlsxMaximum,
                'allowed' => $filteredObjectives <= $xlsxMaximum,
            ],
            'recommended_format' => $filteredObjectives > CurriculumObjectiveReportService::PDF_MAX_ROWS ? 'xlsx' : 'pdf',
        ];
    }

    /** @return array<string, int> */
    private function emptyExplorerSummary(): array
    {
        return [
            'total_objectives' => 0,
            'filtered_objectives' => 0,
            'active_objectives' => 0,
            'inactive_objectives' => 0,
            'objectives_with_sources' => 0,
            'objectives_without_sources' => 0,
            'objectives_with_canonical_source' => 0,
            'objectives_missing_canonical_source' => 0,
            'verified_sources' => 0,
        ];
    }

    private function explorerSchool(Request $request): School
    {
        try {
            return $this->school($request);
        } catch (ValidationException) {
            abort(403, 'No tienes acceso vigente al establecimiento solicitado.');
        }
    }

    public function coverage(Request $request, string $book): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('libro_digital.books.view') || $request->user()?->hasPermission('libro_digital.lesson.manage'), 403);
        $aggregate = $this->book($book);
        $school = $this->school($request, $aggregate);
        if ((int) $aggregate->school_id !== (int) $school->id) {
            abort(403);
        }

        $objectiveRows = DB::table('lcd_session_objectives as session_objectives')
            ->join('lcd_class_sessions as sessions', 'sessions.id', '=', 'session_objectives.class_session_id')
            ->leftJoin('lcd_learning_objectives as objectives', 'objectives.id', '=', 'session_objectives.learning_objective_id')
            ->where('sessions.book_id', $aggregate->id)
            ->selectRaw('session_objectives.learning_objective_id, COALESCE(objectives.code, session_objectives.objective_code_snapshot) as objective_code, COALESCE(objectives.description, session_objectives.objective_description_snapshot) as objective_description, objectives.objective_type, COUNT(DISTINCT sessions.id) as sessions_count, MAX(session_objectives.progress_percent) as maximum_coverage')
            ->groupBy(
                'session_objectives.learning_objective_id',
                'objectives.code',
                'session_objectives.objective_code_snapshot',
                'objectives.description',
                'session_objectives.objective_description_snapshot',
                'objectives.objective_type',
            )
            ->orderBy('objectives.code')->get();

        $treated = $objectiveRows->whereNotNull('learning_objective_id')->count();
        $linkedCatalogIds = DB::table('lcd_subject_curriculum_links as links')
            ->join('lcd_curriculum_catalogs as catalogs', 'catalogs.id', '=', 'links.curriculum_catalog_id')
            ->join('lcd_curriculum_catalog_activations as activations', function ($join) use ($aggregate): void {
                $join->on('activations.curriculum_catalog_id', '=', 'links.curriculum_catalog_id')
                    ->where('activations.school_id', $aggregate->school_id)
                    ->where('activations.academic_year_id', $aggregate->academic_year_id)
                    ->where('activations.status', 'activated');
            })
            ->where('links.school_id', $aggregate->school_id)
            ->where('links.academic_year_id', $aggregate->academic_year_id)
            ->whereIn('links.schedule_subject_id', $aggregate->teachingGroups()->whereNotNull('schedule_subject_id')->pluck('schedule_subject_id'))
            ->where('links.active', true)
            ->where('catalogs.active', true)
            ->whereNotNull('catalogs.source_hash')
            ->where(fn ($query) => $query->whereNull('links.valid_from')->orWhereDate('links.valid_from', '<=', now()->toDateString()))
            ->where(fn ($query) => $query->whereNull('links.valid_to')->orWhereDate('links.valid_to', '>=', now()->toDateString()))
            ->distinct()->pluck('links.curriculum_catalog_id');
        $expected = DB::table('lcd_learning_objectives')->whereIn('curriculum_catalog_id', $linkedCatalogIds)->where('active', true)->count();

        return $this->dataResponse([
            'book_id' => $aggregate->id,
            'expected_objectives' => $expected,
            'treated_objectives' => $treated,
            'coverage_percentage' => $expected > 0 ? round(($treated / $expected) * 100, 2) : null,
            'objectives' => $objectiveRows->map(fn ($row): array => [
                'id' => $row->learning_objective_id,
                'code' => $row->objective_code ?: 'OBJETIVO NO CATALOGADO',
                'description' => $row->objective_description,
                'objective_type' => $row->objective_type,
                'sessions_count' => (int) $row->sessions_count,
                'maximum_coverage' => $row->maximum_coverage === null ? null : (float) $row->maximum_coverage,
            ])->all(),
            'compliance_blocker' => $expected === 0 ? [
                'code' => 'COMPLIANCE_BLOCKER_CURRICULUM_NOT_IMPORTED',
                'message' => 'La cobertura no puede certificarse sin un catálogo curricular oficial versionado y hasheado.',
            ] : null,
        ]);
    }
}
