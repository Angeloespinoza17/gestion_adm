<?php

namespace App\Http\Controllers\LibroDigital;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Exceptions\LibroDigital\VersionConflictException;
use App\Http\Requests\LibroDigital\ReturnEarlyWithdrawalRequest;
use App\Http\Requests\LibroDigital\StoreEarlyWithdrawalRequest;
use App\Models\LibroDigital\EarlyWithdrawal;
use App\Models\LibroDigital\EnrollmentLink;
use App\Models\PorterStudentWithdrawal;
use App\Services\LibroDigital\AuditEventWriter;
use App\Services\LibroDigital\CanonicalJson;
use App\Services\LibroDigital\LibroDigitalAccessContext;
use App\Services\LibroDigital\RecordRevisionWriter;
use App\Services\Porter\PorterStudentContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class EarlyWithdrawalController extends LibroDigitalController
{
    public function __construct(
        LibroDigitalAccessContext $access,
        private readonly PorterStudentContextService $students,
        private readonly CanonicalJson $canonical,
        private readonly RecordRevisionWriter $revisions,
        private readonly AuditEventWriter $audit,
    ) {
        parent::__construct($access);
    }

    public function index(Request $request, string $book): JsonResponse
    {
        $aggregate = $this->book($book);
        $this->authorize('view', $aggregate);
        $this->assertPermission($request, $aggregate->school_id, false);
        $paginator = EarlyWithdrawal::query()->where('book_id', $aggregate->id)
            ->with(['student:id,first_name,last_name,registered_name', 'porterWithdrawal:id,status,reason,person_name,person_relationship'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('occurred_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('occurred_at', '<=', $request->date('date_to')))
            ->latest('occurred_at')->paginate(min(100, max(1, $request->integer('per_page', 25))));

        return $this->collectionResponse($paginator->getCollection()->map(fn (EarlyWithdrawal $withdrawal): array => $this->payload($withdrawal))->all(), [
            'current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(), 'total' => $paginator->total(),
        ]);
    }

    public function store(StoreEarlyWithdrawalRequest $request, string $book): JsonResponse
    {
        $aggregate = $this->book($book);
        $this->authorize('view', $aggregate);
        $this->assertPermission($request, $aggregate->school_id, true);
        if ($this->statusValue($aggregate->status) !== 'open') {
            throw new LibroDigitalException('El libro debe estar abierto para registrar una salida.', 'LCD_BOOK_NOT_OPEN', 409);
        }
        $data = $request->validated();
        $link = EnrollmentLink::query()->where('book_id', $aggregate->id)
            ->where('student_profile_id', $data['student_profile_id'])->where('status', 'active')->first();
        if (! $link) {
            throw new LibroDigitalException('El estudiante no pertenece a la nómina vigente del libro.', 'LCD_WITHDRAWAL_STUDENT_OUTSIDE_ROSTER', 422);
        }
        $student = $link->student()->firstOrFail();
        $authorized = $this->students->resolveAuthorizedPerson($student, ['name' => $data['person_name'], 'rut' => $data['person_rut'] ?? null]);
        $restriction = $this->students->resolvePickupRestriction($student, ['name' => $data['person_name'], 'rut' => $data['person_rut'] ?? null]);
        $requiresAuthorization = (bool) $student->pickup_restriction || $restriction['restricted'] || ! $authorized['authorized'];
        $canOverride = $request->user()->hasPermission('autorizar_retiros_porteria')
            && ($data['approve_override'] ?? false)
            && filled($data['override_reason'] ?? null);
        $occurredAt = filled($data['occurred_at'] ?? null)
            ? Carbon::parse($data['occurred_at'])->utc()
            : now('UTC');
        if ((int) $occurredAt->timezone((string) ($aggregate->school()->value('timezone') ?: config('libro_digital.timezone')))->format('Y') !== (int) $aggregate->year_snapshot) {
            throw new LibroDigitalException('La fecha no pertenece al año del libro.', 'LCD_WITHDRAWAL_DATE_OUTSIDE_YEAR', 422);
        }
        $duplicate = EarlyWithdrawal::query()->where('book_id', $aggregate->id)->where('student_profile_id', $student->id)
            ->whereDate('occurred_at', $occurredAt->toDateString())->whereIn('status', ['recorded', 'authorized', 'observed'])->exists();
        if ($duplicate && ! ($data['force_duplicate_confirmation'] ?? false)) {
            throw new LibroDigitalException('Ya existe una salida vigente para el estudiante en esa fecha.', 'LCD_WITHDRAWAL_DUPLICATE', 422);
        }

        [$lcdWithdrawal, $porter] = DB::transaction(function () use ($request, $aggregate, $link, $student, $data, $authorized, $restriction, $requiresAuthorization, $canOverride, $occurredAt): array {
            $status = $requiresAuthorization ? ($canOverride ? 'autorizado' : 'observado') : 'registrado';
            $porter = PorterStudentWithdrawal::query()->create([
                'student_profile_id' => $student->id,
                'academic_year_id' => $aggregate->academic_year_id,
                'course_section_id' => $link->course_section_id,
                'registered_by' => $request->user()->id,
                'authorized_by' => $canOverride ? $request->user()->id : null,
                'status' => $status,
                'withdrawn_at' => $occurredAt,
                'student_full_name_snapshot' => $link->student_name_snapshot,
                'student_rut_snapshot' => null,
                'academic_year_name_snapshot' => (string) $aggregate->year_snapshot,
                'course_name_snapshot' => $link->course_snapshot,
                'person_name' => $data['person_name'],
                // El flujo LCD conserva este identificador solo dentro del
                // snapshot cifrado; la tabla heredada no recibe una copia plana.
                'person_rut' => null,
                'person_relationship' => $data['person_relationship'],
                'person_phone' => $data['person_phone'] ?? null,
                'reason' => $data['reason'],
                'observations' => $data['observations'] ?? null,
                'person_authorized' => $authorized['authorized'],
                'authorization_source' => $authorized['source'],
                'requires_special_authorization' => $requiresAuthorization,
                'authorization_notes' => $requiresAuthorization ? ($data['override_reason'] ?? $restriction['matched_restriction']['reason'] ?? $student->pickup_restriction_notes) : null,
                'ip_address' => null,
                'user_agent' => null,
                'metadata' => ['lcd_book_public_id' => $aggregate->public_id, 'authorization_source' => $authorized['source']],
            ]);
            if ($requiresAuthorization) {
                $porter->authorizationRequests()->create([
                    'requested_by' => $request->user()->id,
                    'resolved_by' => $canOverride ? $request->user()->id : null,
                    'status' => $canOverride ? 'aprobada' : 'pendiente',
                    'required_permission_slug' => 'autorizar_retiros_porteria',
                    'reason' => $restriction['matched_restriction']['reason'] ?? 'La persona requiere autorización especial.',
                    'requested_at' => now('UTC'), 'resolved_at' => $canOverride ? now('UTC') : null,
                    'resolution_notes' => $data['override_reason'] ?? null,
                    'payload' => ['person_name' => $data['person_name']],
                ]);
            }
            $snapshot = [
                'porter_withdrawal_id' => $porter->id, 'status' => $status,
                'student_profile_id' => $student->id, 'student_name' => $link->student_name_snapshot,
                'course' => $link->course_snapshot, 'occurred_at' => $occurredAt->toIso8601String(),
                'person' => [
                    'name' => $data['person_name'],
                    'rut' => $data['person_rut'] ?? null,
                    'relationship' => $data['person_relationship'],
                    'authorized' => $authorized['authorized'],
                ],
                'reason' => $data['reason'], 'requires_special_authorization' => $requiresAuthorization,
            ];
            $lcd = EarlyWithdrawal::query()->create([
                'school_id' => $aggregate->school_id, 'book_id' => $aggregate->id,
                'academic_year_id' => $aggregate->academic_year_id, 'teaching_group_id' => $link->teaching_group_id,
                'porter_student_withdrawal_id' => $porter->id,
                'student_profile_id' => $student->id, 'student_enrollment_id' => $link->student_enrollment_id,
                'status' => match ($status) {
                    'autorizado' => 'authorized', 'observado' => 'observed', default => 'recorded'
                },
                'occurred_at' => $occurredAt, 'student_name_snapshot' => $link->student_name_snapshot,
                'course_snapshot' => $link->course_snapshot,
                'withdrawal_snapshot_encrypted' => Crypt::encryptString($this->canonical->encode($snapshot)),
                'withdrawal_snapshot_hash' => $this->canonical->hash($snapshot),
                'revision' => 1, 'created_by' => $request->user()->id,
            ]);
            $this->revisions->write($lcd, $lcd->school_id, 1, $request->user(), 'early_withdrawal_created');

            return [$lcd, $porter];
        }, 3);
        $this->audit->write('lcd.early_withdrawal.created', 'create', $lcdWithdrawal, actor: $request->user(), schoolId: $aggregate->school_id, academicYearId: $aggregate->academic_year_id, after: $this->auditPayload($lcdWithdrawal, $porter), request: $request);

        return $this->dataResponse($this->payload($lcdWithdrawal->load(['student', 'porterWithdrawal'])), 201, $lcdWithdrawal->revision);
    }

    public function return(ReturnEarlyWithdrawalRequest $request, string $withdrawal): JsonResponse
    {
        $model = $this->aggregate(EarlyWithdrawal::class, $withdrawal);
        $this->assertPermission($request, $model->school_id, true);
        $expected = (int) ($request->header('If-Match') ?: $request->integer('lock_version'));
        if ($expected !== (int) $model->revision) {
            throw new VersionConflictException($expected, (int) $model->revision);
        }
        $returnedAt = Carbon::parse($request->validated('returned_at'))->utc();
        if ($returnedAt->lessThanOrEqualTo($model->occurred_at)) {
            throw new LibroDigitalException('El retorno debe ser posterior a la salida.', 'LCD_WITHDRAWAL_RETURN_TIME_INVALID', 422);
        }

        DB::transaction(function () use ($request, $model, $returnedAt, $expected): void {
            $locked = EarlyWithdrawal::query()->lockForUpdate()->findOrFail($model->id);
            if ((int) $locked->revision !== $expected || $locked->returned_at) {
                throw new VersionConflictException($expected, (int) $locked->revision);
            }
            $snapshot = ['returned_at' => $returnedAt->toIso8601String(), 'reason' => $request->validated('reason'), 'actor_user_id' => $request->user()->id];
            $locked->forceFill([
                'status' => 'returned', 'returned_at' => $returnedAt,
                'return_snapshot_encrypted' => Crypt::encryptString($this->canonical->encode($snapshot)),
                'return_snapshot_hash' => $this->canonical->hash($snapshot),
                'returned_by' => $request->user()->id, 'revision' => $locked->revision + 1,
            ])->save();
            $this->revisions->write($locked, $locked->school_id, $locked->revision, $request->user(), 'early_withdrawal_returned');
        }, 3);
        $fresh = $model->fresh();
        $this->audit->write('lcd.early_withdrawal.returned', 'return', $fresh, actor: $request->user(), schoolId: $fresh->school_id, academicYearId: $fresh->academic_year_id, after: ['status' => 'returned', 'returned_at' => $fresh->returned_at?->toIso8601String()], reason: $request->validated('reason'), request: $request, entityRevision: $fresh->revision);

        return $this->dataResponse($this->payload($fresh->load(['student', 'porterWithdrawal'])), version: $fresh->revision);
    }

    private function assertPermission(Request $request, int $schoolId, bool $manage): void
    {
        $allowed = $manage
            ? ($request->user()->hasPermission('libro_digital.withdrawals.manage') || $request->user()->hasPermission('registrar_retiro_porteria'))
            : ($request->user()->hasPermission('libro_digital.withdrawals.view') || $request->user()->hasPermission('libro_digital.withdrawals.manage') || $request->user()->hasPermission('ver_historial_porteria'));
        abort_unless($allowed && $this->access->canAccessSchool($request->user(), $schoolId), 403);
    }

    /** @return array<string, mixed> */
    private function payload(EarlyWithdrawal $withdrawal): array
    {
        return [
            'id' => $withdrawal->id, 'public_id' => $withdrawal->public_id, 'book_id' => $withdrawal->book_id,
            'student_profile_id' => $withdrawal->student_profile_id, 'student_name' => $withdrawal->student_name_snapshot,
            'course' => $withdrawal->course_snapshot, 'status' => $withdrawal->status,
            'occurred_at' => $withdrawal->occurred_at?->toIso8601String(), 'returned_at' => $withdrawal->returned_at?->toIso8601String(),
            'withdrawal_code' => $withdrawal->porterWithdrawal?->withdrawal_code,
            'person_name' => $withdrawal->porterWithdrawal?->person_name,
            'person_relationship' => $withdrawal->porterWithdrawal?->person_relationship,
            'reason' => $withdrawal->porterWithdrawal?->reason,
            'requires_special_authorization' => (bool) $withdrawal->porterWithdrawal?->requires_special_authorization,
            'snapshot_hash' => $withdrawal->withdrawal_snapshot_hash,
            'return_snapshot_hash' => $withdrawal->return_snapshot_hash,
            'lock_version' => (int) $withdrawal->revision,
        ];
    }

    /** @return array<string, mixed> */
    private function auditPayload(EarlyWithdrawal $withdrawal, PorterStudentWithdrawal $porter): array
    {
        return [
            'public_id' => $withdrawal->public_id, 'student_profile_id' => $withdrawal->student_profile_id,
            'status' => $withdrawal->status, 'occurred_at' => $withdrawal->occurred_at?->toIso8601String(),
            'porter_withdrawal_id' => $porter->id, 'snapshot_hash' => $withdrawal->withdrawal_snapshot_hash,
        ];
    }
}
