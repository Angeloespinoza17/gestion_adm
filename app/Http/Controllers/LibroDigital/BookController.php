<?php

namespace App\Http\Controllers\LibroDigital;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Http\Requests\LibroDigital\BookActionRequest;
use App\Http\Requests\LibroDigital\StoreBookRequest;
use App\Http\Requests\LibroDigital\UpdateBookRequest;
use App\Http\Resources\LibroDigital\BookResource;
use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\LibroDigital\Book;
use App\Models\LibroDigital\ClosureReopening;
use App\Models\LibroDigital\EnrollmentLink;
use App\Models\LibroDigital\RegulatoryProfile;
use App\Models\LibroDigital\RosterSnapshot;
use App\Models\LibroDigital\RosterSnapshotItem;
use App\Models\LibroDigital\TeacherAssignment;
use App\Models\LibroDigital\TeachingGroup;
use App\Models\Schedule\ScheduleSubject;
use App\Models\Staff;
use App\Models\StudentEnrollment;
use App\Services\LibroDigital\AuditEventWriter;
use App\Services\LibroDigital\CanonicalJson;
use App\Services\LibroDigital\CompliancePreflightService;
use App\Services\LibroDigital\LibroDigitalAccessContext;
use App\Services\LibroDigital\OptimisticLock;
use App\Services\LibroDigital\WorkflowStateMachine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookController extends LibroDigitalController
{
    public function __construct(
        LibroDigitalAccessContext $access,
        private readonly OptimisticLock $locks,
        private readonly CanonicalJson $canonical,
        private readonly AuditEventWriter $audit,
        private readonly CompliancePreflightService $preflight,
        private readonly WorkflowStateMachine $workflows,
    ) {
        parent::__construct($access);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Book::class);
        $school = $this->school($request);
        $query = Book::query()->where('school_id', $school->id)
            ->when($request->integer('academic_year_id'), fn (Builder $builder, int $id) => $builder->where('academic_year_id', $id))
            ->when($request->integer('course_section_id'), fn (Builder $builder, int $id) => $builder->where('course_section_id', $id))
            ->when($request->filled('status'), fn (Builder $builder) => $builder->where('status', $request->string('status')))
            ->when($request->integer('education_level_id'), fn (Builder $builder, int $id) => $builder->whereHas('courseSection', fn (Builder $course) => $course->where('education_level_id', $id)))
            ->when($request->integer('schedule_subject_id'), fn (Builder $builder, int $id) => $builder->whereHas('teachingGroups', fn (Builder $group) => $group->where('schedule_subject_id', $id)))
            ->with($this->relations())->withCount('sessions')->orderByDesc('updated_at');

        $perPage = min(200, max(1, $request->integer('per_page', 25)));
        $paginator = $query->paginate($perPage);

        return $this->collectionResponse(
            BookResource::collection($paginator->getCollection())->resolve($request),
            ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total()]
        );
    }

    public function store(StoreBookRequest $request): JsonResponse
    {
        $this->authorize('create', Book::class);
        $school = $this->school($request);
        $data = $request->validated();
        $course = CourseSection::query()->with(['academicYear', 'educationLevel'])->findOrFail($data['course_section_id']);
        if ((int) $course->academic_year_id !== (int) $data['academic_year_id']) {
            throw new LibroDigitalException('El curso no pertenece al año académico seleccionado.', 'LCD_COURSE_YEAR_MISMATCH');
        }
        if (! $school->academicYears()->whereKey($data['academic_year_id'])->wherePivot('active', true)->exists()) {
            throw new LibroDigitalException('El año académico no está habilitado para este establecimiento.', 'LCD_SCHOOL_YEAR_REQUIRED');
        }

        $year = AcademicYear::query()->findOrFail($data['academic_year_id']);
        $subject = ScheduleSubject::query()->findOrFail($data['schedule_subject_id']);
        $teacher = Staff::query()->with('user')->findOrFail($data['teacher_staff_id']);
        $profileId = $data['normative_profile_id'] ?? $data['regulatory_profile_id'] ?? null;
        $profileId ??= $school->academicYears()->whereKey($year->id)->first()?->pivot?->regulatory_profile_id;
        $profile = $profileId
            ? RegulatoryProfile::query()->where('active', true)->findOrFail($profileId)
            : RegulatoryProfile::query()->where('active', true)->whereDate('effective_from', '<=', now()->toDateString())->orderByDesc('effective_from')->firstOrFail();

        $book = DB::transaction(function () use ($request, $school, $data, $course, $year, $subject, $teacher, $profile): Book {
            $codeBase = Str::upper('LCD-'.$year->year.'-'.$course->id.'-'.$subject->id);
            $code = $codeBase;
            $suffix = 1;
            while (Book::query()->where('school_id', $school->id)->where('academic_year_id', $year->id)->where('code', $code)->exists()) {
                $code = $codeBase.'-'.(++$suffix);
            }

            $book = Book::query()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'regulatory_profile_id' => $profile->id,
                'course_section_id' => $course->id,
                'code' => $code,
                'rbd_snapshot' => $school->rbd,
                'year_snapshot' => $year->year,
                'level_code' => $course->educationLevel?->type,
                'grade_code' => $course->educationLevel?->name,
                'course_label' => $course->display_name,
                'modality_code' => $data['modality'] ?? $course->educationLevel?->type,
                'status' => 'draft',
                'revision' => 1,
                'lock_version' => 1,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            $effectiveOn = max($year->starts_at?->toDateString() ?? now()->toDateString(), now()->toDateString());
            $group = TeachingGroup::query()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'book_id' => $book->id,
                'course_section_id' => $course->id,
                'schedule_subject_id' => $subject->id,
                'code' => $code.'-G1',
                'name' => $data['name'] ?: $course->display_name.' · '.$subject->name,
                'course_snapshot' => $course->display_name,
                'subject_snapshot' => $subject->name,
                'valid_from' => $year->starts_at ?? now()->toDateString(),
                'valid_to' => $year->ends_at,
                'metadata' => ['notes' => $data['notes'] ?? null],
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            TeacherAssignment::query()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'book_id' => $book->id,
                'teaching_group_id' => $group->id,
                'staff_id' => $teacher->id,
                'user_id' => $teacher->user?->id,
                'schedule_subject_id' => $subject->id,
                'teacher_name_snapshot' => $teacher->full_name,
                'valid_from' => $year->starts_at ?? now()->toDateString(),
                'valid_to' => $year->ends_at,
                'is_primary' => true,
                'active' => true,
                'assigned_by' => $request->user()->id,
            ]);

            $enrollments = StudentEnrollment::query()->with('studentProfile')
                ->where('academic_year_id', $year->id)->where('course_section_id', $course->id)
                ->whereNotIn('enrollment_status', StudentEnrollment::NON_ROSTER_STATUS_VALUES)
                ->orderBy('id')->get();
            $snapshotRows = [];
            foreach ($enrollments as $index => $enrollment) {
                $student = $enrollment->studentProfile;
                $link = EnrollmentLink::query()->create([
                    'school_id' => $school->id,
                    'book_id' => $book->id,
                    'teaching_group_id' => $group->id,
                    'student_profile_id' => $student->id,
                    'student_enrollment_id' => $enrollment->id,
                    'course_section_id' => $course->id,
                    'list_number' => $index + 1,
                    'effective_from' => $enrollment->enrolled_at ?? $effectiveOn,
                    'effective_to' => $enrollment->withdrawn_at,
                    'status' => 'active',
                    'student_name_snapshot' => $student->registered_name_resolved,
                    'identifier_snapshot_encrypted' => filled($student->rut) ? Crypt::encryptString((string) $student->rut) : null,
                    'enrollment_status_snapshot' => $enrollment->enrollment_status,
                    'course_snapshot' => $course->display_name,
                    'created_by' => $request->user()->id,
                ]);
                $snapshotRows[] = compact('link', 'student', 'enrollment') + ['list_number' => $index + 1];
            }

            $snapshotPayload = collect($snapshotRows)->map(fn (array $row) => [
                'student_profile_id' => $row['student']->id,
                'student_enrollment_id' => $row['enrollment']->id,
                'list_number' => $row['list_number'],
                'name' => $row['student']->registered_name_resolved,
                'status' => $row['enrollment']->enrollment_status,
            ])->all();
            $snapshot = RosterSnapshot::query()->create([
                'book_id' => $book->id,
                'teaching_group_id' => $group->id,
                'effective_on' => $effectiveOn,
                'reason' => 'book_created',
                'status' => 'sealed',
                'student_count' => count($snapshotRows),
                'snapshot_hash' => $this->canonical->hash($snapshotPayload),
                'created_by' => $request->user()->id,
            ]);
            foreach ($snapshotRows as $row) {
                $payload = [
                    'roster_snapshot_id' => $snapshot->id,
                    'enrollment_link_id' => $row['link']->id,
                    'student_profile_id' => $row['student']->id,
                    'student_enrollment_id' => $row['enrollment']->id,
                    'list_number' => $row['list_number'],
                    'active_from' => $row['link']->effective_from,
                    'active_to' => $row['link']->effective_to,
                    'applicability_status' => 'applicable',
                    'student_name_snapshot' => $row['student']->registered_name_resolved,
                    'identifier_snapshot_encrypted' => $row['link']->identifier_snapshot_encrypted,
                    'course_snapshot' => $course->display_name,
                    'enrollment_status_snapshot' => $row['enrollment']->enrollment_status,
                ];
                RosterSnapshotItem::query()->create($payload + ['record_hash' => $this->canonical->hash($payload)]);
            }

            return $book;
        }, 3);

        $this->audit->write('lcd.book.created', 'create', $book, actor: $request->user(), schoolId: $school->id, academicYearId: $book->academic_year_id, after: $book->toArray(), request: $request);

        return $this->bookResponse($request, $book, 201);
    }

    public function show(Request $request, string $book): JsonResponse
    {
        $model = $this->book($book);
        $this->authorize('view', $model);

        return $this->bookResponse($request, $model);
    }

    public function update(UpdateBookRequest $request, string $book): JsonResponse
    {
        $model = $this->book($book);
        $this->authorize('update', $model);
        $this->locks->assert($model, $request);
        if (! in_array($this->statusValue($model->status), ['draft', 'pending_preflight'], true)) {
            throw new LibroDigitalException('Solo un libro en preparación se puede editar directamente.', 'LCD_BOOK_IMMUTABLE', 409);
        }
        $data = $request->validated();
        foreach (['academic_year_id', 'course_section_id'] as $immutable) {
            if (isset($data[$immutable]) && (int) $data[$immutable] !== (int) $model->{$immutable}) {
                throw new LibroDigitalException('El año y curso no se reemplazan: crea un nuevo libro para conservar trazabilidad.', 'LCD_BOOK_SCOPE_IMMUTABLE', 409);
            }
        }

        DB::transaction(function () use ($request, $model, $data): void {
            $locked = Book::query()->lockForUpdate()->findOrFail($model->id);
            $group = $locked->teachingGroups()->with('teacherAssignments')->firstOrFail();
            if (isset($data['schedule_subject_id']) && (int) $data['schedule_subject_id'] !== (int) $group->schedule_subject_id) {
                $subject = ScheduleSubject::query()->findOrFail($data['schedule_subject_id']);
                $group->forceFill(['schedule_subject_id' => $subject->id, 'subject_snapshot' => $subject->name])->save();
            }
            if (array_key_exists('name', $data)) {
                $group->name = $data['name'] ?: $group->name;
            }
            if (array_key_exists('notes', $data)) {
                $group->metadata = [...($group->metadata ?? []), 'notes' => $data['notes']];
            }
            $group->updated_by = $request->user()->id;
            $group->save();

            if (isset($data['teacher_staff_id'])) {
                $current = $group->teacherAssignments->firstWhere('is_primary', true);
                if (! $current || (int) $current->staff_id !== (int) $data['teacher_staff_id']) {
                    $current?->forceFill(['active' => false, 'valid_to' => now()->subDay()->toDateString()])->save();
                    $teacher = Staff::query()->with('user')->findOrFail($data['teacher_staff_id']);
                    TeacherAssignment::query()->create([
                        'school_id' => $locked->school_id, 'academic_year_id' => $locked->academic_year_id,
                        'book_id' => $locked->id, 'teaching_group_id' => $group->id, 'staff_id' => $teacher->id,
                        'user_id' => $teacher->user?->id, 'schedule_subject_id' => $group->schedule_subject_id,
                        'teacher_name_snapshot' => $teacher->full_name, 'valid_from' => now()->toDateString(),
                        'is_primary' => true, 'active' => true, 'assigned_by' => $request->user()->id,
                    ]);
                }
            }
            if (isset($data['regulatory_profile_id']) || isset($data['normative_profile_id'])) {
                $locked->regulatory_profile_id = $data['regulatory_profile_id'] ?? $data['normative_profile_id'];
            }
            if (array_key_exists('modality', $data)) {
                $locked->modality_code = $data['modality'];
            }
            $locked->revision++;
            $locked->lock_version++;
            $locked->updated_by = $request->user()->id;
            $locked->save();
        }, 3);

        $fresh = $model->fresh();
        $this->audit->write('lcd.book.updated', 'update', $fresh, actor: $request->user(), schoolId: $fresh->school_id, academicYearId: $fresh->academic_year_id, after: $fresh->toArray(), request: $request, entityRevision: $fresh->revision);

        return $this->bookResponse($request, $fresh);
    }

    public function preflight(BookActionRequest $request, string $book): JsonResponse
    {
        return $this->transition($request, $book, 'pending_preflight');
    }

    public function open(BookActionRequest $request, string $book): JsonResponse
    {
        return $this->transition($request, $book, 'open');
    }

    public function close(BookActionRequest $request, string $book): JsonResponse
    {
        return $this->transition($request, $book, 'closed');
    }

    public function reopen(BookActionRequest $request, string $book): JsonResponse
    {
        return $this->transition($request, $book, 'open', true);
    }

    public function roster(Request $request, string $book): JsonResponse
    {
        $model = $this->book($book);
        $this->authorize('view', $model);
        $date = $request->date('date')?->toDateString() ?: now()->toDateString();
        $snapshot = RosterSnapshot::query()->where('book_id', $model->id)->whereDate('effective_on', '<=', $date)
            ->where('status', 'sealed')->latest('effective_on')->latest('revision')->first();
        $snapshot?->load('teachingGroup:id,public_id');
        $items = $snapshot?->items()->with([
            'student:id,first_name,last_name,registered_name',
            'enrollmentLink:id,public_id,teaching_group_id',
        ])->orderByRaw('list_number IS NULL')->orderBy('list_number')->get() ?? collect();

        return $this->collectionResponse($items->map(fn (RosterSnapshotItem $item) => [
            'id' => $item->id,
            'roster_snapshot_item_id' => $item->id,
            'enrollment_link_id' => $item->enrollment_link_id,
            'enrollment_link_public_id' => $item->enrollmentLink?->public_id,
            'student_reference' => $item->enrollmentLink?->public_id,
            'student_profile_id' => $item->student_profile_id,
            'student_enrollment_id' => $item->student_enrollment_id,
            'list_number' => $item->list_number,
            'applicability_status' => $item->applicability_status,
            'student_name' => $item->student_name_snapshot,
            'student' => ['id' => $item->student_profile_id, 'registered_name' => $item->student?->registered_name, 'full_name' => $item->student?->full_name],
        ])->values()->all(), [
            'snapshot' => $snapshot ? [
                ...$snapshot->only(['id', 'public_id', 'effective_on', 'revision', 'student_count', 'snapshot_hash', 'status']),
                'teaching_group_id' => $snapshot->teaching_group_id,
                'teaching_group_public_id' => $snapshot->teachingGroup?->public_id,
            ] : null,
        ]);
    }

    private function transition(BookActionRequest $request, string $identifier, string $target, bool $reopening = false): JsonResponse
    {
        $book = $this->book($identifier);
        $this->authorize('manage', $book);
        $this->locks->assert($book, $request);
        $from = $this->statusValue($book->status);
        $firstTarget = $target === 'closed' ? 'closing' : $target;
        $transitionAllowed = $from === $firstTarget || $this->workflows->can('book', $from, $firstTarget);
        if ($reopening && $from !== 'closed') {
            $transitionAllowed = false;
        }
        if (! $transitionAllowed) {
            throw new LibroDigitalException("No es posible cambiar el libro de {$from} a {$target}.", 'LCD_BOOK_TRANSITION_INVALID', 409);
        }
        if ($target === 'closed' && $book->sessions()->whereNotIn('status', ['signed', 'closed', 'cancelled'])->exists()) {
            throw new LibroDigitalException('No se puede cerrar mientras existan sesiones sin firmar o anular.', 'LCD_BOOK_CLOSE_BLOCKED', 422);
        }
        $checks = null;
        if (in_array($target, ['pending_preflight', 'open'], true)) {
            $checks = $this->preflight->run($book->school_id);
            $bookChecks = [
                ['code' => 'sealed_roster', 'passed' => $book->teachingGroups()->whereHas('rosterSnapshots', fn (Builder $query) => $query->where('status', 'sealed'))->exists()],
                ['code' => 'teacher_assignment', 'passed' => $book->teachingGroups()->whereHas('teacherAssignments', fn (Builder $query) => $query->where('active', true))->exists()],
            ];
            $checks['checks'] = [...$checks['checks'], ...$bookChecks];
            $checks['ready'] = (bool) $checks['ready'] && collect($bookChecks)->every('passed');
            if (! $checks['ready']) {
                throw new LibroDigitalException('El libro no supera sus controles normativos, de nómina o asignación docente.', 'LCD_BOOK_PREFLIGHT_FAILED', 422, $checks['checks']);
            }
        }

        $transitionApplied = DB::transaction(function () use ($request, $book, $target, $reopening): bool {
            $locked = Book::query()->lockForUpdate()->findOrFail($book->id);
            if ($reopening) {
                $reason = trim((string) $request->input('reason'));
                if ($reason === '') {
                    throw new LibroDigitalException('La reapertura requiere un fundamento.', 'LCD_REOPEN_REASON_REQUIRED');
                }
                $approved = $request->user()->hasPermission('libro_digital.closures.reopen');
                ClosureReopening::query()->create([
                    'school_id' => $locked->school_id, 'book_id' => $locked->id,
                    'closable_type' => $locked::class, 'closable_id' => $locked->id,
                    'closed_revision' => $locked->revision, 'scope' => 'book',
                    'status' => $approved ? 'approved' : 'pending', 'reason' => $reason,
                    'original_snapshot_hash' => $this->canonical->hash($locked->toArray()),
                    'requested_by' => $request->user()->id, 'requested_at' => now('UTC'),
                    'approved_by' => $approved ? $request->user()->id : null,
                    'approved_at' => $approved ? now('UTC') : null,
                    'reopened_at' => $approved ? now('UTC') : null,
                ]);
                if (! $approved) {
                    return false;
                }
            }
            if ($target === 'closed') {
                $this->workflows->assertCan('book', $this->statusValue($locked->status), 'closing');
                $locked->status = 'closing';
                $locked->revision++;
                $locked->lock_version++;
                $locked->updated_by = $request->user()->id;
                $locked->save();
                $this->workflows->assertCan('book', 'closing', 'closed');
            }
            $locked->status = $target;
            if ($target === 'open') {
                $locked->opened_at = now('UTC');
                $locked->opened_by = $request->user()->id;
                $locked->closed_at = null;
                $locked->closed_by = null;
            }
            if ($target === 'closed') {
                $locked->closed_at = now('UTC');
                $locked->closed_by = $request->user()->id;
            }
            $locked->revision++;
            $locked->lock_version++;
            $locked->updated_by = $request->user()->id;
            $locked->save();

            return true;
        }, 3);

        $fresh = $book->fresh();
        if ($target === 'closed') {
            $this->audit->write('lcd.book.closing', 'closing', $fresh, actor: $request->user(), schoolId: $fresh->school_id, academicYearId: $fresh->academic_year_id, before: ['status' => $from], after: ['status' => 'closing'], request: $request, entityRevision: max(1, $fresh->revision - 1));
        }
        $event = $transitionApplied ? 'lcd.book.status_changed' : 'lcd.book.reopening_requested';
        $action = $transitionApplied ? $target : 'request_reopening';
        $this->audit->write($event, $action, $fresh, actor: $request->user(), schoolId: $fresh->school_id, academicYearId: $fresh->academic_year_id, before: ['status' => $from], after: ['status' => $this->statusValue($fresh->status)], reason: $request->input('reason'), request: $request, entityRevision: $fresh->revision);
        $response = (new BookResource($fresh->load($this->relations())->loadCount('sessions')))->resolve($request);
        if ($reopening) {
            $response['reopening_status'] = $transitionApplied ? 'approved' : 'pending';
        }
        if ($checks !== null) {
            $response['preflight'] = $checks;
        }

        return $this->dataResponse($response, version: $fresh->lock_version);
    }

    private function bookResponse(Request $request, Book $book, int $status = 200): JsonResponse
    {
        $book->load($this->relations())->loadCount('sessions');
        $group = $book->teachingGroups->first();
        if ($group) {
            $group->setAttribute('roster_count', $group->enrollmentLinks()->where('status', 'active')->count());
        }

        return $this->dataResponse((new BookResource($book))->resolve($request), $status, $book->lock_version);
    }

    /** @return array<int, string> */
    private function relations(): array
    {
        return ['academicYear', 'courseSection', 'regulatoryProfile', 'teachingGroups.subject', 'teachingGroups.teacherAssignments.staff'];
    }
}
