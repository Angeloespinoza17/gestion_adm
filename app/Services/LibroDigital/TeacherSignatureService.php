<?php

namespace App\Services\LibroDigital;

use App\Contracts\LibroDigital\TeacherIdentityVerifier;
use App\DTO\LibroDigital\IdentityVerificationData;
use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Exceptions\LibroDigital\VersionConflictException;
use App\Models\LibroDigital\ClassSession;
use App\Models\LibroDigital\SignatureAttempt;
use App\Models\LibroDigital\TeacherAssignment;
use App\Models\LibroDigital\TeacherSignature;
use App\Models\User;
use App\ValueObjects\LibroDigital\ChileanRun;
use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class TeacherSignatureService
{
    public function __construct(
        private readonly CanonicalJson $canonical,
        private readonly SessionAttendanceService $attendance,
        private readonly SignaturePolicyResolver $policyResolver,
        private readonly TeacherIdentityVerifier $verifier,
        private readonly AuditEventWriter $audit,
        private readonly FeatureFlagService $features,
    ) {}

    public function prepare(ClassSession $session, User $actor, int $expectedLockVersion, ?Request $request = null): ClassSession
    {
        $policy = $this->policyResolver->for($session->regulatoryProfile);
        if ($policy['requires_complete_attendance']) {
            $this->attendance->assertComplete($session);
        }
        if ($policy['requires_pedagogical_record'] && blank($session->objective_summary) && blank($session->content_summary) && blank($session->activity_summary)) {
            throw new LibroDigitalException(
                'Registra el objetivo, contenido o actividad pedagógica antes de preparar la firma.',
                'LCD_LESSON_RECORD_REQUIRED',
                422,
            );
        }

        $prepared = DB::transaction(function () use ($session, $actor, $expectedLockVersion): ClassSession {
            $locked = ClassSession::query()->lockForUpdate()->findOrFail($session->id);
            if ((int) $locked->lock_version !== $expectedLockVersion) {
                throw new VersionConflictException($expectedLockVersion, (int) $locked->lock_version);
            }
            if (! in_array($this->enumValue($locked->status), ['draft', 'attendance_in_progress', 'amended', 'ready_to_sign'], true)) {
                throw new LibroDigitalException('La sesión no está en un estado preparable para firma.', 'LCD_SIGNATURE_STATE_INVALID', 409);
            }

            $locked->forceFill([
                'status' => 'ready_to_sign',
                'canonical_hash' => $this->payloadHash($locked),
                'lock_version' => ((int) $locked->lock_version) + 1,
                'updated_by' => $actor->id,
            ])->save();

            return $locked->fresh();
        }, 3);

        $this->audit->write(
            'lcd.session.signature_prepared',
            'prepare_signature',
            $prepared,
            actor: $actor,
            schoolId: $prepared->school_id,
            academicYearId: $prepared->academic_year_id,
            after: $prepared->only(['public_id', 'status', 'canonical_hash', 'revision', 'lock_version']),
            request: $request,
            entityRevision: (int) $prepared->revision,
        );

        return $prepared;
    }

    public function sign(
        ClassSession $session,
        User $actor,
        string $otp,
        string $timestamp,
        string $correlationId,
        int $expectedLockVersion,
        ?Request $request = null,
    ): TeacherSignature {
        if (! $this->features->enabled('lcd_identity_verifier_enabled', $session->school_id) && ! app()->environment('testing')) {
            throw new LibroDigitalException('La verificación de identidad no está habilitada para este establecimiento.', 'LCD_IDENTITY_FEATURE_DISABLED', 503);
        }
        $staff = $actor->staff;
        if (! $staff) {
            throw new LibroDigitalException('La cuenta no está vinculada a una ficha de funcionario.', 'LCD_SIGNER_STAFF_REQUIRED', 403);
        }

        try {
            $run = new ChileanRun((string) $staff->rut);
        } catch (Throwable) {
            throw new LibroDigitalException('La ficha docente no tiene un RUN válido.', 'LCD_SIGNER_RUN_INVALID', 422);
        }

        $assignment = $this->assignmentFor($session, $actor);
        $this->assertTimestampPolicy($timestamp);
        $this->assertNotReplay($session, $correlationId);
        $rateKey = 'lcd:signature:'.hash('sha256', $actor->id.'|'.$session->id);
        $maxAttempts = (int) config('libro_digital.identity_verifier.rate_limit_attempts', 5);
        if (RateLimiter::tooManyAttempts($rateKey, $maxAttempts)) {
            throw new LibroDigitalException(
                'Demasiados intentos de firma. Espera antes de volver a intentar.',
                'LCD_SIGNATURE_RATE_LIMITED',
                429,
                [['retry_after_seconds' => RateLimiter::availableIn($rateKey)]],
            );
        }
        RateLimiter::hit($rateKey, (int) config('libro_digital.identity_verifier.rate_limit_decay_seconds', 300));
        $payloadHash = $this->claimForSigning($session, $actor, $expectedLockVersion);

        $result = $this->verifier->verify(new IdentityVerificationData(
            run: (string) $run,
            otp: $otp,
            timestamp: $timestamp,
            correlationId: $correlationId,
            payloadHash: $payloadHash,
        ));

        if (! $result->verified) {
            $this->recordAttempt($session, $staff->id, null, $result->status, $result->responseCode, $correlationId, $request);
            $this->releaseSigningClaim($session->id, $actor->id, $payloadHash);
            throw new LibroDigitalException(
                $result->safeMessage ?: 'No fue posible verificar la identidad docente.',
                $result->status === 'unavailable' ? 'LCD_IDENTITY_VERIFIER_UNAVAILABLE' : 'LCD_IDENTITY_REJECTED',
                $result->status === 'unavailable' ? 503 : 422,
            );
        }

        $signature = DB::transaction(function () use ($session, $actor, $assignment, $staff, $run, $payloadHash, $result, $correlationId, $request): TeacherSignature {
            $locked = ClassSession::query()->lockForUpdate()->findOrFail($session->id);
            $currentHash = $this->payloadHash($locked);
            if ($this->enumValue($locked->status) !== 'signing' || ! hash_equals($payloadHash, $currentHash)) {
                throw new LibroDigitalException('La sesión cambió durante la verificación. Debe prepararse nuevamente.', 'LCD_SIGNATURE_PAYLOAD_CHANGED', 409);
            }

            $signature = TeacherSignature::query()->create([
                'school_id' => $locked->school_id,
                'regulatory_profile_id' => $locked->regulatory_profile_id,
                'teacher_assignment_id' => $assignment->id,
                'staff_id' => $staff->id,
                'user_id' => $actor->id,
                'signable_type' => $locked::class,
                'signable_id' => $locked->id,
                'signable_revision' => $locked->revision,
                'verifier_provider' => $this->verifier->provider(),
                'verifier_transaction_id' => $result->transactionId,
                'correlation_id' => $correlationId,
                'status' => 'verified',
                'verified_at' => now('UTC'),
                'payload_hash' => $payloadHash,
                'signature_hash' => hash_hmac('sha256', implode('|', [$payloadHash, $result->responseHash, $correlationId]), (string) config('app.key')),
                'signature_algorithm' => 'HMAC-SHA256 application integrity seal + EDE identity verification',
                'verifier_response_code' => $result->responseCode,
                'verifier_response_hash' => $result->responseHash,
                'signer_identifier_encrypted' => Crypt::encryptString((string) $run),
                'ip_address_encrypted' => $request?->ip() ? Crypt::encryptString((string) $request->ip()) : null,
                'user_agent_hash' => $request?->userAgent() ? hash('sha256', (string) $request->userAgent()) : null,
            ]);

            $locked->forceFill([
                'status' => 'signed',
                'canonical_hash' => $payloadHash,
                'lock_version' => ((int) $locked->lock_version) + 1,
                'updated_by' => $actor->id,
            ])->save();

            return $signature;
        }, 3);

        $this->recordAttempt($session, $staff->id, $signature->id, 'verified', null, $correlationId, $request);
        $this->audit->write(
            'lcd.session.signed',
            'sign',
            $session,
            actor: $actor,
            schoolId: $session->school_id,
            academicYearId: $session->academic_year_id,
            after: $signature->only(['public_id', 'status', 'payload_hash', 'signature_hash', 'verifier_provider', 'verifier_response_code']),
            request: $request,
            entityRevision: (int) $session->revision,
            correlationId: $correlationId,
        );

        return $signature;
    }

    private function assertNotReplay(ClassSession $session, string $correlationId): void
    {
        $used = TeacherSignature::query()->where('correlation_id', $correlationId)->exists()
            || SignatureAttempt::query()->where('correlation_id', $correlationId)->exists();
        if ($used) {
            throw new LibroDigitalException('La correlación de firma ya fue utilizada.', 'LCD_SIGNATURE_REPLAY_DETECTED', 409);
        }
    }

    public function payloadHash(ClassSession $session): string
    {
        $session->refresh();
        $sessionPayload = $session->only([
            'public_id', 'book_id', 'teaching_group_id', 'academic_year_id', 'regulatory_profile_id',
            'roster_snapshot_id', 'schedule_subject_id', 'actual_teacher_id', 'session_date',
            'actual_start_at', 'actual_end_at', 'class_type', 'teacher_name_snapshot', 'subject_snapshot',
            'course_snapshot', 'objective_summary', 'content_summary', 'activity_summary', 'observation', 'revision',
        ]);
        $attendance = DB::table('lcd_session_attendance')->where('class_session_id', $session->id)
            ->orderBy('student_profile_id')->get([
                'roster_snapshot_item_id', 'student_profile_id', 'student_enrollment_id', 'status', 'arrival_at',
                'departure_at', 'justification_status', 'source', 'notes', 'revision', 'record_hash',
            ])->map(fn ($row) => (array) $row)->all();
        $lesson = [];
        foreach (['topics', 'objectives', 'activities', 'resources', 'observations', 'cancellations'] as $child) {
            $table = 'lcd_session_'.$child;
            $lesson[$child] = DB::table($table)->where('class_session_id', $session->id)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }

        return $this->canonical->hash([
            'session' => $sessionPayload,
            'roster_snapshot_hash' => $session->rosterSnapshot()->value('snapshot_hash'),
            'attendance' => $attendance,
            'lesson_record' => $lesson,
        ]);
    }

    private function assignmentFor(ClassSession $session, User $actor): TeacherAssignment
    {
        $staffId = (int) $actor->staff_id;
        if (! in_array($staffId, array_filter([(int) $session->actual_teacher_id, (int) $session->scheduled_teacher_id, (int) $session->substitute_teacher_id]), true)) {
            throw new LibroDigitalException('Solo el docente responsable o su reemplazo vigente puede firmar esta sesión.', 'LCD_SIGNER_NOT_RESPONSIBLE', 403);
        }

        $assignment = TeacherAssignment::query()
            ->where('school_id', $session->school_id)
            ->where('book_id', $session->book_id)
            ->where('teaching_group_id', $session->teaching_group_id)
            ->where('staff_id', $staffId)
            ->where('active', true)
            ->whereDate('valid_from', '<=', $session->session_date)
            ->where(fn ($query) => $query->whereNull('valid_to')->orWhereDate('valid_to', '>=', $session->session_date))
            ->where(fn ($query) => $query->whereNull('schedule_subject_id')->orWhere('schedule_subject_id', $session->schedule_subject_id))
            ->first();

        if (! $assignment) {
            throw new LibroDigitalException('No existe una asignación docente vigente para esta clase.', 'LCD_TEACHER_ASSIGNMENT_REQUIRED', 403);
        }

        return $assignment;
    }

    private function claimForSigning(ClassSession $session, User $actor, int $expectedLockVersion): string
    {
        return DB::transaction(function () use ($session, $actor, $expectedLockVersion): string {
            $locked = ClassSession::query()->lockForUpdate()->findOrFail($session->id);
            if ((int) $locked->lock_version !== $expectedLockVersion) {
                throw new VersionConflictException($expectedLockVersion, (int) $locked->lock_version);
            }
            if ($this->enumValue($locked->status) !== 'ready_to_sign') {
                throw new LibroDigitalException('La sesión debe prepararse antes de firmar.', 'LCD_SIGNATURE_NOT_PREPARED', 409);
            }

            $payloadHash = $this->payloadHash($locked);
            if (! $locked->canonical_hash || ! hash_equals((string) $locked->canonical_hash, $payloadHash)) {
                throw new LibroDigitalException('La sesión cambió desde su preparación.', 'LCD_SIGNATURE_PAYLOAD_CHANGED', 409);
            }
            $locked->forceFill(['status' => 'signing', 'lock_version' => ((int) $locked->lock_version) + 1, 'updated_by' => $actor->id])->save();

            return $payloadHash;
        }, 3);
    }

    private function releaseSigningClaim(int $sessionId, int $actorId, string $payloadHash): void
    {
        DB::transaction(function () use ($sessionId, $actorId, $payloadHash): void {
            $locked = ClassSession::query()->lockForUpdate()->find($sessionId);
            if ($locked && $this->enumValue($locked->status) === 'signing' && hash_equals($payloadHash, $this->payloadHash($locked))) {
                $locked->forceFill(['status' => 'ready_to_sign', 'lock_version' => ((int) $locked->lock_version) + 1, 'updated_by' => $actorId])->save();
            }
        }, 3);
    }

    private function recordAttempt(ClassSession $session, int $staffId, ?int $signatureId, string $result, ?string $failureCode, string $correlationId, ?Request $request): void
    {
        SignatureAttempt::query()->create([
            'teacher_signature_id' => $signatureId,
            'staff_id' => $staffId,
            'signable_type' => $session::class,
            'signable_id' => $session->id,
            'verifier_provider' => $this->verifier->provider(),
            'correlation_id' => $correlationId,
            'result' => $result,
            'failure_code' => $failureCode,
            'failure_detail_sanitized' => null,
            'ip_address_encrypted' => $request?->ip() ? Crypt::encryptString((string) $request->ip()) : null,
            'user_agent_hash' => $request?->userAgent() ? hash('sha256', (string) $request->userAgent()) : null,
            'attempted_at' => now('UTC'),
        ]);
    }

    private function assertTimestampPolicy(string $timestamp): void
    {
        if (! preg_match('/(?:Z|[+\-]\d{2}:?\d{2})$/', trim($timestamp))) {
            throw new LibroDigitalException('El timestamp de firma debe incluir zona horaria.', 'LCD_SIGNATURE_TIMEZONE_REQUIRED');
        }

        try {
            $parsed = Carbon::parse($timestamp);
        } catch (Throwable) {
            throw new LibroDigitalException('El timestamp de firma no es válido.', 'LCD_SIGNATURE_TIMESTAMP_INVALID');
        }

        $configured = config('libro_digital.identity_verifier.max_timestamp_skew_seconds');
        if (filled($configured) && abs(now('UTC')->diffInSeconds($parsed->utc(), false)) > (int) $configured) {
            throw new LibroDigitalException('El timestamp de firma está fuera de la ventana configurada.', 'LCD_SIGNATURE_TIMESTAMP_STALE');
        }
    }

    private function enumValue(mixed $value): string
    {
        return $value instanceof BackedEnum ? (string) $value->value : (string) $value;
    }
}
