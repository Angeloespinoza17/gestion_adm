<?php

namespace App\Http\Controllers\LibroDigital;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Http\Requests\LibroDigital\StoreLateArrivalRequest;
use App\Models\LibroDigital\ClassSession;
use App\Models\LibroDigital\EnrollmentLink;
use App\Models\LibroDigital\LateArrival;
use App\Models\LibroDigital\LateArrivalPeriod;
use App\Services\LibroDigital\AuditEventWriter;
use App\Services\LibroDigital\CanonicalJson;
use App\Services\LibroDigital\FeatureFlagService;
use App\Services\LibroDigital\LibroDigitalAccessContext;
use App\Services\LibroDigital\RecordRevisionWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class LateArrivalController extends LibroDigitalController
{
    public function __construct(
        LibroDigitalAccessContext $access,
        private readonly FeatureFlagService $features,
        private readonly CanonicalJson $canonical,
        private readonly RecordRevisionWriter $revisions,
        private readonly AuditEventWriter $audit,
    ) {
        parent::__construct($access);
    }

    public function index(Request $request, string $book): JsonResponse
    {
        $aggregate = $this->book($book);
        $this->assertAvailable($request, $aggregate);
        $paginator = LateArrival::query()->where('book_id', $aggregate->id)
            ->with(['student:id,first_name,last_name,registered_name', 'period:id,public_id,name,starts_on,ends_on'])
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('arrival_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('arrival_at', '<=', $request->date('date_to')))
            ->when($request->filled('student_profile_id'), fn ($query) => $query->where('student_profile_id', $request->integer('student_profile_id')))
            ->latest('arrival_at')->paginate(min(100, max(1, $request->integer('per_page', 50))));

        return $this->collectionResponse($paginator->getCollection()->map(fn (LateArrival $arrival): array => $this->payload($arrival))->all(), [
            'current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(), 'total' => $paginator->total(),
            'policy_notice' => 'La clasificación horaria regulatoria permanece deshabilitada hasta contar con una regla oficial verificada.',
        ]);
    }

    public function store(StoreLateArrivalRequest $request, string $book): JsonResponse
    {
        $aggregate = $this->book($book);
        $this->assertAvailable($request, $aggregate);
        if ($this->statusValue($aggregate->status) !== 'open') {
            throw new LibroDigitalException('El libro debe estar abierto para registrar el atraso.', 'LCD_BOOK_NOT_OPEN', 409);
        }
        $data = $request->validated();
        $link = EnrollmentLink::query()->where('book_id', $aggregate->id)
            ->where('student_profile_id', $data['student_profile_id'])->where('status', 'active')->first();
        if (! $link) {
            throw new LibroDigitalException('El estudiante no pertenece a la nómina del libro.', 'LCD_PARVULARIA_STUDENT_OUTSIDE_ROSTER', 422);
        }
        $arrivalAt = Carbon::parse($data['arrival_at'])->utc();
        $localDate = $arrivalAt->timezone((string) ($aggregate->school()->value('timezone') ?: config('libro_digital.timezone')))->toDateString();
        $year = $aggregate->academicYear()->firstOrFail();
        if ($localDate < $year->starts_at->toDateString() || $localDate > $year->ends_at->toDateString()) {
            throw new LibroDigitalException('La fecha queda fuera del año académico.', 'LCD_PARVULARIA_DATE_OUTSIDE_YEAR', 422);
        }
        $period = filled($data['period_id'] ?? null) ? $this->aggregate(LateArrivalPeriod::class, $data['period_id']) : null;
        if ($period && (int) $period->book_id !== (int) $aggregate->id) {
            throw new LibroDigitalException('El periodo de atraso no pertenece al libro.', 'LCD_LATE_PERIOD_SCOPE_INVALID', 403);
        }
        $session = filled($data['class_session_id'] ?? null) ? $this->aggregate(ClassSession::class, $data['class_session_id']) : null;
        if ($session && ((int) $session->book_id !== (int) $aggregate->id || $session->session_date->toDateString() !== $localDate)) {
            throw new LibroDigitalException('La sesión no corresponde al libro y fecha seleccionados.', 'LCD_LATE_SESSION_SCOPE_INVALID', 403);
        }
        $attendance = $session?->attendance()->where('student_profile_id', $link->student_profile_id)->first();
        $minutesLate = $session?->scheduled_start_at ? max(0, $session->scheduled_start_at->diffInMinutes($arrivalAt, false)) : null;
        $payload = [
            'book_public_id' => $aggregate->public_id, 'student_profile_id' => $link->student_profile_id,
            'student_name' => $link->student_name_snapshot, 'arrival_at' => $arrivalAt->toIso8601String(),
            'minutes_late' => $minutesLate, 'source' => $data['source'] ?? 'manual',
            'policy' => 'recorded_without_unverified_regulatory_classification',
        ];
        $arrival = DB::transaction(function () use ($request, $aggregate, $link, $period, $session, $attendance, $arrivalAt, $minutesLate, $data, $payload): LateArrival {
            $arrival = LateArrival::query()->create([
                'school_id' => $aggregate->school_id, 'book_id' => $aggregate->id,
                'academic_year_id' => $aggregate->academic_year_id, 'teaching_group_id' => $link->teaching_group_id,
                'late_arrival_period_id' => $period?->id, 'class_session_id' => $session?->id,
                'session_attendance_id' => $attendance?->id, 'student_profile_id' => $link->student_profile_id,
                'student_enrollment_id' => $link->student_enrollment_id, 'arrival_at' => $arrivalAt,
                'minutes_late' => $minutesLate, 'status' => 'recorded', 'source' => $data['source'] ?? 'manual',
                'student_name_snapshot' => $link->student_name_snapshot, 'course_snapshot' => $link->course_snapshot,
                'justification_encrypted' => filled($data['justification'] ?? null) ? Crypt::encryptString($data['justification']) : null,
                'record_hash' => $this->canonical->hash($payload), 'revision' => 1,
                'created_by' => $request->user()->id,
            ]);
            $this->revisions->write($arrival, $arrival->school_id, 1, $request->user(), 'parvularia_late_arrival_created');

            return $arrival;
        }, 3);
        $this->audit->write('lcd.parvularia.late_arrival_created', 'create', $arrival, actor: $request->user(), schoolId: $arrival->school_id, academicYearId: $arrival->academic_year_id, after: $payload + ['record_hash' => $arrival->record_hash], request: $request);

        return $this->dataResponse($this->payload($arrival->load(['student', 'period'])), 201, $arrival->revision);
    }

    private function assertAvailable(Request $request, $book): void
    {
        $this->authorize('view', $book);
        abort_unless($request->user()->hasPermission('libro_digital.parvularia.manage') && $this->access->canAccessSchool($request->user(), $book->school_id), 403);
        if (! $this->features->enabled('lcd_parvularia_enabled', $book->school_id)) {
            throw new LibroDigitalException('El módulo de parvularia no está habilitado.', 'LCD_PARVULARIA_FEATURE_DISABLED', 503);
        }
        $book->loadMissing(['courseSection.educationLevel', 'regulatoryProfile']);
        if ($book->courseSection?->educationLevel?->type !== 'parvularia'
            || ! in_array('parvularia', (array) ($book->regulatoryProfile?->rules_snapshot['education_types'] ?? []), true)) {
            throw new LibroDigitalException('El libro y perfil no corresponden a educación parvularia.', 'LCD_PARVULARIA_SCOPE_INVALID', 409);
        }
    }

    /** @return array<string, mixed> */
    private function payload(LateArrival $arrival): array
    {
        return [
            'id' => $arrival->id, 'public_id' => $arrival->public_id, 'book_id' => $arrival->book_id,
            'student_profile_id' => $arrival->student_profile_id, 'student_name' => $arrival->student_name_snapshot,
            'course' => $arrival->course_snapshot, 'arrival_at' => $arrival->arrival_at?->toIso8601String(),
            'minutes_late' => $arrival->minutes_late, 'status' => $arrival->status, 'source' => $arrival->source,
            'period' => $arrival->period, 'record_hash' => $arrival->record_hash,
            'lock_version' => (int) $arrival->revision,
        ];
    }
}
