<?php

namespace App\Http\Controllers\LibroDigital;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Http\Requests\LibroDigital\CancelSessionRequest;
use App\Http\Requests\LibroDigital\SignSessionRequest;
use App\Http\Requests\LibroDigital\StoreSessionRequest;
use App\Http\Requests\LibroDigital\UpdateSessionRequest;
use App\Http\Resources\LibroDigital\ClassSessionResource;
use App\Models\LibroDigital\Book;
use App\Models\LibroDigital\ClassSession;
use App\Models\LibroDigital\RosterSnapshot;
use App\Models\LibroDigital\TeacherAssignment;
use App\Models\LibroDigital\TeacherSignature;
use App\Models\Schedule\ScheduleSubject;
use App\Models\Staff;
use App\Services\LibroDigital\AuditEventWriter;
use App\Services\LibroDigital\CanonicalJson;
use App\Services\LibroDigital\LibroDigitalAccessContext;
use App\Services\LibroDigital\OptimisticLock;
use App\Services\LibroDigital\TeacherSignatureService;
use App\Services\LibroDigital\WorkflowStateMachine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SessionController extends LibroDigitalController
{
    public function __construct(
        LibroDigitalAccessContext $access,
        private readonly OptimisticLock $locks,
        private readonly CanonicalJson $canonical,
        private readonly AuditEventWriter $audit,
        private readonly TeacherSignatureService $signatures,
        private readonly WorkflowStateMachine $workflows,
    ) {
        parent::__construct($access);
    }

    public function index(Request $request, string $book): JsonResponse
    {
        $bookModel = $this->book($book);
        $this->authorize('view', $bookModel);
        $sessions = ClassSession::query()->where('book_id', $bookModel->id)
            ->when($request->input('date_from') ?: $request->input('from'), fn (Builder $query, string $date) => $query->whereDate('session_date', '>=', $date))
            ->when($request->input('date_to') ?: $request->input('to'), fn (Builder $query, string $date) => $query->whereDate('session_date', '<=', $date))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->input('status')))
            ->when($request->integer('teacher_staff_id'), fn (Builder $query, int $id) => $query->where('actual_teacher_id', $id))
            ->with($this->relations())->withCount('attendance')
            ->orderByDesc('session_date')->orderByDesc('scheduled_start_at')->paginate(min(200, max(1, $request->integer('per_page', 50))));

        return $this->collectionResponse(
            ClassSessionResource::collection($sessions->getCollection())->resolve($request),
            ['current_page' => $sessions->currentPage(), 'last_page' => $sessions->lastPage(), 'per_page' => $sessions->perPage(), 'total' => $sessions->total()]
        );
    }

    public function store(StoreSessionRequest $request, string $book): JsonResponse
    {
        $bookModel = $this->book($book);
        $this->authorize('view', $bookModel);
        if ($this->statusValue($bookModel->status) !== 'open') {
            throw new LibroDigitalException('Solo se pueden crear sesiones en un libro abierto.', 'LCD_BOOK_NOT_OPEN', 409);
        }
        $data = $request->validated();
        $school = $bookModel->school()->firstOrFail();
        $group = $bookModel->teachingGroups()->firstOrFail();
        $teacher = Staff::query()->findOrFail($data['teacher_staff_id']);
        $this->assertTeacherMayOperate($request, $bookModel, $group->id, $teacher->id, $data['scheduled_date']);
        $subjectId = (int) ($data['schedule_subject_id'] ?? $group->schedule_subject_id);
        $subject = ScheduleSubject::query()->findOrFail($subjectId);
        $snapshot = RosterSnapshot::query()->where('book_id', $bookModel->id)->where('teaching_group_id', $group->id)
            ->where('status', 'sealed')->whereDate('effective_on', '<=', $data['scheduled_date'])
            ->latest('effective_on')->latest('revision')->first();
        if (! $snapshot) {
            throw new LibroDigitalException('No existe una nómina sellada vigente para la fecha de clase.', 'LCD_ROSTER_SNAPSHOT_REQUIRED', 422);
        }

        [$start, $end] = $this->timestamps($data['scheduled_date'], $data['start_time'], $data['end_time'], $school->timezone);
        $occurrenceKey = $this->canonical->hash([
            'date' => $data['scheduled_date'], 'start' => $start->toIso8601String(), 'end' => $end->toIso8601String(),
            'subject_id' => $subjectId, 'teacher_id' => $teacher->id, 'block_id' => $data['school_day_block_id'] ?? null,
        ]);

        $session = ClassSession::query()->create([
            'school_id' => $bookModel->school_id,
            'book_id' => $bookModel->id,
            'teaching_group_id' => $group->id,
            'academic_year_id' => $bookModel->academic_year_id,
            'regulatory_profile_id' => $bookModel->regulatory_profile_id,
            'roster_snapshot_id' => $snapshot->id,
            'school_day_block_id' => $data['school_day_block_id'] ?? null,
            'schedule_subject_id' => $subjectId,
            'scheduled_teacher_id' => $teacher->id,
            'actual_teacher_id' => $teacher->id,
            'session_date' => $data['scheduled_date'],
            'scheduled_start_at' => $start,
            'scheduled_end_at' => $end,
            'actual_start_at' => $start,
            'actual_end_at' => $end,
            'status' => 'draft',
            'class_type' => $data['modality'] ?? 'regular',
            'teacher_name_snapshot' => $teacher->full_name,
            'subject_snapshot' => $subject->name,
            'course_snapshot' => $group->course_snapshot,
            'observation' => $data['notes'] ?? null,
            'occurrence_key' => $occurrenceKey,
            'revision' => 1,
            'lock_version' => 1,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);
        $this->audit->write('lcd.session.created', 'create', $session, actor: $request->user(), schoolId: $session->school_id, academicYearId: $session->academic_year_id, after: $session->toArray(), request: $request);

        return $this->sessionResponse($request, $session, 201);
    }

    public function show(Request $request, string $session): JsonResponse
    {
        $model = $this->session($session);
        $this->authorize('view', $model);

        return $this->sessionResponse($request, $model);
    }

    public function update(UpdateSessionRequest $request, string $session): JsonResponse
    {
        $model = $this->session($session);
        $this->authorize('update', $model);
        $this->locks->assert($model, $request);
        if (! in_array($this->statusValue($model->status), ['scheduled', 'draft', 'attendance_in_progress'], true)) {
            throw new LibroDigitalException('La sesión ya no admite edición directa.', 'LCD_SESSION_IMMUTABLE', 409);
        }
        $data = $request->validated();
        $school = $model->school()->firstOrFail();
        $date = $data['scheduled_date'] ?? $model->session_date->format('Y-m-d');
        $startTime = $data['start_time'] ?? $model->scheduled_start_at?->setTimezone($school->timezone)->format('H:i');
        $endTime = $data['end_time'] ?? $model->scheduled_end_at?->setTimezone($school->timezone)->format('H:i');
        [$start, $end] = $this->timestamps($date, $startTime, $endTime, $school->timezone);
        $teacher = isset($data['teacher_staff_id']) ? Staff::query()->findOrFail($data['teacher_staff_id']) : $model->actualTeacher()->firstOrFail();
        $this->assertTeacherMayOperate($request, $model->book()->firstOrFail(), $model->teaching_group_id, $teacher->id, $date);

        DB::transaction(function () use ($request, $model, $data, $date, $start, $end, $teacher): void {
            $locked = ClassSession::query()->lockForUpdate()->findOrFail($model->id);
            $locked->forceFill([
                'session_date' => $date,
                'scheduled_start_at' => $start,
                'scheduled_end_at' => $end,
                'actual_start_at' => $start,
                'actual_end_at' => $end,
                'school_day_block_id' => $data['school_day_block_id'] ?? $locked->school_day_block_id,
                'scheduled_teacher_id' => $teacher->id,
                'actual_teacher_id' => $teacher->id,
                'teacher_name_snapshot' => $teacher->full_name,
                'class_type' => $data['modality'] ?? $locked->class_type,
                'observation' => array_key_exists('notes', $data) ? $data['notes'] : $locked->observation,
                'occurrence_key' => $this->canonical->hash([
                    'date' => $date, 'start' => $start->toIso8601String(), 'end' => $end->toIso8601String(),
                    'subject_id' => $locked->schedule_subject_id, 'teacher_id' => $teacher->id,
                    'block_id' => $data['school_day_block_id'] ?? $locked->school_day_block_id,
                ]),
                'revision' => $locked->revision + 1,
                'lock_version' => $locked->lock_version + 1,
                'updated_by' => $request->user()->id,
            ])->save();
        }, 3);

        $fresh = $model->fresh();
        $this->audit->write('lcd.session.updated', 'update', $fresh, actor: $request->user(), schoolId: $fresh->school_id, academicYearId: $fresh->academic_year_id, after: $fresh->toArray(), request: $request, entityRevision: $fresh->revision);

        return $this->sessionResponse($request, $fresh);
    }

    public function cancel(CancelSessionRequest $request, string $session): JsonResponse
    {
        $model = $this->session($session);
        $this->authorize('update', $model);
        $this->locks->assert($model, $request);
        if (! $this->workflows->can('session', $this->statusValue($model->status), 'cancelled')) {
            throw new LibroDigitalException('La sesión no se puede anular en su estado actual.', 'LCD_SESSION_CANCEL_INVALID', 409);
        }
        DB::transaction(function () use ($request, $model): void {
            DB::table('lcd_session_cancellations')->insert([
                'class_session_id' => $model->id,
                'reason_code' => $request->input('reason_code', 'other'),
                'reason' => $request->string('reason')->toString(),
                'cancelled_by' => $request->user()->id,
                'cancelled_at' => now('UTC'),
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ]);
            ClassSession::query()->whereKey($model->id)->update([
                'status' => 'cancelled', 'revision' => $model->revision + 1, 'lock_version' => $model->lock_version + 1,
                'updated_by' => $request->user()->id, 'updated_at' => now('UTC'),
            ]);
        }, 3);
        $fresh = $model->fresh();
        $this->audit->write('lcd.session.cancelled', 'cancel', $fresh, actor: $request->user(), schoolId: $fresh->school_id, academicYearId: $fresh->academic_year_id, reason: $request->input('reason'), request: $request, entityRevision: $fresh->revision);

        return $this->sessionResponse($request, $fresh);
    }

    public function prepareSignature(Request $request, string $session): JsonResponse
    {
        $request->validate(['lock_version' => ['required', 'integer', 'min:1']]);
        $model = $this->session($session);
        $this->authorize('sign', $model);
        $prepared = $this->signatures->prepare($model, $request->user(), (int) $request->input('lock_version'), $request);

        return $this->sessionResponse($request, $prepared);
    }

    public function sign(SignSessionRequest $request, string $session): JsonResponse
    {
        $model = $this->session($session);
        $this->authorize('sign', $model);
        $correlationId = (string) $request->attributes->get('lcd_correlation_id');
        $signature = $this->signatures->sign(
            $model,
            $request->user(),
            $request->string('otp')->toString(),
            $request->string('timestamp')->toString(),
            $correlationId,
            (int) $request->input('lock_version'),
            $request,
        );
        $fresh = $model->fresh();

        return $this->dataResponse([
            'signature' => $this->signatureData($signature),
            'session' => (new ClassSessionResource($fresh->load($this->relations())->loadCount('attendance')))->resolve($request),
        ], version: $fresh->lock_version);
    }

    private function assertTeacherMayOperate(Request $request, Book $book, int $groupId, int $teacherId, string $date): void
    {
        $user = $request->user();
        $override = $user->isSuperAdmin() || $user->hasPermission('libro_digital.books.manage') || $user->hasPermission('libro_digital.closures.manage');
        if (! $override && (int) $user->staff_id !== $teacherId) {
            throw new LibroDigitalException('Solo puedes operar sesiones asignadas a tu ficha docente.', 'LCD_SESSION_TEACHER_FORBIDDEN', 403);
        }
        $assigned = TeacherAssignment::query()->where('book_id', $book->id)->where('teaching_group_id', $groupId)
            ->where('staff_id', $teacherId)->where('active', true)->whereDate('valid_from', '<=', $date)
            ->where(fn (Builder $query) => $query->whereNull('valid_to')->orWhereDate('valid_to', '>=', $date))->exists();
        if (! $assigned) {
            throw new LibroDigitalException('El docente no tiene una asignación vigente para este libro.', 'LCD_TEACHER_ASSIGNMENT_REQUIRED', 422);
        }
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function timestamps(string $date, string $start, string $end, string $timezone): array
    {
        $startAt = Carbon::createFromFormat('Y-m-d H:i', $date.' '.$start, $timezone)->utc();
        $endAt = Carbon::createFromFormat('Y-m-d H:i', $date.' '.$end, $timezone)->utc();
        if ($endAt->lessThanOrEqualTo($startAt)) {
            throw new LibroDigitalException('La hora de término debe ser posterior al inicio.', 'LCD_SESSION_TIME_INVALID');
        }

        return [$startAt, $endAt];
    }

    private function sessionResponse(Request $request, ClassSession $session, int $status = 200): JsonResponse
    {
        $session->load($this->relations())->loadCount('attendance');

        return $this->dataResponse((new ClassSessionResource($session))->resolve($request), $status, $session->lock_version);
    }

    /** @return array<int, string> */
    private function relations(): array
    {
        return ['schoolDayBlock', 'scheduleEvent', 'actualTeacher', 'rosterSnapshot.items'];
    }

    /** @return array<string, mixed> */
    private function signatureData(TeacherSignature $signature): array
    {
        return [
            'id' => $signature->id,
            'public_id' => $signature->public_id,
            'status' => $this->statusValue($signature->status),
            'verified_at' => $signature->verified_at?->toIso8601String(),
            'payload_hash' => $signature->payload_hash,
            'signature_hash' => $signature->signature_hash,
            'provider' => $signature->verifier_provider,
        ];
    }
}
