<?php

namespace App\Services\SocialWork;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RiskDataAdapter
{
    public function snapshot(int $studentId, Carbon $from, Carbon $to): array
    {
        $attendance = ['total' => 0, 'absent' => 0, 'percentage' => null, 'consecutive_absences' => 0];
        if (Schema::hasTable('attendance_records')) {
            $rows = DB::table('attendance_records')->where('student_profile_id', $studentId)->whereBetween('attendance_date', [$from->toDateString(), $to->toDateString()])->orderBy('attendance_date')->get(['attendance_date', 'status']);
            $attendance['total'] = $rows->count();
            $attendance['absent'] = $rows->where('status', 'absent')->count();
            $attendance['percentage'] = $rows->count() ? round((($rows->count() - $attendance['absent']) / $rows->count()) * 100, 2) : null;
            $streak = 0; $maxStreak = 0;
            foreach ($rows as $row) { $streak = $row->status === 'absent' ? $streak + 1 : 0; $maxStreak = max($maxStreak, $streak); }
            $attendance['consecutive_absences'] = $maxStreak;
        }

        $late = Schema::hasTable('lcd_late_arrivals') ? DB::table('lcd_late_arrivals')->where('student_profile_id', $studentId)->whereBetween('arrival_at', [$from, $to])->count() : 0;
        $annotations = Schema::hasTable('lcd_coexistence_entries') ? DB::table('lcd_coexistence_entries')->where('student_profile_id', $studentId)->whereBetween('happened_at', [$from, $to])->count() : 0;
        $activeCases = Schema::hasTable('social_work_cases') ? DB::table('social_work_cases')->where('primary_student_id', $studentId)->whereNotIn('status', ['cerrado', 'anulado'])->whereNull('deleted_at')->count() : 0;
        $overdueCommitments = Schema::hasTable('social_work_commitments') ? DB::table('social_work_commitments')->whereIn('case_id', DB::table('social_work_cases')->select('id')->where('primary_student_id', $studentId))->whereIn('status', ['pendiente', 'solicitado'])->where('due_at', '<', now())->count() : 0;

        return ['attendance' => $attendance, 'late_arrivals' => $late, 'annotations' => $annotations, 'active_cases' => $activeCases, 'overdue_commitments' => $overdueCommitments];
    }
}
