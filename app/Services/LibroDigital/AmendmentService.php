<?php

namespace App\Services\LibroDigital;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Models\LibroDigital\AmendmentApproval;
use App\Models\LibroDigital\AmendmentRequest;
use App\Models\LibroDigital\Assessment;
use App\Models\LibroDigital\AttendanceReconciliation;
use App\Models\LibroDigital\Book;
use App\Models\LibroDigital\ClassSession;
use App\Models\LibroDigital\ClosureReopening;
use App\Models\LibroDigital\DailyAttendanceClosure;
use App\Models\LibroDigital\EdeExport;
use App\Models\LibroDigital\FiscalizationPackage;
use App\Models\LibroDigital\GradeClosure;
use App\Models\LibroDigital\MonthlyAttendanceClosure;
use App\Models\LibroDigital\RecordRevision;
use App\Models\LibroDigital\SessionAttendance;
use App\Models\LibroDigital\StudentResult;
use App\Models\LibroDigital\TeacherSignature;
use App\Models\User;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AmendmentService
{
    public function __construct(
        private readonly CanonicalJson $canonical,
        private readonly AuditEventWriter $audit,
    ) {}

    /** @param array<string, mixed> $proposed */
    public function request(Model $record, int $schoolId, ?Book $book, User $actor, array $proposed, string $reason, ?string $section = null, ?string $field = null, ?Request $request = null): AmendmentRequest
    {
        if (AmendmentRequest::query()->where('amendable_type', $record::class)->where('amendable_id', $record->getKey())
            ->whereIn('status', ['requested', 'under_review', 'approved'])->exists()) {
            throw new LibroDigitalException('Ya existe una corrección pendiente para este registro.', 'LCD_AMENDMENT_PENDING_EXISTS', 409);
        }
        $currentRevision = (int) ($record->getAttribute('revision') ?? 1);
        $before = $record->attributesToArray();
        $requiresSignature = $record instanceof ClassSession || $record instanceof SessionAttendance;
        $amendment = DB::transaction(function () use ($record, $schoolId, $book, $actor, $proposed, $reason, $section, $field, $currentRevision, $before, $requiresSignature): AmendmentRequest {
            $amendment = AmendmentRequest::query()->create([
                'school_id' => $schoolId, 'book_id' => $book?->id,
                'amendable_type' => $record::class, 'amendable_id' => $record->getKey(),
                'original_revision' => $currentRevision, 'section' => $section, 'field' => $field,
                'before_snapshot' => $before, 'proposed_snapshot' => $proposed, 'reason' => $reason,
                'status' => 'requested', 'requires_signature' => $requiresSignature, 'requested_by' => $actor->id, 'requested_at' => now('UTC'),
            ]);
            AmendmentApproval::query()->create([
                'amendment_request_id' => $amendment->id, 'approval_order' => 1,
                'required_role' => 'direccion', 'decision' => 'pending',
            ]);

            return $amendment;
        }, 3);
        $this->audit->write('lcd.amendment.requested', 'request_amendment', $record, actor: $actor, schoolId: $schoolId, academicYearId: $book?->academic_year_id, before: $before, after: $proposed, reason: $reason, request: $request, entityRevision: $currentRevision);

        return $amendment;
    }

    public function review(AmendmentRequest $amendment, User $reviewer, bool $approve, ?string $note = null, ?Request $request = null): AmendmentRequest
    {
        return DB::transaction(function () use ($amendment, $reviewer, $approve, $note, $request): AmendmentRequest {
            $locked = AmendmentRequest::query()->lockForUpdate()->findOrFail($amendment->id);
            if ((int) $locked->requested_by === (int) $reviewer->id) {
                throw new LibroDigitalException('La persona solicitante no puede revisar su propia corrección.', 'LCD_AMENDMENT_SEPARATION_OF_DUTIES', 403);
            }
            if (! in_array($locked->status instanceof BackedEnum ? $locked->status->value : $locked->status, ['requested', 'under_review'], true)) {
                throw new LibroDigitalException('La solicitud ya fue revisada.', 'LCD_AMENDMENT_ALREADY_REVIEWED', 409);
            }
            $decision = $approve ? 'approved' : 'rejected';
            if ($approve) {
                $this->assertReinforcedApprovalReady($locked);
            }
            $approval = $locked->approvals()->where('decision', 'pending')->orderBy('approval_order')->firstOrFail();
            $decisionPayload = ['amendment' => $locked->public_id, 'decision' => $decision, 'reviewer' => $reviewer->id, 'note' => $note, 'at' => now('UTC')->toIso8601String()];
            $approval->forceFill(['decision' => $decision, 'decided_by' => $reviewer->id, 'decided_at' => now('UTC'), 'comment' => $note, 'decision_hash' => $this->canonical->hash($decisionPayload)])->save();
            $locked->forceFill(['status' => $decision, 'reviewed_by' => $reviewer->id, 'reviewed_at' => now('UTC'), 'review_note' => $note])->save();
            $this->audit->write('lcd.amendment.'.$decision, 'review_amendment', $locked, actor: $reviewer, schoolId: $locked->school_id, academicYearId: $locked->book?->academic_year_id, after: $decisionPayload, reason: $note, request: $request);

            return $locked->fresh();
        }, 3);
    }

    public function apply(AmendmentRequest $amendment, User $actor, ?Request $request = null): RecordRevision
    {
        return DB::transaction(function () use ($amendment, $actor, $request): RecordRevision {
            $locked = AmendmentRequest::query()->lockForUpdate()->with('amendable')->findOrFail($amendment->id);
            if ((int) $locked->requested_by === (int) $actor->id || (int) $locked->reviewed_by === (int) $actor->id) {
                throw new LibroDigitalException('La aplicación exige una tercera persona distinta de quien solicitó y revisó.', 'LCD_AMENDMENT_SEPARATION_OF_DUTIES', 403);
            }
            if (($locked->status instanceof BackedEnum ? $locked->status->value : $locked->status) !== 'approved') {
                throw new LibroDigitalException('La enmienda debe estar aprobada antes de aplicarse.', 'LCD_AMENDMENT_NOT_APPROVED', 409);
            }
            $record = $locked->amendable;
            if ($record) {
                $record = $record::query()->lockForUpdate()->find($record->getKey());
            }
            if (! $record || (int) ($record->getAttribute('revision') ?? 1) !== (int) $locked->original_revision) {
                throw new LibroDigitalException('El registro cambió desde la solicitud; debe revisarse otra vez.', 'LCD_AMENDMENT_REVISION_CONFLICT', 409);
            }
            $newRevision = ((int) $locked->original_revision) + 1;
            $this->applyChanges($record, $locked->proposed_snapshot ?? [], $newRevision, $actor, $locked);
            $record->refresh();
            $payload = $record->attributesToArray();
            $previous = RecordRevision::query()->where('revisable_type', $record::class)->where('revisable_id', $record->getKey())->latest('revision')->first();
            $revision = RecordRevision::query()->create([
                'school_id' => $locked->school_id, 'amendment_request_id' => $locked->id,
                'previous_revision_id' => $previous?->id, 'revisable_type' => $record::class,
                'revisable_id' => $record->getKey(), 'revision' => $newRevision, 'payload' => $payload,
                'payload_hash' => $this->canonical->hash($payload), 'reason' => $locked->reason,
                'created_by' => $actor->id,
            ]);
            $locked->forceFill(['status' => 'applied', 'applied_revision' => $newRevision, 'applied_at' => now('UTC')])->save();
            if ($locked->book_id) {
                EdeExport::query()->where('book_id', $locked->book_id)
                    ->whereNotIn('status', ['requested', 'preflight_failed', 'stale', 'revoked'])
                    ->update(['status' => 'stale', 'stale_at' => now('UTC')]);
                $exportIds = EdeExport::query()->where('book_id', $locked->book_id)->pluck('id');
                FiscalizationPackage::query()->where('school_id', $locked->school_id)
                    ->where(fn ($query) => $query->whereIn('ede_export_id', $exportIds)->orWhereNull('ede_export_id'))
                    ->whereIn('status', ['queued', 'generated', 'released'])
                    ->update(['status' => 'stale', 'revoked_at' => now('UTC'), 'revocation_reason' => 'Enmienda aplicada al libro fuente.']);
            }
            $this->audit->write('lcd.amendment.applied', 'apply_amendment', $record, actor: $actor, schoolId: $locked->school_id, academicYearId: $locked->book?->academic_year_id, before: $locked->before_snapshot, after: $payload, reason: $locked->reason, request: $request, entityRevision: $newRevision);

            return $revision;
        }, 3);
    }

    private function assertReinforcedApprovalReady(AmendmentRequest $amendment): void
    {
        if (! $amendment->book_id) {
            return;
        }
        $record = $amendment->amendable()->first();
        $session = $record instanceof ClassSession
            ? $record
            : ($record instanceof SessionAttendance ? $record->classSession()->first() : null);
        $hasSignature = $session && TeacherSignature::query()->where('signable_type', ClassSession::class)
            ->where('signable_id', $session->id)->where('status', 'verified')->exists();
        $hasDailyClosure = $session && DailyAttendanceClosure::query()->where('book_id', $session->book_id)
            ->where('teaching_group_id', $session->teaching_group_id)->whereDate('closure_date', $session->session_date)
            ->whereIn('status', ['closed', 'reclosed'])->exists();
        $hasMonthlyClosure = $session && MonthlyAttendanceClosure::query()->where('book_id', $session->book_id)
            ->where('teaching_group_id', $session->teaching_group_id)->where('month', $session->session_date->month)
            ->whereIn('status', ['closed', 'reclosed'])->exists();
        $hasExport = EdeExport::query()->where('book_id', $amendment->book_id)
            ->whereNotIn('status', ['requested', 'preflight_failed', 'stale', 'revoked'])->exists();
        $hasReconciliation = AttendanceReconciliation::query()->where('book_id', $amendment->book_id)->exists();
        $hasFiscalization = FiscalizationPackage::query()->where('school_id', $amendment->school_id)
            ->whereIn('status', ['queued', 'generated', 'released'])->exists();

        if ($hasSignature || $hasDailyClosure || $hasMonthlyClosure || $hasExport || $hasReconciliation || $hasFiscalization) {
            $approval = $amendment->approvals()->where('decision', 'pending')->orderBy('approval_order')->first();
            if (! $approval || $approval->required_role !== 'direccion') {
                throw new LibroDigitalException('La corrección requiere autorización reforzada de dirección.', 'LCD_AMENDMENT_REINFORCED_APPROVAL_REQUIRED', 409);
            }
        }
    }

    /** @param array<string, mixed> $proposed */
    private function applyChanges(Model $record, array $proposed, int $newRevision, User $actor, AmendmentRequest $amendment): void
    {
        if ($record instanceof ClassSession) {
            $record->forceFill([
                ...$proposed,
                'status' => 'amended',
                'canonical_hash' => null,
                'revision' => $newRevision,
                'lock_version' => ((int) $record->lock_version) + 1,
                'updated_by' => $actor->id,
            ])->save();
            $this->reopenAttendanceClosures($record, $actor, $amendment);

            return;
        }

        if ($record instanceof SessionAttendance) {
            $recordedAt = now('UTC');
            $record->forceFill([
                ...$proposed,
                'recorded_by' => $actor->id,
                'recorded_at' => $recordedAt,
                'revision' => $newRevision,
            ])->save();
            $record->forceFill(['record_hash' => $this->canonical->hash($this->attendanceHashPayload($record))])->save();
            $session = ClassSession::query()->lockForUpdate()->findOrFail($record->class_session_id);
            $session->forceFill([
                'status' => 'amended',
                'canonical_hash' => null,
                'revision' => ((int) $session->revision) + 1,
                'lock_version' => ((int) $session->lock_version) + 1,
                'updated_by' => $actor->id,
            ])->save();
            $this->reopenAttendanceClosures($session, $actor, $amendment);

            return;
        }

        if ($record instanceof Assessment) {
            $wasClosed = in_array($this->value($record->status), ['closed', 'amended'], true);
            $record->forceFill([
                ...$proposed,
                'status' => $wasClosed ? 'amended' : $this->value($record->status),
                'revision' => $newRevision,
                'lock_version' => ((int) $record->lock_version) + 1,
                'updated_by' => $actor->id,
            ])->save();
            if ($wasClosed) {
                $this->reopenGradeClosures($record, $actor, $amendment);
            }

            return;
        }

        if ($record instanceof StudentResult) {
            $assessment = Assessment::query()->lockForUpdate()->findOrFail($record->assessment_id);
            $recordedAt = now('UTC');
            $record->forceFill([
                ...$proposed,
                'recorded_by' => $actor->id,
                'recorded_at' => $recordedAt,
                'revision' => $newRevision,
            ])->save();
            $record->forceFill(['record_hash' => $this->canonical->hash($this->resultHashPayload($record, $assessment))])->save();
            $assessment->forceFill([
                'status' => 'amended',
                'revision' => ((int) $assessment->revision) + 1,
                'lock_version' => ((int) $assessment->lock_version) + 1,
                'updated_by' => $actor->id,
            ])->save();
            $this->reopenGradeClosures($assessment, $actor, $amendment);

            return;
        }

        $record->forceFill([...$proposed, 'revision' => $newRevision])->save();
    }

    private function reopenAttendanceClosures(ClassSession $session, User $actor, AmendmentRequest $amendment): void
    {
        $daily = DailyAttendanceClosure::query()
            ->where('book_id', $session->book_id)
            ->where('teaching_group_id', $session->teaching_group_id)
            ->whereDate('closure_date', $session->session_date)
            ->orderByDesc('revision')->lockForUpdate()->first();
        if ($daily && in_array($this->value($daily->status), ['closed', 'reclosed'], true)) {
            $this->reopen($daily, 'daily_attendance', $actor, $amendment);
        }

        $month = (int) $session->session_date->month;
        $monthly = MonthlyAttendanceClosure::query()
            ->where('book_id', $session->book_id)
            ->where('teaching_group_id', $session->teaching_group_id)
            ->where('month', $month)
            ->orderByDesc('revision')->lockForUpdate()->first();
        if ($monthly && in_array($this->value($monthly->status), ['closed', 'reclosed'], true)) {
            $this->reopen($monthly, 'monthly_attendance', $actor, $amendment);
        }
    }

    private function reopenGradeClosures(Assessment $assessment, User $actor, AmendmentRequest $amendment): void
    {
        $closures = GradeClosure::query()
            ->where('book_id', $assessment->book_id)
            ->where('teaching_group_id', $assessment->teaching_group_id)
            ->where('assessment_period_id', $assessment->assessment_period_id)
            ->where('schedule_subject_id', $assessment->schedule_subject_id)
            ->whereIn('status', ['closed', 'reclosed'])
            ->lockForUpdate()->get();
        foreach ($closures as $closure) {
            $this->reopen($closure, 'grade_assessment', $actor, $amendment);
        }
    }

    private function reopen(Model $closure, string $scope, User $actor, AmendmentRequest $amendment): void
    {
        $closure->forceFill(['status' => 'reopened'])->save();
        ClosureReopening::query()->create([
            'school_id' => $amendment->school_id,
            'book_id' => $amendment->book_id,
            'closable_type' => $closure::class,
            'closable_id' => $closure->getKey(),
            'closed_revision' => (int) $closure->getAttribute('revision'),
            'scope' => $scope,
            'status' => 'reopened',
            'reason' => $amendment->reason,
            'original_snapshot_hash' => (string) $closure->getAttribute('snapshot_hash'),
            'requested_by' => $amendment->requested_by,
            'requested_at' => $amendment->requested_at,
            'approved_by' => $amendment->reviewed_by,
            'approved_at' => $amendment->reviewed_at,
            'reopened_at' => now('UTC'),
        ]);
        $this->audit->write(
            'lcd.closure.reopened',
            'reopen_closure',
            $closure,
            actor: $actor,
            schoolId: (int) $amendment->school_id,
            academicYearId: $amendment->book?->academic_year_id,
            after: ['scope' => $scope, 'revision' => $closure->getAttribute('revision'), 'status' => 'reopened'],
            reason: $amendment->reason,
        );
    }

    /** @return array<string, mixed> */
    private function attendanceHashPayload(SessionAttendance $record): array
    {
        return $record->only([
            'class_session_id', 'roster_snapshot_item_id', 'student_profile_id', 'student_enrollment_id',
            'status', 'arrival_at', 'departure_at', 'justification_status', 'source', 'notes',
            'recorded_by', 'recorded_at', 'revision',
        ]);
    }

    /** @return array<string, mixed> */
    private function resultHashPayload(StudentResult $record, Assessment $assessment): array
    {
        return [
            'assessment_public_id' => $assessment->public_id,
            'student_profile_id' => $record->student_profile_id,
            ...$record->only([
                'status', 'raw_score', 'numeric_value', 'qualitative_value', 'normalized_percentage',
                'absent', 'exempt', 'observation', 'revision', 'recorded_by', 'recorded_at',
            ]),
        ];
    }

    private function value(mixed $value): string
    {
        return $value instanceof BackedEnum ? (string) $value->value : (string) $value;
    }
}
