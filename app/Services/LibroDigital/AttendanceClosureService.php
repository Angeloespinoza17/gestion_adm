<?php

namespace App\Services\LibroDigital;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Models\LibroDigital\Book;
use App\Models\LibroDigital\ClosureReopening;
use App\Models\LibroDigital\DailyAttendanceClosure;
use App\Models\LibroDigital\MonthlyAttendanceClosure;
use App\Models\LibroDigital\TeachingGroup;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceClosureService
{
    public function __construct(
        private readonly DailyAttendanceResolver $daily,
        private readonly AttendanceAnomalyDetector $anomalies,
        private readonly CanonicalJson $canonical,
        private readonly AuditEventWriter $audit,
    ) {}

    public function closeDay(Book $book, TeachingGroup $group, string $date, User $actor, ?Request $request = null): DailyAttendanceClosure
    {
        if ($group->book_id !== $book->id) {
            throw new LibroDigitalException('El grupo no pertenece al libro.', 'LCD_GROUP_BOOK_MISMATCH', 403);
        }
        if (! in_array($book->status instanceof \BackedEnum ? $book->status->value : $book->status, ['open', 'temporarily_locked', 'closing'], true)) {
            throw new LibroDigitalException('El libro no admite nuevos cierres de asistencia en su estado actual.', 'LCD_CLOSURE_BOOK_STATE_INVALID', 409);
        }
        $year = $book->academicYear()->firstOrFail();
        if ($date < $year->starts_at->format('Y-m-d') || $date > $year->ends_at->format('Y-m-d')) {
            throw new LibroDigitalException('La fecha de cierre queda fuera del año académico del libro.', 'LCD_DAILY_CLOSURE_DATE_OUTSIDE_YEAR');
        }
        $issues = $this->anomalies->forBookDate($book, $date);
        if ($issues !== []) {
            throw new LibroDigitalException('No se puede cerrar el día: existen anomalías.', 'LCD_DAILY_CLOSURE_ANOMALIES', 422, $issues);
        }
        $snapshot = $this->daily->resolve($book, $group->id, $date);
        $counts = collect($snapshot['students'])->countBy('status');
        $roster = $group->rosterSnapshots()->whereDate('effective_on', '<=', $date)->latest('effective_on')->latest('revision')->firstOrFail();

        $closure = DB::transaction(function () use ($book, $group, $date, $actor, $snapshot, $counts, $roster): DailyAttendanceClosure {
            $latest = DailyAttendanceClosure::query()->where('teaching_group_id', $group->id)->whereDate('closure_date', $date)
                ->orderByDesc('revision')->lockForUpdate()->first();
            if ($latest && $this->statusValue($latest->status) !== 'reopened') {
                throw new LibroDigitalException('El día ya está cerrado. Solicita una reapertura formal.', 'LCD_DAILY_ALREADY_CLOSED', 409);
            }
            $revision = $latest ? ((int) $latest->revision) + 1 : 1;
            $status = $latest ? 'reclosed' : 'closed';

            $closure = DailyAttendanceClosure::query()->create([
                'school_id' => $book->school_id, 'book_id' => $book->id, 'teaching_group_id' => $group->id,
                'roster_snapshot_id' => $roster->id, 'closure_date' => $date, 'status' => $status,
                'expected_count' => count($snapshot['students']), 'present_count' => (int) ($counts['present'] ?? 0),
                'absent_count' => (int) ($counts['absent'] ?? 0), 'late_count' => 0,
                'not_applicable_count' => (int) ($counts['not_applicable'] ?? 0), 'anomalies' => [],
                'snapshot' => $snapshot, 'snapshot_hash' => $this->canonical->hash($snapshot), 'revision' => $revision,
                'closed_by' => $actor->id, 'closed_at' => now('UTC'),
            ]);
            if ($latest) {
                $this->markReclosed($latest, $revision);
            }

            return $closure;
        }, 3);
        $this->audit->write('lcd.attendance.day_closed', 'close_day', $closure, actor: $actor, schoolId: $book->school_id, academicYearId: $book->academic_year_id, after: $closure->only(['public_id', 'closure_date', 'snapshot_hash', 'revision']), request: $request);

        return $closure;
    }

    public function closeMonth(Book $book, TeachingGroup $group, int $month, User $actor, ?Request $request = null): MonthlyAttendanceClosure
    {
        if ($group->book_id !== $book->id) {
            throw new LibroDigitalException('El grupo no pertenece al libro.', 'LCD_GROUP_BOOK_MISMATCH', 403);
        }
        if (! in_array($book->status instanceof \BackedEnum ? $book->status->value : $book->status, ['open', 'temporarily_locked', 'closing'], true)) {
            throw new LibroDigitalException('El libro no admite nuevos cierres de asistencia en su estado actual.', 'LCD_CLOSURE_BOOK_STATE_INVALID', 409);
        }
        $latestDays = DailyAttendanceClosure::query()->where('book_id', $book->id)->where('teaching_group_id', $group->id)
            ->whereMonth('closure_date', $month)->orderBy('closure_date')->orderByDesc('revision')->get()
            ->groupBy(fn (DailyAttendanceClosure $closure): string => $closure->closure_date->format('Y-m-d'))
            ->map(fn ($revisions) => $revisions->first())->values();
        if ($latestDays->contains(fn (DailyAttendanceClosure $closure): bool => $this->statusValue($closure->status) === 'reopened')) {
            throw new LibroDigitalException('Existen días reabiertos que deben volver a cerrarse antes de consolidar el mes.', 'LCD_MONTHLY_REOPENED_DAYS', 409);
        }
        $days = $latestDays->filter(fn (DailyAttendanceClosure $closure): bool => in_array($this->statusValue($closure->status), ['closed', 'reclosed'], true))->values();
        if ($days->isEmpty()) {
            throw new LibroDigitalException('No existen cierres diarios para consolidar.', 'LCD_MONTHLY_DAILY_CLOSURES_REQUIRED');
        }
        $snapshot = ['month' => $month, 'days' => $days->map->only(['public_id', 'closure_date', 'expected_count', 'present_count', 'absent_count', 'late_count', 'not_applicable_count', 'snapshot_hash'])->all()];
        $expected = (int) $days->sum('expected_count');
        $present = (int) $days->sum('present_count');
        $absent = (int) $days->sum('absent_count');

        $closure = DB::transaction(function () use ($book, $group, $month, $actor, $days, $snapshot, $expected, $present, $absent): MonthlyAttendanceClosure {
            $latest = MonthlyAttendanceClosure::query()->where('book_id', $book->id)->where('teaching_group_id', $group->id)->where('month', $month)
                ->orderByDesc('revision')->lockForUpdate()->first();
            if ($latest && $this->statusValue($latest->status) !== 'reopened') {
                throw new LibroDigitalException('El mes ya está cerrado. Solicita una reapertura formal.', 'LCD_MONTHLY_ALREADY_CLOSED', 409);
            }
            $revision = $latest ? ((int) $latest->revision) + 1 : 1;
            $status = $latest ? 'reclosed' : 'closed';

            $closure = MonthlyAttendanceClosure::query()->create([
                'school_id' => $book->school_id, 'book_id' => $book->id, 'teaching_group_id' => $group->id,
                'academic_year_id' => $book->academic_year_id, 'month' => $month, 'status' => $status,
                'school_days_count' => $days->count(), 'expected_total' => $expected, 'present_total' => $present,
                'absent_total' => $absent, 'attendance_percentage' => $expected > 0 ? round(($present / $expected) * 100, 3) : null,
                'snapshot' => $snapshot, 'snapshot_hash' => $this->canonical->hash($snapshot), 'revision' => $revision,
                'closed_by' => $actor->id, 'closed_at' => now('UTC'),
            ]);
            if ($latest) {
                $this->markReclosed($latest, $revision);
            }

            return $closure;
        }, 3);
        $this->audit->write('lcd.attendance.month_closed', 'close_month', $closure, actor: $actor, schoolId: $book->school_id, academicYearId: $book->academic_year_id, after: $closure->only(['public_id', 'month', 'snapshot_hash', 'revision']), request: $request);

        return $closure;
    }

    private function markReclosed(DailyAttendanceClosure|MonthlyAttendanceClosure $closure, int $replacementRevision): void
    {
        $reopening = ClosureReopening::query()->where('closable_type', $closure::class)
            ->where('closable_id', $closure->id)->where('status', 'reopened')
            ->latest('id')->lockForUpdate()->first();
        if (! $reopening) {
            throw new LibroDigitalException('No existe una autorización de reapertura vigente para este cierre.', 'LCD_CLOSURE_REOPENING_REQUIRED', 409);
        }
        $reopening->forceFill([
            'status' => 'reclosed',
            'reclosed_at' => now('UTC'),
            'replacement_revision' => $replacementRevision,
        ])->save();
    }

    private function statusValue(mixed $status): string
    {
        return $status instanceof \BackedEnum ? (string) $status->value : (string) $status;
    }
}
