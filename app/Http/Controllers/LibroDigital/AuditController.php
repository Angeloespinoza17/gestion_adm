<?php

namespace App\Http\Controllers\LibroDigital;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Http\Requests\LibroDigital\ListAuditEventsRequest;
use App\Http\Requests\LibroDigital\VerifyAuditIntegrityRequest;
use App\Models\LibroDigital\AbsenceCase;
use App\Models\LibroDigital\AmendmentRequest;
use App\Models\LibroDigital\AuditEvent;
use App\Models\LibroDigital\Book;
use App\Models\LibroDigital\ClassSession;
use App\Models\LibroDigital\CoexistenceEntry;
use App\Models\LibroDigital\DailyAttendanceClosure;
use App\Models\LibroDigital\EarlyWithdrawal;
use App\Models\LibroDigital\EdeExport;
use App\Models\LibroDigital\GradeClosure;
use App\Models\LibroDigital\LateArrival;
use App\Models\LibroDigital\MonthlyAttendanceClosure;
use App\Models\LibroDigital\ParvulariaEvaluation;
use App\Models\LibroDigital\ParvulariaPlan;
use App\Models\LibroDigital\PieSupportRecord;
use App\Models\LibroDigital\ReportExport;
use App\Models\LibroDigital\SessionAttendance;
use App\Models\LibroDigital\StudentResult;
use App\Models\LibroDigital\TeacherSignature;
use App\Services\LibroDigital\AuditIntegrityVerifier;
use App\Services\LibroDigital\LibroDigitalAccessContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class AuditController extends LibroDigitalController
{
    /** @var array<string, class-string> */
    private const ENTITY_TYPES = [
        'book' => Book::class,
        'session' => ClassSession::class,
        'session_attendance' => SessionAttendance::class,
        'signature' => TeacherSignature::class,
        'daily_attendance_closure' => DailyAttendanceClosure::class,
        'monthly_attendance_closure' => MonthlyAttendanceClosure::class,
        'grade_closure' => GradeClosure::class,
        'student_result' => StudentResult::class,
        'amendment' => AmendmentRequest::class,
        'early_withdrawal' => EarlyWithdrawal::class,
        'late_arrival' => LateArrival::class,
        'absence_case' => AbsenceCase::class,
        'coexistence_entry' => CoexistenceEntry::class,
        'pie_support_record' => PieSupportRecord::class,
        'parvularia_plan' => ParvulariaPlan::class,
        'parvularia_evaluation' => ParvulariaEvaluation::class,
        'report' => ReportExport::class,
        'ede_export' => EdeExport::class,
    ];

    public function __construct(
        LibroDigitalAccessContext $access,
        private readonly AuditIntegrityVerifier $integrity,
    ) {
        parent::__construct($access);
    }

    public function index(ListAuditEventsRequest $request): JsonResponse
    {
        $school = $this->school($request);
        $events = AuditEvent::query()->where('school_id', $school->id)
            ->with(['actor:id,name', 'actorStaff:id,full_name'])
            ->when($request->filled('academic_year_id'), fn (Builder $query) => $query->where('academic_year_id', $request->integer('academic_year_id')))
            ->when($request->filled('entity_type'), fn (Builder $query) => $query->where('auditable_type', $request->string('entity_type')->toString()))
            ->when($request->filled('event_type'), fn (Builder $query) => $query->where('event', $request->string('event_type')->toString()))
            ->when($request->filled('action'), fn (Builder $query) => $query->where('action', $request->string('action')->toString()))
            ->when($request->filled('actor_id'), fn (Builder $query) => $query->where('actor_user_id', $request->integer('actor_id')))
            ->when($request->filled('correlation_id'), fn (Builder $query) => $query->where('correlation_id', $request->string('correlation_id')->toString()))
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('occurred_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('occurred_at', '<=', $request->date('to')))
            ->when($request->filled('query'), function (Builder $query) use ($request): void {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $request->string('query')->toString()).'%';
                $query->where(fn (Builder $search) => $search
                    ->where('auditable_type', 'like', $term)
                    ->orWhere('event', 'like', $term)
                    ->orWhere('correlation_id', 'like', $term)
                    ->orWhere('request_id', 'like', $term)
                    ->orWhere('public_id', 'like', $term));
            })
            ->orderByDesc('sequence_number')->paginate($request->integer('per_page', 50));

        return $this->collectionResponse(
            $events->getCollection()->map(fn (AuditEvent $event): array => $this->payload($event))->all(),
            [
                'current_page' => $events->currentPage(), 'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(), 'total' => $events->total(),
            ],
        );
    }

    public function verify(VerifyAuditIntegrityRequest $request): JsonResponse
    {
        $school = $this->school($request);
        $result = $this->integrity->verify($school->id);

        return $this->dataResponse([
            ...$result,
            'status' => $result['valid'] ? 'valid' : 'invalid',
            'school_id' => $school->id,
            'scope' => 'entire_school_chain',
            'message' => $result['valid']
                ? 'La cadena completa del establecimiento es consistente.'
                : 'La cadena completa del establecimiento contiene diferencias.',
            'filters_ignored_for_integrity' => collect(['entity_type', 'event_type', 'actor_id', 'query', 'from', 'to'])
                ->filter(fn (string $key): bool => $request->filled($key))->values()->all(),
        ]);
    }

    public function entity(ListAuditEventsRequest $request, string $entityType, string $entityId): JsonResponse
    {
        $school = $this->school($request);
        $auditableType = self::ENTITY_TYPES[$entityType] ?? null;
        if (! $auditableType) {
            throw new LibroDigitalException(
                'Tipo de entidad auditable no permitido.',
                'LCD_AUDIT_ENTITY_TYPE_INVALID',
                422,
            );
        }

        $events = AuditEvent::query()
            ->where('school_id', $school->id)
            ->where('auditable_type', $auditableType)
            ->where('auditable_id', (int) $entityId)
            ->with(['actor:id,name', 'actorStaff:id,full_name'])
            ->orderByDesc('sequence_number')
            ->paginate(min(100, max(1, $request->integer('per_page', 50))));
        $integrity = $this->integrity->verify((int) $school->id);

        return $this->collectionResponse(
            $events->getCollection()->map(fn (AuditEvent $event): array => $this->payload($event))->all(),
            [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
                'entity' => ['type' => $entityType, 'id' => (int) $entityId],
                // La cadena es global por establecimiento; no se presenta una
                // subcadena de entidad como si fuese verificable en aislamiento.
                'school_chain_valid' => (bool) $integrity['valid'],
            ],
        );
    }

    /** @return array<string, mixed> */
    private function payload(AuditEvent $event): array
    {
        return [
            'id' => $event->id, 'public_id' => $event->public_id, 'school_id' => $event->school_id,
            'academic_year_id' => $event->academic_year_id, 'sequence_number' => (int) $event->sequence_number,
            'actor' => $event->actor ? ['id' => $event->actor->id, 'name' => $event->actor->name] : null,
            'actor_name' => $event->actor?->name ?: $event->actorStaff?->full_name ?: 'Sistema',
            'actor_role' => $event->actor_role_snapshot, 'event_type' => $event->event, 'event' => $event->event,
            'action' => $event->action, 'entity_type' => $event->auditable_type, 'auditable_type' => $event->auditable_type,
            'entity_id' => $event->auditable_id, 'auditable_id' => $event->auditable_id, 'revision' => $event->revision,
            'reason' => $event->reason, 'before_hash' => $event->before_hash, 'after_hash' => $event->after_hash,
            // Nunca se expone encrypted_diff, IP cifrada, user-agent ni datos previos/posteriores.
            'changed_fields' => [], 'correlation_id' => $event->correlation_id, 'request_id' => $event->request_id,
            'previous_event_hash' => $event->previous_event_hash, 'event_hash' => $event->event_hash,
            'occurred_at' => $event->occurred_at?->toIso8601String(),
        ];
    }
}
