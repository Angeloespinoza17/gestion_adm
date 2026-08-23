<?php

namespace App\Http\Controllers\LibroDigital;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Http\Requests\LibroDigital\StoreAssessmentRequest;
use App\Http\Requests\LibroDigital\UpdateAssessmentRequest;
use App\Http\Requests\LibroDigital\UpdateAssessmentResultsRequest;
use App\Models\LibroDigital\Assessment;
use App\Models\LibroDigital\AssessmentPeriod;
use App\Models\LibroDigital\Book;
use App\Models\LibroDigital\EnrollmentLink;
use App\Models\LibroDigital\GradeClosure;
use App\Models\LibroDigital\GradingScheme;
use App\Models\LibroDigital\RecordRevision;
use App\Models\LibroDigital\StudentResult;
use App\Models\LibroDigital\TeacherAssignment;
use App\Models\LibroDigital\TeachingGroup;
use App\Services\LibroDigital\AuditEventWriter;
use App\Services\LibroDigital\CanonicalJson;
use App\Services\LibroDigital\CurriculumObjectiveScopeService;
use App\Services\LibroDigital\Curriculum\CurriculumProgramScopeService;
use App\Services\LibroDigital\LibroDigitalAccessContext;
use App\Services\LibroDigital\OptimisticLock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssessmentController extends LibroDigitalController
{
    public function __construct(
        LibroDigitalAccessContext $access,
        private readonly OptimisticLock $locks,
        private readonly CanonicalJson $canonical,
        private readonly AuditEventWriter $audit,
        private readonly CurriculumObjectiveScopeService $curriculumScope,
        private readonly CurriculumProgramScopeService $programScope,
    ) {
        parent::__construct($access);
    }

    public function index(Request $request, string $book): JsonResponse
    {
        $bookModel = $this->book($book);
        $this->authorize('view', $bookModel);
        $paginator = Assessment::query()->where('book_id', $bookModel->id)
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->input('status')))
            ->with(['period', 'gradingScheme', 'subject', 'teacherAssignment.staff'])
            ->withCount('results')->orderByDesc('assessment_date')->orderByDesc('id')
            ->paginate(min(200, max(1, $request->integer('per_page', 50))));

        $expected = $this->expectedResultsCount($bookModel);

        return $this->collectionResponse(
            $paginator->getCollection()->map(fn (Assessment $assessment): array => $this->payload($assessment, $expected))->all(),
            ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total()],
        );
    }

    public function show(Request $request, string $assessment): JsonResponse
    {
        $model = $this->assessment($assessment);
        $this->assertMayManage($request, $model);
        $model->load(['period', 'gradingScheme', 'subject', 'teacherAssignment.staff', 'results.student'])->loadCount('results');

        return $this->dataResponse($this->payload($model, $this->expectedResultsCount($model->book), true), version: $model->lock_version);
    }

    public function store(StoreAssessmentRequest $request, string $book): JsonResponse
    {
        $bookModel = $this->book($book);
        $this->authorize('view', $bookModel);
        $this->assertBookOpen($bookModel);
        $data = $request->validated();
        $group = $bookModel->teachingGroups()->firstOrFail();
        $assignment = $this->assignmentFor($request, $bookModel, $group, $data['scheduled_on']);
        $year = $bookModel->academicYear()->firstOrFail();
        if ($data['scheduled_on'] < $year->starts_at->format('Y-m-d') || $data['scheduled_on'] > $year->ends_at->format('Y-m-d')) {
            throw new LibroDigitalException('La evaluación debe quedar dentro del año académico del libro.', 'LCD_ASSESSMENT_DATE_OUTSIDE_YEAR');
        }
        $programContext = $this->programScope->resolve(
            $bookModel,
            (int) $group->schedule_subject_id,
            $data['curriculum_program_id'] ?? null,
            $data['curriculum_unit_id'] ?? null,
            objectiveIds: array_values($data['curriculum_objective_ids'] ?? []),
        );

        $assessment = DB::transaction(function () use ($request, $bookModel, $group, $assignment, $year, $data, $programContext): Assessment {
            $scheme = GradingScheme::query()->firstOrCreate(
                ['school_id' => $bookModel->school_id, 'code' => 'CL_1_7', 'version' => '1'],
                [
                    'regulatory_profile_id' => $bookModel->regulatory_profile_id,
                    'name' => 'Escala chilena 1,0 a 7,0',
                    'scale_type' => 'numeric',
                    'minimum_value' => 1,
                    'maximum_value' => 7,
                    'passing_value' => 4,
                    'decimal_places' => 1,
                    'rounding_mode' => 'half_up',
                    'rules' => ['input_contract' => '1_to_7'],
                    'valid_from' => $year->starts_at,
                    'valid_to' => $year->ends_at,
                    'active' => true,
                ],
            );
            if (! $scheme->active) {
                throw new LibroDigitalException('La escala de calificación institucional no está activa.', 'LCD_GRADING_SCHEME_INACTIVE', 409);
            }
            $period = AssessmentPeriod::query()->firstOrCreate(
                ['book_id' => $bookModel->id, 'code' => 'ANNUAL'],
                [
                    'school_id' => $bookModel->school_id,
                    'academic_year_id' => $bookModel->academic_year_id,
                    'name' => 'Año académico '.$year->year,
                    'type' => 'academic_year',
                    'starts_on' => $year->starts_at,
                    'ends_on' => $year->ends_at,
                    'weight' => 100,
                    'status' => 'open',
                ],
            );
            $objectiveIds = array_values($data['curriculum_objective_ids'] ?? []);
            $assessment = Assessment::query()->create([
                'school_id' => $bookModel->school_id,
                'book_id' => $bookModel->id,
                'teaching_group_id' => $group->id,
                'assessment_period_id' => $period->id,
                'grading_scheme_id' => $scheme->id,
                'schedule_subject_id' => $group->schedule_subject_id,
                'teacher_assignment_id' => $assignment->id,
                'curriculum_program_id' => $programContext['program']?->id,
                'curriculum_unit_id' => $programContext['unit']?->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'assessment_type' => $data['assessment_type'],
                'assessment_date' => $data['scheduled_on'],
                'weight' => $data['weighting'] ?? 0,
                'maximum_score' => $data['maximum_score'] ?? null,
                'instrument_metadata' => ['grading_scale' => $data['grading_scale'], 'curriculum_objective_ids' => $objectiveIds],
                'status' => 'draft',
                'revision' => 1,
                'lock_version' => 1,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
            $this->appendObjectives($assessment, $objectiveIds);

            return $assessment;
        }, 3);

        $this->audit->write('lcd.assessment.created', 'create', $assessment, actor: $request->user(), schoolId: $assessment->school_id, academicYearId: $bookModel->academic_year_id, after: $assessment->toArray(), request: $request);

        return $this->assessmentResponse($request, $assessment, 201);
    }

    public function update(UpdateAssessmentRequest $request, string $assessment): JsonResponse
    {
        $model = $this->assessment($assessment);
        $this->assertMayManage($request, $model);
        $this->locks->assert($model, $request);
        $this->assertBookOpen($model->book);
        if (in_array($this->statusValue($model->status), ['closed', 'amended', 'cancelled'], true)) {
            throw new LibroDigitalException('La evaluación cerrada no admite edición directa; utiliza una enmienda.', 'LCD_ASSESSMENT_IMMUTABLE', 409);
        }
        $data = $request->validated();
        $before = $model->toArray();
        if (isset($data['scheduled_on'])) {
            $year = $model->book->academicYear()->firstOrFail();
            if ($data['scheduled_on'] < $year->starts_at->format('Y-m-d') || $data['scheduled_on'] > $year->ends_at->format('Y-m-d')) {
                throw new LibroDigitalException('La evaluación debe quedar dentro del año académico del libro.', 'LCD_ASSESSMENT_DATE_OUTSIDE_YEAR');
            }
        }
        $programContext = $this->programScope->resolve(
            $model->book,
            (int) $model->schedule_subject_id,
            array_key_exists('curriculum_program_id', $data) ? $data['curriculum_program_id'] : $model->curriculum_program_id,
            array_key_exists('curriculum_unit_id', $data) ? $data['curriculum_unit_id'] : $model->curriculum_unit_id,
            objectiveIds: array_values($data['curriculum_objective_ids'] ?? data_get($model->instrument_metadata, 'curriculum_objective_ids', [])),
        );

        DB::transaction(function () use ($request, $model, $data, $programContext): void {
            $locked = Assessment::query()->lockForUpdate()->findOrFail($model->id);
            $this->locks->assert($locked, $request);
            $metadata = $locked->instrument_metadata ?? [];
            if (array_key_exists('grading_scale', $data)) {
                $metadata['grading_scale'] = $data['grading_scale'];
            }
            if (array_key_exists('curriculum_objective_ids', $data)) {
                $metadata['curriculum_objective_ids'] = array_values($data['curriculum_objective_ids']);
                $this->appendObjectives($locked, $metadata['curriculum_objective_ids']);
            }
            $locked->forceFill([
                'name' => $data['name'] ?? $locked->name,
                'description' => array_key_exists('description', $data) ? $data['description'] : $locked->description,
                'assessment_type' => $data['assessment_type'] ?? $locked->assessment_type,
                'assessment_date' => $data['scheduled_on'] ?? $locked->assessment_date,
                'weight' => array_key_exists('weighting', $data) ? ($data['weighting'] ?? 0) : $locked->weight,
                'maximum_score' => array_key_exists('maximum_score', $data) ? $data['maximum_score'] : $locked->maximum_score,
                'instrument_metadata' => $metadata,
                'curriculum_program_id' => $programContext['program']?->id,
                'curriculum_unit_id' => $programContext['unit']?->id,
                'revision' => $locked->revision + 1,
                'lock_version' => $locked->lock_version + 1,
                'updated_by' => $request->user()->id,
            ])->save();
        }, 3);

        $fresh = $model->fresh();
        $this->audit->write('lcd.assessment.updated', 'update', $fresh, actor: $request->user(), schoolId: $fresh->school_id, academicYearId: $fresh->book->academic_year_id, before: $before, after: $fresh->toArray(), request: $request, entityRevision: $fresh->revision);

        return $this->assessmentResponse($request, $fresh);
    }

    public function results(UpdateAssessmentResultsRequest $request, string $assessment): JsonResponse
    {
        $model = $this->assessment($assessment);
        $this->assertMayManage($request, $model);
        $this->locks->assert($model, $request);
        $this->assertBookOpen($model->book);
        if (in_array($this->statusValue($model->status), ['closed', 'amended', 'cancelled'], true)) {
            throw new LibroDigitalException('Los resultados cerrados solo se corrigen mediante enmienda.', 'LCD_ASSESSMENT_RESULTS_IMMUTABLE', 409);
        }

        DB::transaction(function () use ($request, $model): void {
            $locked = Assessment::query()->lockForUpdate()->findOrFail($model->id);
            $this->locks->assert($locked, $request);
            foreach ($request->validated('results') as $row) {
                $absent = (bool) ($row['absent'] ?? false);
                $exempt = (bool) ($row['exempt'] ?? false);
                if (! $absent && ! $exempt && ! isset($row['raw_score']) && ! isset($row['numeric_value']) && ! filled($row['qualitative_value'] ?? null)) {
                    throw new LibroDigitalException('Cada resultado debe incluir una calificación o marcar ausencia/eximición.', 'LCD_ASSESSMENT_RESULT_VALUE_REQUIRED');
                }
                if (isset($row['raw_score']) && $locked->maximum_score !== null && (float) $row['raw_score'] > (float) $locked->maximum_score) {
                    throw new LibroDigitalException('Un puntaje supera el máximo configurado para la evaluación.', 'LCD_ASSESSMENT_SCORE_EXCEEDS_MAXIMUM');
                }
                $link = $this->enrollmentLink($locked->book_id, (int) $row['student_profile_id'], $locked->assessment_date->format('Y-m-d'));
                $result = StudentResult::query()->lockForUpdate()->firstOrNew([
                    'assessment_id' => $locked->id,
                    'student_profile_id' => $link->student_profile_id,
                ]);
                $revision = $result->exists ? ((int) $result->revision) + 1 : 1;
                $status = $absent ? 'absent' : ($exempt ? 'exempt' : 'recorded');
                $normalized = isset($row['raw_score']) && (float) $locked->maximum_score > 0
                    ? round(((float) $row['raw_score'] / (float) $locked->maximum_score) * 100, 4)
                    : null;
                $record = [
                    'status' => $status,
                    'raw_score' => $row['raw_score'] ?? null,
                    'numeric_value' => $row['numeric_value'] ?? null,
                    'qualitative_value' => $row['qualitative_value'] ?? null,
                    'normalized_percentage' => $normalized,
                    'absent' => $absent,
                    'exempt' => $exempt,
                    'observation' => $row['observation'] ?? null,
                    'revision' => $revision,
                    'recorded_by' => $request->user()->id,
                    'recorded_at' => now('UTC'),
                ];
                $result->forceFill([
                    ...$record,
                    'student_enrollment_id' => $link->student_enrollment_id,
                    'enrollment_link_id' => $link->id,
                    // Una corrección explícita desde Libro Digital prevalece sobre futuras recargas masivas.
                    'annual_grade_import_id' => null,
                    'annual_grade_import_cell_id' => null,
                    'record_hash' => $this->canonical->hash([
                        'assessment_public_id' => $locked->public_id,
                        'student_profile_id' => $link->student_profile_id,
                        ...$record,
                    ]),
                ])->save();
                $this->recordRevision($result, $locked->school_id, $revision, $request->user()->id);
            }
            $locked->forceFill([
                'status' => 'results_open',
                'revision' => $locked->revision + 1,
                'lock_version' => $locked->lock_version + 1,
                'updated_by' => $request->user()->id,
            ])->save();
        }, 3);

        $fresh = $model->fresh();
        $this->audit->write('lcd.assessment.results_saved', 'record_results', $fresh, actor: $request->user(), schoolId: $fresh->school_id, academicYearId: $fresh->book->academic_year_id, after: ['results_count' => $fresh->results()->count()], request: $request, entityRevision: $fresh->revision);

        return $this->assessmentResponse($request, $fresh, includeResults: true);
    }

    public function close(Request $request, string $assessment): JsonResponse
    {
        $request->validate(['lock_version' => ['required', 'integer', 'min:1']]);
        $model = $this->assessment($assessment);
        $this->assertMayManage($request, $model);
        $this->locks->assert($model, $request);
        $this->assertBookOpen($model->book);
        if ($this->statusValue($model->status) === 'closed') {
            throw new LibroDigitalException('La evaluación ya está cerrada.', 'LCD_ASSESSMENT_ALREADY_CLOSED', 409);
        }

        DB::transaction(function () use ($request, $model): void {
            $locked = Assessment::query()->lockForUpdate()->findOrFail($model->id);
            $this->locks->assert($locked, $request);
            $links = $this->expectedLinks($locked->book_id, $locked->assessment_date->format('Y-m-d'));
            if ($links->isEmpty()) {
                throw new LibroDigitalException('No existe una nómina vigente que permita cerrar resultados.', 'LCD_ASSESSMENT_ROSTER_REQUIRED');
            }
            $results = StudentResult::query()->where('assessment_id', $locked->id)
                ->whereIn('student_profile_id', $links->pluck('student_profile_id'))->orderBy('student_profile_id')->get();
            $validResults = $results->whereIn('status', ['recorded', 'absent', 'exempt']);
            $recorded = $validResults->pluck('student_profile_id')->map(fn ($id) => (int) $id);
            $missing = $links->reject(fn (EnrollmentLink $link) => $recorded->contains((int) $link->student_profile_id));
            if ($missing->isNotEmpty()) {
                throw new LibroDigitalException(
                    'No se puede cerrar: faltan resultados de la nómina vigente.',
                    'LCD_ASSESSMENT_RESULTS_INCOMPLETE',
                    422,
                    $missing->map(fn (EnrollmentLink $link): array => ['field' => 'results', 'reason' => 'Falta resultado para '.$link->student_name_snapshot, 'student_profile_id' => $link->student_profile_id])->values()->all(),
                );
            }
            $snapshot = [
                'assessment_id' => $locked->id,
                'assessment_public_id' => $locked->public_id,
                'assessment_revision' => $locked->revision + 1,
                'roster_count' => $links->count(),
                'results' => $validResults->map(fn (StudentResult $result): array => [
                    'student_profile_id' => $result->student_profile_id,
                    'status' => $result->status,
                    'raw_score' => $result->raw_score,
                    'numeric_value' => $result->numeric_value,
                    'qualitative_value' => $result->qualitative_value,
                    'absent' => $result->absent,
                    'exempt' => $result->exempt,
                    'revision' => $result->revision,
                    'record_hash' => $result->record_hash,
                ])->all(),
            ];
            GradeClosure::query()->create([
                'school_id' => $locked->school_id,
                'book_id' => $locked->book_id,
                'teaching_group_id' => $locked->teaching_group_id,
                'assessment_period_id' => $locked->assessment_period_id,
                'schedule_subject_id' => $locked->schedule_subject_id,
                'scope' => 'assessment',
                'status' => 'closed',
                'snapshot' => $snapshot,
                'snapshot_hash' => $this->canonical->hash($snapshot),
                'revision' => 1,
                'closed_by' => $request->user()->id,
                'closed_at' => now('UTC'),
            ]);
            $locked->forceFill([
                'status' => 'closed',
                'revision' => $locked->revision + 1,
                'lock_version' => $locked->lock_version + 1,
                'updated_by' => $request->user()->id,
            ])->save();
        }, 3);

        $fresh = $model->fresh();
        $this->audit->write('lcd.assessment.closed', 'close', $fresh, actor: $request->user(), schoolId: $fresh->school_id, academicYearId: $fresh->book->academic_year_id, after: ['status' => 'closed', 'results_count' => $fresh->results()->count()], request: $request, entityRevision: $fresh->revision);

        return $this->assessmentResponse($request, $fresh, includeResults: true);
    }

    private function assertMayManage(Request $request, Assessment $assessment): void
    {
        if (! $request->user()->hasPermission('libro_digital.assessments.manage') || ! $this->access->canAccessSchool($request->user(), (int) $assessment->school_id)) {
            abort(403);
        }
        $this->assignmentFor($request, $assessment->book, $assessment->teachingGroup, $assessment->assessment_date->format('Y-m-d'), $assessment->teacher_assignment_id);
    }

    private function assignmentFor(Request $request, Book $book, TeachingGroup $group, string $date, ?int $expectedId = null): TeacherAssignment
    {
        $user = $request->user();
        $override = $user->isSuperAdmin() || $user->hasPermission('libro_digital.books.manage') || $user->hasPermission('libro_digital.closures.manage');
        $query = TeacherAssignment::query()->where('book_id', $book->id)->where('teaching_group_id', $group->id)
            ->where('active', true)->whereDate('valid_from', '<=', $date)
            ->where(fn (Builder $dates) => $dates->whereNull('valid_to')->orWhereDate('valid_to', '>=', $date));
        if ($expectedId) {
            $query->whereKey($expectedId);
        }
        if (! $override) {
            $query->where(function (Builder $identity) use ($user): void {
                $identity->where('user_id', $user->id);
                if ($user->staff_id) {
                    $identity->orWhere('staff_id', $user->staff_id);
                }
            });
        }
        $assignment = $query->orderByDesc('is_primary')->first();
        if (! $assignment) {
            throw new LibroDigitalException('No tienes una asignación docente vigente para esta evaluación.', 'LCD_ASSESSMENT_TEACHER_ASSIGNMENT_REQUIRED', 403);
        }

        return $assignment;
    }

    private function assertBookOpen(Book $book): void
    {
        if ($this->statusValue($book->status) !== 'open') {
            throw new LibroDigitalException('Las evaluaciones solo se modifican mientras el libro está abierto.', 'LCD_BOOK_NOT_OPEN', 409);
        }
    }

    private function enrollmentLink(int $bookId, int $studentId, string $on): EnrollmentLink
    {
        $link = $this->expectedLinks($bookId, $on)->firstWhere('student_profile_id', $studentId);
        if (! $link) {
            throw new LibroDigitalException('El resultado incluye un estudiante fuera de la nómina vigente.', 'LCD_ASSESSMENT_RESULT_OUTSIDE_ROSTER');
        }

        return $link;
    }

    private function expectedLinks(int $bookId, string $on)
    {
        return EnrollmentLink::query()->where('book_id', $bookId)->where('status', 'active')
            ->whereDate('effective_from', '<=', $on)
            ->where(fn (Builder $dates) => $dates->whereNull('effective_to')->orWhereDate('effective_to', '>=', $on))
            ->orderBy('list_number')->get();
    }

    private function expectedResultsCount(Book $book): int
    {
        return EnrollmentLink::query()->where('book_id', $book->id)->where('status', 'active')->count();
    }

    /** @param list<int> $objectiveIds */
    private function appendObjectives(Assessment $assessment, array $objectiveIds): void
    {
        if ($objectiveIds === []) {
            return;
        }
        $this->curriculumScope->assertAllowed(
            $assessment->book,
            (int) $assessment->schedule_subject_id,
            $objectiveIds,
        );
        $objectives = DB::table('lcd_learning_objectives')->whereIn('id', $objectiveIds)
            ->where('active', true)
            ->where(function ($query) use ($assessment): void {
                $query->whereNull('schedule_subject_id')->orWhere('schedule_subject_id', $assessment->schedule_subject_id);
            })->get(['id', 'code', 'description']);
        if ($objectives->count() !== count($objectiveIds)) {
            throw new LibroDigitalException('Uno o más objetivos curriculares no corresponden a la asignatura.', 'LCD_ASSESSMENT_OBJECTIVE_SCOPE_INVALID');
        }
        $existing = DB::table('lcd_assessment_objectives')->where('assessment_id', $assessment->id)
            ->whereIn('learning_objective_id', $objectiveIds)->pluck('learning_objective_id')->map(fn ($id) => (int) $id);
        foreach ($objectives as $objective) {
            if ($existing->contains((int) $objective->id)) {
                continue;
            }
            DB::table('lcd_assessment_objectives')->insert([
                'assessment_id' => $assessment->id,
                'learning_objective_id' => $objective->id,
                'objective_code_snapshot' => $objective->code,
                'objective_description_snapshot' => $objective->description,
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ]);
        }
    }

    private function recordRevision(StudentResult $result, int $schoolId, int $revision, int $actorId): void
    {
        $payload = [
            'assessment_id' => $result->assessment_id,
            'student_profile_id' => $result->student_profile_id,
            'status' => $result->status,
            'raw_score' => $result->raw_score,
            'numeric_value' => $result->numeric_value,
            'qualitative_value' => $result->qualitative_value,
            'normalized_percentage' => $result->normalized_percentage,
            'absent' => $result->absent,
            'exempt' => $result->exempt,
            'record_hash' => $result->record_hash,
        ];
        RecordRevision::query()->firstOrCreate(
            ['revisable_type' => StudentResult::class, 'revisable_id' => $result->id, 'revision' => $revision],
            ['school_id' => $schoolId, 'payload' => $payload, 'payload_hash' => $this->canonical->hash($payload), 'reason' => 'grade_result_recorded', 'created_by' => $actorId],
        );
    }

    private function assessment(string|int $identifier): Assessment
    {
        return $this->aggregate(Assessment::class, $identifier);
    }

    private function assessmentResponse(Request $request, Assessment $assessment, int $status = 200, bool $includeResults = false): JsonResponse
    {
        $assessment->load(['book', 'period', 'gradingScheme', 'subject', 'teacherAssignment.staff']);
        if ($includeResults) {
            $assessment->load('results.student');
        }
        $assessment->loadCount('results');

        return $this->dataResponse($this->payload($assessment, $this->expectedResultsCount($assessment->book), $includeResults), $status, $assessment->lock_version);
    }

    /** @return array<string, mixed> */
    private function payload(Assessment $assessment, int $expected, bool $includeResults = false): array
    {
        $assessment->loadMissing(['curriculumProgram', 'curriculumUnit']);
        $metadata = $assessment->instrument_metadata ?? [];

        return [
            'id' => $assessment->id,
            'public_id' => $assessment->public_id,
            'book_id' => $assessment->book_id,
            'teaching_group_id' => $assessment->teaching_group_id,
            'name' => $assessment->name,
            'description' => $assessment->description,
            'assessment_type' => $assessment->assessment_type,
            'scheduled_on' => $assessment->assessment_date?->format('Y-m-d'),
            'weighting' => $assessment->weight !== null ? (float) $assessment->weight : null,
            'maximum_score' => $assessment->maximum_score !== null ? (float) $assessment->maximum_score : null,
            'grading_scale' => $metadata['grading_scale'] ?? '1_to_7',
            'curriculum_objective_ids' => array_values($metadata['curriculum_objective_ids'] ?? []),
            'curriculum_program_id' => $assessment->curriculumProgram?->public_id,
            'curriculum_unit_id' => $assessment->curriculumUnit?->public_id,
            'status' => $this->statusValue($assessment->status),
            'results_count' => (int) ($assessment->results_count ?? $assessment->results()->count()),
            'expected_results_count' => $expected,
            'revision' => (int) $assessment->revision,
            'lock_version' => (int) $assessment->lock_version,
            'teacher' => $assessment->relationLoaded('teacherAssignment') ? [
                'id' => $assessment->teacherAssignment?->staff_id,
                'name' => $assessment->teacherAssignment?->teacher_name_snapshot,
            ] : null,
            'results' => $includeResults ? $assessment->results->map(fn (StudentResult $result): array => [
                'id' => $result->id,
                'public_id' => $result->public_id,
                'student_profile_id' => $result->student_profile_id,
                'student_name' => $result->student?->registered_name_resolved,
                'status' => $result->status,
                'raw_score' => $result->raw_score !== null ? (float) $result->raw_score : null,
                'numeric_value' => $result->numeric_value !== null ? (float) $result->numeric_value : null,
                'qualitative_value' => $result->qualitative_value,
                'absent' => $result->absent,
                'exempt' => $result->exempt,
                'observation' => $result->observation,
                'revision' => (int) $result->revision,
            ])->values()->all() : null,
            'created_at' => $assessment->created_at?->toIso8601String(),
            'updated_at' => $assessment->updated_at?->toIso8601String(),
        ];
    }
}
