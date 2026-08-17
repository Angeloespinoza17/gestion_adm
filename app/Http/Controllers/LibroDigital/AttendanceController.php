<?php

namespace App\Http\Controllers\LibroDigital;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Http\Requests\LibroDigital\UpdateSessionAttendanceRequest;
use App\Models\LibroDigital\ClassSession;
use App\Models\LibroDigital\RosterSnapshotItem;
use App\Services\LibroDigital\AuditEventWriter;
use App\Services\LibroDigital\LibroDigitalAccessContext;
use App\Services\LibroDigital\OptimisticLock;
use App\Services\LibroDigital\SessionAttendanceService;
use BackedEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceController extends LibroDigitalController
{
    public function __construct(
        LibroDigitalAccessContext $access,
        private readonly OptimisticLock $locks,
        private readonly SessionAttendanceService $attendance,
        private readonly AuditEventWriter $audit,
    ) {
        parent::__construct($access);
    }

    public function show(Request $request, string $session): JsonResponse
    {
        $model = $this->session($session);
        $this->authorize('view', $model);

        return $this->dataResponse($this->payload($model), version: $model->lock_version);
    }

    public function update(UpdateSessionAttendanceRequest $request, string $session): JsonResponse
    {
        $model = $this->session($session);
        $this->authorize('attendance', $model);
        $this->locks->assert($model, $request);
        $timezone = $model->school()->value('timezone') ?: config('libro_digital.timezone', 'America/Santiago');
        $items = $model->rosterSnapshot->items()->get()->keyBy('student_profile_id');
        $records = collect($request->validated('records'))
            ->reject(fn (array $record) => $record['status'] === 'pending')
            ->map(function (array $record) use ($model, $timezone, $items): array {
                $item = filled($record['roster_snapshot_item_id'] ?? null)
                    ? $model->rosterSnapshot->items()->find($record['roster_snapshot_item_id'])
                    : $items->get((int) $record['student_profile_id']);
                if (! $item) {
                    throw new LibroDigitalException('La asistencia contiene estudiantes fuera de la nómina sellada.', 'LCD_ATTENDANCE_OUTSIDE_ROSTER');
                }
                $date = $model->session_date->format('Y-m-d');
                $arrival = filled($record['arrival_time'] ?? null)
                    ? Carbon::createFromFormat('Y-m-d H:i', $date.' '.$record['arrival_time'], $timezone)->utc()
                    : null;
                $departure = filled($record['departure_time'] ?? null)
                    ? Carbon::createFromFormat('Y-m-d H:i', $date.' '.$record['departure_time'], $timezone)->utc()
                    : null;

                return [
                    'roster_snapshot_item_id' => $item->id,
                    'status' => $record['status'],
                    'arrival_at' => $arrival,
                    'departure_at' => $departure,
                    'justification_status' => filled($record['justification'] ?? null) ? 'pending' : 'not_required',
                    'notes' => collect([$record['justification'] ?? null, $record['observation'] ?? null])->filter()->join(' · ') ?: null,
                    'source' => 'manual',
                ];
            })->values()->all();

        if ($records !== []) {
            $this->attendance->replace($model, $records, $request->user());
            $model = $model->fresh();
            $this->audit->write('lcd.session.attendance_saved', 'update_attendance', $model, actor: $request->user(), schoolId: $model->school_id, academicYearId: $model->academic_year_id, after: $this->attendance->totals($model), request: $request, entityRevision: $model->revision);
        }

        return $this->dataResponse($this->payload($model), version: $model->lock_version);
    }

    public function complete(Request $request, string $session): JsonResponse
    {
        $request->validate(['lock_version' => ['required', 'integer', 'min:1']]);
        $model = $this->session($session);
        $this->authorize('attendance', $model);
        $this->locks->assert($model, $request);
        $this->attendance->assertComplete($model);
        $this->audit->write('lcd.session.attendance_completed', 'complete_attendance', $model, actor: $request->user(), schoolId: $model->school_id, academicYearId: $model->academic_year_id, after: $this->attendance->totals($model), request: $request, entityRevision: $model->revision);

        return $this->dataResponse($this->payload($model), version: $model->lock_version);
    }

    public function daily(Request $request, string $book): JsonResponse
    {
        $bookModel = $this->book($book);
        $this->authorize('view', $bookModel);
        $date = $request->input('date', now()->toDateString());
        $rows = $this->aggregateQuery($bookModel->id)->whereDate('s.session_date', $date)
            ->groupBy('s.session_date', 'a.status')->orderBy('s.session_date')->get();

        return $this->dataResponse(['date' => $date, 'totals' => $this->groupTotals($rows), 'rows' => $rows]);
    }

    public function monthly(Request $request, string $book): JsonResponse
    {
        $bookModel = $this->book($book);
        $this->authorize('view', $bookModel);
        $month = max(1, min(12, $request->integer('month', now()->month)));
        $year = $request->integer('year', $bookModel->year_snapshot);
        $rows = $this->aggregateQuery($bookModel->id)
            ->whereYear('s.session_date', $year)->whereMonth('s.session_date', $month)
            ->groupBy('s.session_date', 'a.status')->orderBy('s.session_date')->get();
        $byDay = $rows->groupBy('session_date')->map(fn ($dayRows, string $date) => ['date' => $date, ...$this->groupTotals($dayRows)])->values();

        return $this->dataResponse(['year' => $year, 'month' => $month, 'totals' => $this->groupTotals($rows), 'days' => $byDay]);
    }

    /** @return array<string, mixed> */
    private function payload(ClassSession $session): array
    {
        $session->loadMissing(['school', 'rosterSnapshot.items.student', 'attendance']);
        $existing = $session->attendance->keyBy('roster_snapshot_item_id');
        $timezone = $session->school?->timezone ?: config('libro_digital.timezone', 'America/Santiago');
        $records = $session->rosterSnapshot->items->sortBy(fn (RosterSnapshotItem $item) => $item->list_number ?? PHP_INT_MAX)
            ->map(function (RosterSnapshotItem $item) use ($existing, $timezone): array {
                $attendance = $existing->get($item->id);
                $status = $attendance?->status instanceof BackedEnum ? $attendance->status->value : ($attendance?->status ?? 'pending');

                return [
                    'id' => $attendance?->id,
                    'roster_snapshot_item_id' => $item->id,
                    'student_profile_id' => $item->student_profile_id,
                    'student_enrollment_id' => $item->student_enrollment_id,
                    'list_number' => $item->list_number,
                    'student_name' => $item->student_name_snapshot,
                    'student' => ['id' => $item->student_profile_id, 'registered_name' => $item->student?->registered_name, 'full_name' => $item->student?->full_name],
                    'status' => $status,
                    'arrival_time' => $attendance?->arrival_at?->setTimezone($timezone)->format('H:i'),
                    'departure_time' => $attendance?->departure_at?->setTimezone($timezone)->format('H:i'),
                    'justification' => $attendance?->justification_status === 'pending' ? $attendance?->notes : null,
                    'observation' => $attendance?->notes,
                    'revision' => (int) ($attendance?->revision ?? 0),
                ];
            })->values();

        return [
            'session_id' => $session->id,
            'roster_snapshot_id' => $session->roster_snapshot_id,
            'roster_snapshot_hash' => $session->rosterSnapshot->snapshot_hash,
            'records' => $records,
            'totals' => $this->attendance->totals($session),
            'complete' => $this->attendance->totals($session)['complete'],
            'lock_version' => (int) $session->lock_version,
            'updated_at' => $session->updated_at?->toIso8601String(),
        ];
    }

    private function aggregateQuery(int $bookId)
    {
        return DB::table('lcd_class_sessions as s')->leftJoin('lcd_session_attendance as a', 'a.class_session_id', '=', 's.id')
            ->where('s.book_id', $bookId)->selectRaw('s.session_date, a.status, COUNT(a.id) as total');
    }

    /** @return array<string, int|float> */
    private function groupTotals($rows): array
    {
        $counts = collect($rows)->groupBy('status')->map(fn ($items) => (int) $items->sum('total'));
        $recorded = (int) $counts->sum();
        $present = (int) ($counts['present'] ?? 0) + (int) ($counts['late'] ?? 0);

        return [
            'recorded' => $recorded, 'present' => $present, 'absent' => (int) ($counts['absent'] ?? 0),
            'late' => (int) ($counts['late'] ?? 0), 'left_early' => (int) ($counts['left_early'] ?? 0),
            'not_applicable' => (int) ($counts['not_applicable'] ?? 0),
            'attendance_rate' => $recorded > 0 ? round($present / $recorded * 100, 2) : 0.0,
        ];
    }
}
