<?php

namespace App\Http\Controllers\LibroDigital;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Http\Requests\LibroDigital\ResolveAbsenceCaseRequest;
use App\Http\Requests\LibroDigital\StoreAbsenceActionRequest;
use App\Http\Requests\LibroDigital\StoreOperationalRecordRequest;
use App\Models\LibroDigital\AbsenceCase;
use App\Models\LibroDigital\AbsenceCaseAction;
use App\Models\LibroDigital\Book;
use App\Models\LibroDigital\EnrollmentLink;
use App\Services\LibroDigital\AuditEventWriter;
use App\Services\LibroDigital\CanonicalJson;
use App\Services\LibroDigital\LibroDigitalAccessContext;
use App\Services\LibroDigital\OptimisticLock;
use App\Services\LibroDigital\RecordRevisionWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class AbsenceCaseController extends LibroDigitalController
{
    public function __construct(
        LibroDigitalAccessContext $access,
        private readonly OptimisticLock $locks,
        private readonly CanonicalJson $canonical,
        private readonly RecordRevisionWriter $revisions,
        private readonly AuditEventWriter $audit,
    ) {
        parent::__construct($access);
    }

    public function index(Request $request): JsonResponse
    {
        $book = $request->filled('book_id') ? $this->book($request->input('book_id')) : null;
        $school = $this->school($request, $book);
        $this->assertPermission($request, $school->id);
        if ($book) {
            $this->authorize('view', $book);
        }
        $paginator = AbsenceCase::query()->where('school_id', $school->id)
            ->when($book, fn (Builder $query, Book $bookModel) => $query->where('book_id', $bookModel->id))
            ->when($request->integer('academic_year_id'), fn (Builder $query, int $yearId) => $query->where('academic_year_id', $yearId))
            ->when($request->integer('course_section_id'), fn (Builder $query, int $courseId) => $query->where('course_section_id', $courseId))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->input('status')))
            ->with(['student', 'actions' => fn ($query) => $query->orderBy('occurred_at')->orderBy('id')])
            ->withCount('actions')->orderByDesc('detected_on')->orderByDesc('id')
            ->paginate(min(200, max(1, $request->integer('per_page', 50))));

        return $this->collectionResponse(
            $paginator->getCollection()->map(fn (AbsenceCase $case): array => $this->payload($case))->all(),
            ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total()],
        );
    }

    public function store(StoreOperationalRecordRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (! filled($data['book_id'] ?? null)) {
            throw new LibroDigitalException('Selecciona el libro asociado al caso de ausencia.', 'LCD_ABSENCE_BOOK_REQUIRED');
        }
        if (! filled($data['student_profile_id'] ?? null)) {
            throw new LibroDigitalException('Selecciona el estudiante del caso de ausencia.', 'LCD_ABSENCE_STUDENT_REQUIRED');
        }
        $book = $this->book($data['book_id']);
        $this->authorize('view', $book);
        $this->assertPermission($request, $book->school_id);
        if ($this->statusValue($book->status) !== 'open') {
            throw new LibroDigitalException('Solo se abren casos nuevos mientras el libro está abierto.', 'LCD_BOOK_NOT_OPEN', 409);
        }
        if (isset($data['academic_year_id']) && (int) $data['academic_year_id'] !== (int) $book->academic_year_id) {
            throw new LibroDigitalException('El año académico no coincide con el libro.', 'LCD_ABSENCE_YEAR_MISMATCH');
        }
        if (isset($data['course_section_id']) && (int) $data['course_section_id'] !== (int) $book->course_section_id) {
            throw new LibroDigitalException('El curso no coincide con el libro.', 'LCD_ABSENCE_COURSE_MISMATCH');
        }
        $link = $this->rosterLink($book, (int) $data['student_profile_id']);
        $timezone = $book->school()->value('timezone') ?: 'America/Santiago';
        $occurredAt = Carbon::createFromFormat('Y-m-d H:i:s', $data['occurred_on'].' 12:00:00', $timezone)->utc();
        $year = $book->academicYear()->value('year');

        $case = DB::transaction(function () use ($request, $book, $link, $data, $occurredAt, $year): AbsenceCase {
            $case = AbsenceCase::query()->create([
                'school_id' => $book->school_id,
                'book_id' => $book->id,
                'academic_year_id' => $book->academic_year_id,
                'student_profile_id' => $link->student_profile_id,
                'student_enrollment_id' => $link->student_enrollment_id,
                'course_section_id' => $link->course_section_id,
                'responsible_user_id' => $request->user()->id,
                'case_number' => 'AUS-'.$year.'-'.Str::upper(substr((string) Str::ulid(), -10)),
                'detected_on' => $data['occurred_on'],
                'absence_started_on' => $data['occurred_on'],
                'last_absence_on' => $data['occurred_on'],
                'consecutive_absences' => 1,
                'accumulated_absences' => 1,
                'risk_level' => $data['category'] ?: 'monitoring',
                'status' => 'open',
                'student_name_snapshot' => $link->student_name_snapshot,
                'course_snapshot' => $link->course_snapshot,
                'initial_snapshot' => ['category' => $data['category'] ?: null, 'detected_on' => $data['occurred_on']],
                'revision' => 1,
                'created_by' => $request->user()->id,
            ]);
            $notes = ['title' => $data['title'], 'description' => $data['description'], 'actions' => $data['actions'] ?? null];
            AbsenceCaseAction::query()->create([
                'absence_case_id' => $case->id,
                'staff_id' => $request->user()->staff_id,
                'user_id' => $request->user()->id,
                'action_type' => 'case_opened',
                'occurred_at' => $occurredAt,
                'result_code' => 'case_created',
                'notes_encrypted' => Crypt::encryptString($this->canonical->encode($notes)),
                'status' => 'completed',
            ]);
            $this->revisions->write($case, $case->school_id, 1, $request->user(), 'absence_case_created');

            return $case;
        }, 3);

        $this->audit->write('lcd.absence_case.created', 'create', $case, actor: $request->user(), schoolId: $case->school_id, academicYearId: $case->academic_year_id, after: $this->auditPayload($case), request: $request);

        return $this->caseResponse($case, 201);
    }

    public function action(StoreAbsenceActionRequest $request, string $absenceCase): JsonResponse
    {
        $model = $this->absenceCase($absenceCase);
        $this->assertPermission($request, $model->school_id);
        $this->locks->assert($model, $request);
        if (in_array($this->statusValue($model->status), ['resolved', 'closed'], true)) {
            throw new LibroDigitalException('No se agregan acciones directas a un caso resuelto.', 'LCD_ABSENCE_CASE_IMMUTABLE', 409);
        }

        DB::transaction(function () use ($request, $model): void {
            $locked = AbsenceCase::query()->lockForUpdate()->findOrFail($model->id);
            $this->locks->assert($locked, $request);
            AbsenceCaseAction::query()->create([
                'absence_case_id' => $locked->id,
                'staff_id' => $request->user()->staff_id,
                'user_id' => $request->user()->id,
                'action_type' => $request->input('action_type') ?: 'follow_up',
                'occurred_at' => Carbon::parse($request->validated('occurred_at'))->utc(),
                'medium' => $request->input('medium'),
                'result_code' => $request->input('result_code'),
                'notes_encrypted' => Crypt::encryptString($request->validated('description')),
                'next_deadline_on' => $request->input('next_deadline_on'),
                'status' => 'completed',
            ]);
            $nextStatus = in_array($this->statusValue($locked->status), ['escalated', 'monitoring'], true)
                ? $this->statusValue($locked->status)
                : 'contacted';
            $locked->forceFill([
                'status' => $nextStatus,
                'next_deadline_on' => $request->input('next_deadline_on', $locked->next_deadline_on),
                'revision' => $locked->revision + 1,
            ])->save();
            $this->revisions->write($locked, $locked->school_id, $locked->revision, $request->user(), 'absence_case_action_added');
        }, 3);

        $fresh = $model->fresh();
        $this->audit->write('lcd.absence_case.action_added', 'add_action', $fresh, actor: $request->user(), schoolId: $fresh->school_id, academicYearId: $fresh->academic_year_id, after: $this->auditPayload($fresh), request: $request, entityRevision: $fresh->revision);

        return $this->caseResponse($fresh);
    }

    public function resolve(ResolveAbsenceCaseRequest $request, string $absenceCase): JsonResponse
    {
        $model = $this->absenceCase($absenceCase);
        $this->assertPermission($request, $model->school_id);
        $this->locks->assert($model, $request);
        if (in_array($this->statusValue($model->status), ['resolved', 'closed'], true)) {
            throw new LibroDigitalException('El caso ya fue resuelto.', 'LCD_ABSENCE_CASE_ALREADY_RESOLVED', 409);
        }

        DB::transaction(function () use ($request, $model): void {
            $locked = AbsenceCase::query()->lockForUpdate()->findOrFail($model->id);
            $this->locks->assert($locked, $request);
            AbsenceCaseAction::query()->create([
                'absence_case_id' => $locked->id,
                'staff_id' => $request->user()->staff_id,
                'user_id' => $request->user()->id,
                'action_type' => 'resolution',
                'occurred_at' => now('UTC'),
                'result_code' => 'resolved',
                'notes_encrypted' => Crypt::encryptString($request->validated('resolution')),
                'status' => 'completed',
            ]);
            $locked->forceFill([
                'status' => 'resolved',
                'closed_at' => now('UTC'),
                'closed_by' => $request->user()->id,
                'closure_reason' => Crypt::encryptString($request->validated('resolution')),
                'revision' => $locked->revision + 1,
            ])->save();
            $this->revisions->write($locked, $locked->school_id, $locked->revision, $request->user(), 'absence_case_resolved');
        }, 3);

        $fresh = $model->fresh();
        $this->audit->write('lcd.absence_case.resolved', 'resolve', $fresh, actor: $request->user(), schoolId: $fresh->school_id, academicYearId: $fresh->academic_year_id, after: $this->auditPayload($fresh), request: $request, entityRevision: $fresh->revision);

        return $this->caseResponse($fresh);
    }

    private function assertPermission(Request $request, int $schoolId): void
    {
        if (! $request->user()->hasPermission('libro_digital.absence.manage') || ! $this->access->canAccessSchool($request->user(), $schoolId)) {
            abort(403);
        }
    }

    private function rosterLink(Book $book, int $studentId): EnrollmentLink
    {
        $link = EnrollmentLink::query()->where('book_id', $book->id)->where('student_profile_id', $studentId)->where('status', 'active')->first();
        if (! $link) {
            throw new LibroDigitalException('El estudiante no pertenece a la nómina del libro.', 'LCD_ABSENCE_STUDENT_OUTSIDE_ROSTER');
        }

        return $link;
    }

    private function absenceCase(string|int $identifier): AbsenceCase
    {
        return $this->aggregate(AbsenceCase::class, $identifier);
    }

    private function caseResponse(AbsenceCase $case, int $status = 200): JsonResponse
    {
        $case->load(['student', 'actions' => fn ($query) => $query->orderBy('occurred_at')->orderBy('id')])->loadCount('actions');

        return $this->dataResponse($this->payload($case), $status, $case->revision);
    }

    /** @return array<string, mixed> */
    private function payload(AbsenceCase $case): array
    {
        $initial = $case->actions->firstWhere('action_type', 'case_opened');
        $initialNotes = $initial ? $this->decryptNotes($initial->notes_encrypted) : [];

        return [
            'id' => $case->id,
            'public_id' => $case->public_id,
            'book_id' => $case->book_id,
            'case_number' => $case->case_number,
            'student_profile_id' => $case->student_profile_id,
            'student' => $case->relationLoaded('student') ? ['id' => $case->student?->id, 'registered_name' => $case->student?->registered_name_resolved] : null,
            'occurred_on' => $case->detected_on?->format('Y-m-d'),
            'opened_on' => $case->detected_on?->format('Y-m-d'),
            'title' => $initialNotes['title'] ?? 'Caso de ausencia',
            'category' => $case->initial_snapshot['category'] ?? $case->risk_level,
            'description' => $initialNotes['description'] ?? null,
            'actions' => $initialNotes['actions'] ?? null,
            'risk_level' => $case->risk_level,
            'status' => $this->statusValue($case->status),
            'actions_count' => (int) ($case->actions_count ?? $case->actions->count()),
            'next_deadline_on' => $case->next_deadline_on?->format('Y-m-d'),
            'resolution' => $this->decrypt($case->closure_reason),
            'revision' => (int) $case->revision,
            'lock_version' => (int) $case->revision,
            'created_at' => $case->created_at?->toIso8601String(),
            'updated_at' => $case->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function auditPayload(AbsenceCase $case): array
    {
        return [
            'book_id' => $case->book_id,
            'case_number' => $case->case_number,
            'student_profile_id' => $case->student_profile_id,
            'risk_level' => $case->risk_level,
            'status' => $this->statusValue($case->status),
            'next_deadline_on' => $case->next_deadline_on?->format('Y-m-d'),
            'revision' => (int) $case->revision,
        ];
    }

    /** @return array<string, mixed> */
    private function decryptNotes(?string $value): array
    {
        $plain = $this->decrypt($value);
        if (! $plain) {
            return [];
        }
        $decoded = json_decode($plain, true);

        return is_array($decoded) ? $decoded : ['description' => $plain];
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
