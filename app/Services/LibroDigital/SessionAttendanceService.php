<?php

namespace App\Services\LibroDigital;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Models\LibroDigital\ClassSession;
use App\Models\LibroDigital\SessionAttendance;
use App\Models\User;
use BackedEnum;
use Illuminate\Support\Facades\DB;

class SessionAttendanceService
{
    public function __construct(private readonly CanonicalJson $canonical) {}

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array{expected: int, recorded: int, present: int, absent: int, late: int, left_early: int, not_applicable: int, complete: bool}
     */
    public function replace(ClassSession $session, array $records, User $actor): array
    {
        if (in_array($this->statusValue($session->status), ['signed', 'closed'], true)) {
            throw new LibroDigitalException(
                'La asistencia de una sesion firmada no se puede sobrescribir.',
                'LCD_SESSION_IMMUTABLE',
                409,
            );
        }

        $rosterItems = $session->rosterSnapshot->items()->get()->keyBy('id');
        $submittedIds = collect($records)->pluck('roster_snapshot_item_id');

        if ($submittedIds->duplicates()->isNotEmpty()) {
            throw new LibroDigitalException('La solicitud contiene estudiantes duplicados.', 'LCD_ATTENDANCE_DUPLICATE');
        }

        if ($submittedIds->diff($rosterItems->keys())->isNotEmpty()) {
            throw new LibroDigitalException('La asistencia contiene estudiantes fuera de la nomina sellada.', 'LCD_ATTENDANCE_OUTSIDE_ROSTER');
        }

        DB::transaction(function () use ($session, $records, $actor, $rosterItems): void {
            $locked = ClassSession::query()->lockForUpdate()->findOrFail($session->getKey());
            if (in_array($this->statusValue($locked->status), ['signed', 'closed'], true)) {
                throw new LibroDigitalException('La sesion fue firmada mientras registrabas asistencia.', 'LCD_SESSION_IMMUTABLE', 409);
            }

            foreach ($records as $record) {
                $item = $rosterItems->get((int) $record['roster_snapshot_item_id']);
                $status = (string) ($record['status'] ?? '');
                if (! in_array($status, ['present', 'absent', 'late', 'left_early', 'not_applicable'], true)) {
                    throw new LibroDigitalException('Estado de asistencia no permitido.', 'LCD_ATTENDANCE_STATUS_INVALID');
                }
                if ($status === 'late' && blank($record['arrival_at'] ?? null)) {
                    throw new LibroDigitalException('Un atraso requiere hora de llegada.', 'LCD_ATTENDANCE_ARRIVAL_REQUIRED');
                }
                if ($status === 'left_early' && blank($record['departure_at'] ?? null)) {
                    throw new LibroDigitalException('Un retiro anticipado requiere hora de salida.', 'LCD_ATTENDANCE_DEPARTURE_REQUIRED');
                }

                $payload = [
                    'class_session_id' => $locked->id,
                    'roster_snapshot_item_id' => $item->id,
                    'student_profile_id' => $item->student_profile_id,
                    'student_enrollment_id' => $item->student_enrollment_id,
                    'status' => $status,
                    'arrival_at' => $record['arrival_at'] ?? null,
                    'departure_at' => $record['departure_at'] ?? null,
                    'justification_status' => $record['justification_status'] ?? 'not_required',
                    'source' => $record['source'] ?? 'manual',
                    'notes' => $record['notes'] ?? null,
                    'recorded_by' => $actor->id,
                    'recorded_at' => now('UTC'),
                ];
                $payload['record_hash'] = $this->canonical->hash($payload);

                $attendance = SessionAttendance::query()->firstOrNew([
                    'class_session_id' => $locked->id,
                    'student_profile_id' => $item->student_profile_id,
                ]);
                $attendance->fill($payload);
                $attendance->revision = $attendance->exists ? ((int) $attendance->revision + 1) : 1;
                $attendance->save();
            }

            $locked->forceFill([
                'status' => 'attendance_in_progress',
                'lock_version' => $locked->lock_version + 1,
                'updated_by' => $actor->id,
            ])->save();
        }, 3);

        return $this->totals($session->fresh());
    }

    /** @return array{expected: int, recorded: int, present: int, absent: int, late: int, left_early: int, not_applicable: int, complete: bool} */
    public function totals(ClassSession $session): array
    {
        $expected = $session->rosterSnapshot->items()->where('applicability_status', 'applicable')->count();
        $counts = $session->attendance()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $recorded = (int) $counts->sum();

        return [
            'expected' => $expected,
            'recorded' => $recorded,
            'present' => (int) ($counts['present'] ?? 0),
            'absent' => (int) ($counts['absent'] ?? 0),
            'late' => (int) ($counts['late'] ?? 0),
            'left_early' => (int) ($counts['left_early'] ?? 0),
            'not_applicable' => (int) ($counts['not_applicable'] ?? 0),
            'complete' => $recorded === $expected,
        ];
    }

    public function assertComplete(ClassSession $session): void
    {
        $totals = $this->totals($session);
        if (! $totals['complete']) {
            $missing = max(0, $totals['expected'] - $totals['recorded']);
            throw new LibroDigitalException(
                'No es posible cerrar la asistencia: faltan estudiantes por registrar.',
                'LCD_SESSION_ATTENDANCE_INCOMPLETE',
                422,
                [['field' => 'attendance', 'reason' => "Faltan {$missing} estudiantes por registrar."]],
            );
        }
    }

    private function statusValue(mixed $status): string
    {
        return $status instanceof BackedEnum ? (string) $status->value : (string) $status;
    }
}
