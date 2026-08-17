<?php

namespace App\Services\LibroDigital;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Models\LibroDigital\Book;
use Illuminate\Support\Facades\DB;

class DailyAttendanceResolver
{
    public function __construct(private readonly AttendancePolicyResolver $policies) {}

    /** @return array<string, mixed> */
    public function resolve(Book $book, int $teachingGroupId, string $date): array
    {
        $policy = $this->policies->for($book->regulatoryProfile);
        // A profile must name an explicitly implemented and legally verified rule.
        // Unknown values are blockers: silently treating them as "any presence wins"
        // would manufacture an official daily-attendance rule.
        if ($policy['daily_resolution'] !== 'any_pedagogical_presence') {
            throw new LibroDigitalException('La regla oficial de asistencia diaria no ha sido verificada para este perfil.', 'COMPLIANCE_BLOCKER_DAILY_ATTENDANCE_RULE', 409);
        }

        $sessions = $book->sessions()->where('teaching_group_id', $teachingGroupId)->whereDate('session_date', $date)
            ->whereNotIn('status', ['cancelled'])->orderBy('scheduled_start_at')->orderBy('id')->get();
        if ($sessions->isEmpty()) {
            throw new LibroDigitalException('No existen sesiones aplicables para resolver el día.', 'LCD_DAILY_NO_SESSIONS');
        }
        if ($sessions->contains(fn ($session) => ! in_array($session->status instanceof \BackedEnum ? $session->status->value : $session->status, ['signed', 'closed'], true))) {
            throw new LibroDigitalException('Hay sesiones sin firma o cierre en el día.', 'LCD_DAILY_UNSIGNED_SESSIONS', 409);
        }

        $sessionIds = $sessions->pluck('id');
        $rows = DB::table('lcd_session_attendance')->whereIn('class_session_id', $sessionIds)->orderBy('student_profile_id')->orderBy('class_session_id')->get()->groupBy('student_profile_id');
        $students = $rows->map(function ($records, $studentId): array {
            $statuses = $records->pluck('status');
            $status = $statuses->contains(fn ($value) => in_array($value, ['present', 'late', 'left_early'], true)) ? 'present'
                : ($statuses->every(fn ($value) => $value === 'not_applicable') ? 'not_applicable' : 'absent');

            return ['student_profile_id' => (int) $studentId, 'status' => $status, 'session_statuses' => $statuses->all()];
        })->values()->all();

        return ['date' => $date, 'teaching_group_id' => $teachingGroupId, 'policy' => $policy['daily_resolution'], 'students' => $students];
    }
}
