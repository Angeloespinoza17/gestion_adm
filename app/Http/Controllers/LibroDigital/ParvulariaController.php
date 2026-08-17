<?php

namespace App\Http\Controllers\LibroDigital;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Http\Requests\LibroDigital\StoreOperationalRecordRequest;
use App\Http\Requests\LibroDigital\StoreParvulariaBookRequest;
use App\Http\Resources\LibroDigital\BookResource;
use App\Models\CourseSection;
use App\Models\LibroDigital\Book;
use App\Models\LibroDigital\EnrollmentLink;
use App\Models\LibroDigital\ParvulariaEvaluation;
use App\Models\LibroDigital\ParvulariaPlan;
use App\Models\LibroDigital\RegulatoryProfile;
use App\Models\Staff;
use App\Services\LibroDigital\AuditEventWriter;
use App\Services\LibroDigital\CompliancePreflightService;
use App\Services\LibroDigital\FeatureFlagService;
use App\Services\LibroDigital\LibroDigitalAccessContext;
use App\Services\LibroDigital\RecordRevisionWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class ParvulariaController extends LibroDigitalController
{
    public function __construct(
        LibroDigitalAccessContext $access,
        private readonly FeatureFlagService $features,
        private readonly CompliancePreflightService $preflight,
        private readonly RecordRevisionWriter $revisions,
        private readonly AuditEventWriter $audit,
    ) {
        parent::__construct($access);
    }

    public function indexBooks(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Book::class);
        abort_unless($request->user()?->hasPermission('libro_digital.parvularia.manage'), 403);
        $school = $this->school($request);
        if (! $this->features->enabled('lcd_parvularia_enabled', (int) $school->id)) {
            throw new LibroDigitalException('El módulo de parvularia no está habilitado para este establecimiento.', 'LCD_PARVULARIA_FEATURE_DISABLED', 503);
        }

        $paginator = Book::query()
            ->where('school_id', $school->id)
            ->whereHas('courseSection.educationLevel', fn ($query) => $query->where('type', 'parvularia'))
            ->when($request->integer('academic_year_id'), fn ($query, int $yearId) => $query->where('academic_year_id', $yearId))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->with(['academicYear', 'courseSection.educationLevel', 'regulatoryProfile', 'teachingGroups.subject', 'teachingGroups.teacherAssignments.staff'])
            ->withCount('sessions')
            ->orderByDesc('updated_at')
            ->paginate(min(100, max(1, $request->integer('per_page', 25))));

        return $this->collectionResponse(
            BookResource::collection($paginator->getCollection())->resolve($request),
            [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'capability' => $this->preflight->run((int) $school->id)['capabilities']['parvularia'] ?? [],
            ],
        );
    }

    public function storeBook(StoreParvulariaBookRequest $request, BookController $books): JsonResponse
    {
        $school = $this->school($request);
        if (! $this->features->enabled('lcd_parvularia_enabled', (int) $school->id)) {
            throw new LibroDigitalException('El módulo de parvularia no está habilitado para este establecimiento.', 'LCD_PARVULARIA_FEATURE_DISABLED', 503);
        }
        $data = $request->validated();
        $course = CourseSection::query()->with('educationLevel')->findOrFail($data['course_section_id']);
        if ($course->educationLevel?->type !== 'parvularia') {
            throw new LibroDigitalException('El curso seleccionado no corresponde a educación parvularia.', 'LCD_PARVULARIA_COURSE_REQUIRED', 422);
        }
        $profile = RegulatoryProfile::query()->where('active', true)->findOrFail($data['normative_profile_id']);
        if (! in_array('parvularia', $profile->rules_snapshot['education_types'] ?? [], true)) {
            throw new LibroDigitalException('El perfil normativo seleccionado no declara cobertura para parvularia.', 'LCD_PARVULARIA_PROFILE_REQUIRED', 422);
        }

        // La creación canónica queda en un solo lugar para conservar exactamente
        // los mismos snapshots, nómina sellada, asignación docente y auditoría.
        return $books->store($request);
    }

    public function show(Request $request, string $book): JsonResponse
    {
        $bookModel = $this->book($book);
        $this->assertAvailable($request, $bookModel);
        $bookModel->load(['courseSection.educationLevel', 'regulatoryProfile', 'teachingGroups']);

        return $this->dataResponse([
            'book_id' => $bookModel->id,
            'book_public_id' => $bookModel->public_id,
            'status' => $this->statusValue($bookModel->status),
            'course' => $bookModel->course_label,
            'education_type' => $bookModel->courseSection?->educationLevel?->type,
            'regulatory_profile' => [
                'id' => $bookModel->regulatoryProfile?->id,
                'code' => $bookModel->regulatoryProfile?->code,
                'name' => $bookModel->regulatoryProfile?->name,
                'version' => $bookModel->regulatoryProfile?->version,
            ],
            'planning_count' => ParvulariaPlan::query()->where('book_id', $bookModel->id)->count(),
            'evaluations_count' => ParvulariaEvaluation::query()->where('book_id', $bookModel->id)->count(),
        ], version: $bookModel->lock_version);
    }

    public function planning(Request $request, string $book): JsonResponse
    {
        $bookModel = $this->book($book);
        $this->assertAvailable($request, $bookModel);
        $paginator = ParvulariaPlan::query()->where('book_id', $bookModel->id)->with('responsible')
            ->orderByDesc('starts_on')->orderByDesc('id')
            ->paginate(min(200, max(1, $request->integer('per_page', 50))));

        return $this->collectionResponse(
            $paginator->getCollection()->map(fn (ParvulariaPlan $plan): array => $this->planPayload($plan))->all(),
            ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total()],
        );
    }

    public function storePlanning(StoreOperationalRecordRequest $request, string $book): JsonResponse
    {
        $bookModel = $this->book($book);
        $this->assertAvailable($request, $bookModel);
        $this->assertBookOpen($bookModel);
        $data = $request->validated();
        $this->assertDateInYear($bookModel, $data['occurred_on']);
        $responsible = $this->professional($request, $data['professional_staff_id'] ?? null);
        $group = $bookModel->teachingGroups()->firstOrFail();

        $plan = ParvulariaPlan::query()->create([
            'school_id' => $bookModel->school_id,
            'book_id' => $bookModel->id,
            'teaching_group_id' => $group->id,
            'responsible_staff_id' => $responsible->id,
            'plan_type' => $data['category'] ?: 'learning_experience',
            'horizon' => 'daily',
            'title' => $data['title'],
            'curricular_scope' => $data['category'] ?: null,
            'learning_experience' => $data['description'],
            'pedagogical_strategies' => $data['actions'] ?? null,
            'responsibles' => [['staff_id' => $responsible->id, 'name' => $responsible->full_name]],
            'starts_on' => $data['occurred_on'],
            'ends_on' => $data['occurred_on'],
            'status' => 'draft',
            'revision' => 1,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);
        $this->revisions->write($plan, $plan->school_id, 1, $request->user(), 'parvularia_plan_created');
        $this->audit->write('lcd.parvularia.plan_created', 'create', $plan, actor: $request->user(), schoolId: $plan->school_id, academicYearId: $bookModel->academic_year_id, after: $plan->toArray(), request: $request);

        return $this->dataResponse($this->planPayload($plan->load('responsible')), 201, $plan->revision);
    }

    public function evaluations(Request $request, string $book): JsonResponse
    {
        $bookModel = $this->book($book);
        $this->assertAvailable($request, $bookModel);
        $paginator = ParvulariaEvaluation::query()->where('book_id', $bookModel->id)
            ->with(['student', 'evaluator', 'plan'])->orderByDesc('observed_at')->orderByDesc('id')
            ->paginate(min(200, max(1, $request->integer('per_page', 50))));

        return $this->collectionResponse(
            $paginator->getCollection()->map(fn (ParvulariaEvaluation $evaluation): array => $this->evaluationPayload($evaluation))->all(),
            ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total()],
        );
    }

    public function storeEvaluation(StoreOperationalRecordRequest $request, string $book): JsonResponse
    {
        $bookModel = $this->book($book);
        $this->assertAvailable($request, $bookModel);
        $this->assertBookOpen($bookModel);
        $data = $request->validated();
        $this->assertDateInYear($bookModel, $data['occurred_on']);
        $evaluator = $this->professional($request, $data['professional_staff_id'] ?? null);
        $group = $bookModel->teachingGroups()->firstOrFail();
        $link = filled($data['student_profile_id'] ?? null)
            ? $this->rosterLink($bookModel, (int) $data['student_profile_id'])
            : null;
        $observedAt = Carbon::createFromFormat('Y-m-d H:i:s', $data['occurred_on'].' 12:00:00', $bookModel->school()->value('timezone') ?: 'America/Santiago')->utc();

        $evaluation = ParvulariaEvaluation::query()->create([
            'school_id' => $bookModel->school_id,
            'book_id' => $bookModel->id,
            'teaching_group_id' => $group->id,
            'student_profile_id' => $link?->student_profile_id,
            'student_enrollment_id' => $link?->student_enrollment_id,
            'evaluator_staff_id' => $evaluator->id,
            'evaluation_type' => $data['category'] ?: 'pedagogical_observation',
            'scope' => $link ? 'individual' : 'group',
            'observed_at' => $observedAt,
            'indicator_snapshot' => $data['title'],
            'observation_encrypted' => Crypt::encryptString($data['description']),
            'feedback_encrypted' => filled($data['actions'] ?? null) ? Crypt::encryptString($data['actions']) : null,
            'confidentiality_level' => $data['confidentiality_level'] ?? 'restricted',
            'status' => 'recorded',
            'revision' => 1,
            'created_by' => $request->user()->id,
        ]);
        $this->revisions->write($evaluation, $evaluation->school_id, 1, $request->user(), 'parvularia_evaluation_created');
        $this->audit->write('lcd.parvularia.evaluation_created', 'create', $evaluation, actor: $request->user(), schoolId: $evaluation->school_id, academicYearId: $bookModel->academic_year_id, after: $this->evaluationAuditPayload($evaluation), request: $request);

        return $this->dataResponse($this->evaluationPayload($evaluation->load(['student', 'evaluator'])), 201, $evaluation->revision);
    }

    private function assertAvailable(Request $request, Book $book): void
    {
        $this->authorize('view', $book);
        if (! $request->user()->hasPermission('libro_digital.parvularia.manage') || ! $this->access->canAccessSchool($request->user(), (int) $book->school_id)) {
            abort(403);
        }
        if (! $this->features->enabled('lcd_parvularia_enabled', (int) $book->school_id)) {
            throw new LibroDigitalException('El módulo de parvularia no está habilitado para este establecimiento.', 'LCD_PARVULARIA_FEATURE_DISABLED', 503);
        }
        $capability = $this->preflight->run((int) $book->school_id)['capabilities']['parvularia'] ?? [];
        if (! ($capability['ready'] ?? false)) {
            throw new LibroDigitalException('El preflight normativo de parvularia aún tiene bloqueadores.', 'LCD_PARVULARIA_PREFLIGHT_REQUIRED', 503);
        }
        $book->loadMissing(['courseSection.educationLevel', 'regulatoryProfile']);
        $educationType = $book->courseSection?->educationLevel?->type;
        $profileTypes = $book->regulatoryProfile?->rules_snapshot['education_types'] ?? [];
        if ($educationType !== 'parvularia' || ! in_array('parvularia', $profileTypes, true)) {
            throw new LibroDigitalException('El libro y su perfil normativo no corresponden a educación parvularia.', 'LCD_PARVULARIA_SCOPE_INVALID', 409);
        }
    }

    private function assertBookOpen(Book $book): void
    {
        if ($this->statusValue($book->status) !== 'open') {
            throw new LibroDigitalException('La planificación y evaluación se registran mientras el libro está abierto.', 'LCD_BOOK_NOT_OPEN', 409);
        }
    }

    private function assertDateInYear(Book $book, string $date): void
    {
        $year = $book->academicYear()->firstOrFail();
        if ($date < $year->starts_at->format('Y-m-d') || $date > $year->ends_at->format('Y-m-d')) {
            throw new LibroDigitalException('La fecha queda fuera del año académico del libro.', 'LCD_PARVULARIA_DATE_OUTSIDE_YEAR');
        }
    }

    private function professional(Request $request, mixed $staffId): Staff
    {
        $staffId = $staffId ?: $request->user()->staff_id;
        if (! $staffId) {
            throw new LibroDigitalException('Identifica al profesional responsable del registro.', 'LCD_PARVULARIA_PROFESSIONAL_REQUIRED');
        }

        return Staff::query()->where('active', true)->findOrFail((int) $staffId);
    }

    private function rosterLink(Book $book, int $studentId): EnrollmentLink
    {
        $link = EnrollmentLink::query()->where('book_id', $book->id)->where('student_profile_id', $studentId)->where('status', 'active')->first();
        if (! $link) {
            throw new LibroDigitalException('El estudiante no pertenece a la nómina del libro.', 'LCD_PARVULARIA_STUDENT_OUTSIDE_ROSTER');
        }

        return $link;
    }

    /** @return array<string, mixed> */
    private function planPayload(ParvulariaPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'public_id' => $plan->public_id,
            'book_id' => $plan->book_id,
            'planned_on' => $plan->starts_on?->format('Y-m-d'),
            'occurred_on' => $plan->starts_on?->format('Y-m-d'),
            'title' => $plan->title,
            'category' => $plan->plan_type,
            'description' => $plan->learning_experience,
            'actions' => $plan->pedagogical_strategies,
            'responsible_staff_id' => $plan->responsible_staff_id,
            'responsible_name' => $plan->responsible?->full_name,
            'status' => $plan->status,
            'revision' => (int) $plan->revision,
            'lock_version' => (int) $plan->revision,
            'created_at' => $plan->created_at?->toIso8601String(),
            'updated_at' => $plan->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function evaluationPayload(ParvulariaEvaluation $evaluation): array
    {
        return [
            'id' => $evaluation->id,
            'public_id' => $evaluation->public_id,
            'book_id' => $evaluation->book_id,
            'student_profile_id' => $evaluation->student_profile_id,
            'student' => $evaluation->relationLoaded('student') && $evaluation->student ? ['id' => $evaluation->student->id, 'registered_name' => $evaluation->student->registered_name_resolved] : null,
            'occurred_on' => $evaluation->observed_at?->format('Y-m-d'),
            'title' => $evaluation->indicator_snapshot,
            'category' => $evaluation->evaluation_type,
            'description' => $this->decrypt($evaluation->observation_encrypted),
            'actions' => $this->decrypt($evaluation->feedback_encrypted),
            'scope' => $evaluation->scope,
            'confidentiality_level' => $evaluation->confidentiality_level,
            'evaluator_staff_id' => $evaluation->evaluator_staff_id,
            'evaluator_name' => $evaluation->evaluator?->full_name,
            'status' => $evaluation->status,
            'revision' => (int) $evaluation->revision,
            'lock_version' => (int) $evaluation->revision,
            'created_at' => $evaluation->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function evaluationAuditPayload(ParvulariaEvaluation $evaluation): array
    {
        return [
            'book_id' => $evaluation->book_id,
            'student_profile_id' => $evaluation->student_profile_id,
            'evaluation_type' => $evaluation->evaluation_type,
            'scope' => $evaluation->scope,
            'observed_at' => $evaluation->observed_at?->toIso8601String(),
            'status' => $evaluation->status,
            'revision' => (int) $evaluation->revision,
        ];
    }

    private function decrypt(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }
        try {
            return Crypt::decryptString($value);
        } catch (Throwable) {
            return null;
        }
    }
}
