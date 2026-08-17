<?php

namespace App\Http\Controllers\LibroDigital;

use App\Models\LibroDigital\ClassSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StatisticsController extends LibroDigitalController
{
    public function __invoke(Request $request): JsonResponse
    {
        $school = $this->school($request);
        [$from, $to] = $this->period($request, $school->timezone);
        $user = $request->user();

        $sessions = ClassSession::query()->where('school_id', $school->id)
            ->whereBetween('session_date', [$from, $to])
            ->when($request->integer('academic_year_id'), fn (Builder $query, int $id) => $query->where('academic_year_id', $id))
            ->when($request->integer('book_id'), fn (Builder $query, int $id) => $query->where('book_id', $id))
            ->when($request->integer('course_section_id'), fn (Builder $query, int $id) => $query->whereHas('book', fn (Builder $book) => $book->where('course_section_id', $id)))
            ->when($request->integer('schedule_subject_id'), fn (Builder $query, int $id) => $query->where('schedule_subject_id', $id))
            ->when($request->integer('teacher_staff_id'), fn (Builder $query, int $id) => $query->where('actual_teacher_id', $id))
            ->when($request->filled('session_status'), fn (Builder $query) => $query->where('status', $request->input('session_status')))
            ->when(! $user->isSuperAdmin() && ! $user->hasPermission('libro_digital.closures.manage'), fn (Builder $query) => $query->where('actual_teacher_id', $user->staff_id));

        $sessionRows = (clone $sessions)->get(['id', 'session_date', 'status', 'schedule_subject_id', 'subject_snapshot', 'objective_summary']);
        $sessionIds = $sessionRows->pluck('id');
        $attendanceRows = DB::table('lcd_session_attendance')->whereIn('class_session_id', $sessionIds)->get(['class_session_id', 'student_profile_id', 'status']);
        $statusCounts = $attendanceRows->groupBy('status')->map->count();
        $denominator = (int) collect(['present', 'absent', 'late', 'left_early'])->sum(fn (string $status) => (int) ($statusCounts[$status] ?? 0));
        $present = (int) ($statusCounts['present'] ?? 0) + (int) ($statusCounts['late'] ?? 0) + (int) ($statusCounts['left_early'] ?? 0);
        $attendanceRate = $denominator ? round($present / $denominator * 100, 2) : null;
        $totalSessions = $sessionRows->count();
        $completedSessions = $sessionRows->whereIn('status', ['signed', 'closed'])->count();
        $signedSessions = $sessionRows->whereIn('status', ['signed', 'closed'])->count();
        $covered = $sessionRows->filter(fn ($session) => filled($session->objective_summary))->count();
        $averageGrade = DB::table('lcd_student_results as r')->join('lcd_assessments as a', 'a.id', '=', 'r.assessment_id')
            ->where('a.school_id', $school->id)->when($request->integer('book_id'), fn ($query, int $id) => $query->where('a.book_id', $id))->avg('r.numeric_value');

        $timeline = $sessionRows->groupBy(fn ($session) => $session->session_date->format('Y-m-d'))->map(function ($daySessions, string $date) use ($attendanceRows): array {
            $ids = $daySessions->pluck('id');
            $rows = $attendanceRows->whereIn('class_session_id', $ids);
            $denominator = $rows->whereIn('status', ['present', 'absent', 'late', 'left_early'])->count();
            $present = $rows->whereIn('status', ['present', 'late', 'left_early'])->count();

            return ['date' => $date, 'label' => Carbon::parse($date)->format('d-m'), 'attendance_rate' => $denominator ? round($present / $denominator * 100, 2) : 0];
        })->values();
        $sessionsByStatus = $sessionRows->groupBy('status')->map(fn ($items, string $status) => ['status' => $status, 'label' => str_replace('_', ' ', ucfirst($status)), 'value' => $items->count()])->values();
        $coverage = $sessionRows->groupBy('schedule_subject_id')->map(function ($items): array {
            $covered = $items->filter(fn ($session) => filled($session->objective_summary))->count();

            return ['subject' => $items->first()->subject_snapshot, 'label' => $items->first()->subject_snapshot, 'percentage' => $items->count() ? round($covered / $items->count() * 100, 2) : 0];
        })->values();
        $risks = $attendanceRows->groupBy('student_profile_id')->map(function ($rows): string {
            $denominator = $rows->whereIn('status', ['present', 'absent', 'late', 'left_early'])->count();
            $rate = $denominator ? $rows->whereIn('status', ['present', 'late', 'left_early'])->count() / $denominator * 100 : 100;

            return $rate < 75 ? 'critical' : ($rate < 85 ? 'high' : ($rate < 90 ? 'medium' : 'regular'));
        })->countBy();
        $riskDistribution = collect([
            ['name' => 'Crítico (<75%)', 'label' => 'Crítico (<75%)', 'value' => (int) ($risks['critical'] ?? 0), 'color' => '#c84f5a'],
            ['name' => 'Alto (75–84%)', 'label' => 'Alto (75–84%)', 'value' => (int) ($risks['high'] ?? 0), 'color' => '#d59b26'],
            ['name' => 'Medio (85–89%)', 'label' => 'Medio (85–89%)', 'value' => (int) ($risks['medium'] ?? 0), 'color' => '#3b82a0'],
            ['name' => 'Regular (≥90%)', 'label' => 'Regular (≥90%)', 'value' => (int) ($risks['regular'] ?? 0), 'color' => '#2b8a66'],
        ]);
        $openAbsenceCases = DB::table('lcd_absence_cases')->where('school_id', $school->id)->whereIn('status', ['open', 'monitoring'])->count();

        return $this->dataResponse([
            'period' => ['from' => $from, 'to' => $to],
            'summary' => [
                'completed_sessions' => $completedSessions,
                'attendance_rate' => $attendanceRate,
                'curriculum_coverage' => $totalSessions ? round($covered / $totalSessions * 100, 2) : null,
                'signature_rate' => $totalSessions ? round($signedSessions / $totalSessions * 100, 2) : null,
                'average_grade' => $averageGrade === null ? null : round((float) $averageGrade, 2),
                'open_absence_cases' => $openAbsenceCases,
            ],
            'kpis' => [
                ['key' => 'sessions', 'label' => 'Sesiones realizadas', 'value' => $completedSessions, 'icon' => 'bx-calendar-check'],
                ['key' => 'attendance', 'label' => 'Asistencia promedio', 'value' => $attendanceRate, 'unit' => '%', 'icon' => 'bx-user-check'],
                ['key' => 'coverage', 'label' => 'Cobertura curricular', 'value' => $totalSessions ? round($covered / $totalSessions * 100, 2) : null, 'unit' => '%', 'icon' => 'bx-target-lock'],
                ['key' => 'signatures', 'label' => 'Sesiones firmadas', 'value' => $totalSessions ? round($signedSessions / $totalSessions * 100, 2) : null, 'unit' => '%', 'icon' => 'bx-pen'],
                ['key' => 'grades', 'label' => 'Promedio calificaciones', 'value' => $averageGrade === null ? null : round((float) $averageGrade, 2), 'icon' => 'bx-bar-chart'],
                ['key' => 'absence', 'label' => 'Casos de ausencia', 'value' => $openAbsenceCases, 'icon' => 'bx-calendar-x'],
            ],
            'attendance_timeline' => $timeline,
            'sessions_by_status' => $sessionsByStatus,
            'curriculum_coverage' => $coverage,
            'risk_distribution' => $riskDistribution,
        ]);
    }

    /** @return array{0: string, 1: string} */
    private function period(Request $request, string $timezone): array
    {
        $now = now($timezone);
        $period = $request->input('period', 'academic_year');

        return match ($period) {
            'current_week' => [$now->copy()->startOfWeek()->toDateString(), $now->copy()->endOfWeek()->toDateString()],
            'current_month' => [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString()],
            'custom' => [$request->input('from', $now->copy()->startOfYear()->toDateString()), $request->input('to', $now->toDateString())],
            default => [$now->copy()->startOfYear()->toDateString(), $now->copy()->endOfYear()->toDateString()],
        };
    }
}
