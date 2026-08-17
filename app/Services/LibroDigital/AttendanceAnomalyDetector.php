<?php

namespace App\Services\LibroDigital;

use App\Models\LibroDigital\Book;
use App\Models\LibroDigital\ClassSession;

class AttendanceAnomalyDetector
{
    /** @return array<int, array<string, mixed>> */
    public function forSession(ClassSession $session): array
    {
        $issues = [];
        $expected = $session->rosterSnapshot()->withCount('items')->first()?->items_count ?? 0;
        $actual = $session->attendance()->count();
        if ($expected !== $actual) {
            $issues[] = ['code' => 'ATTENDANCE_COUNT_MISMATCH', 'expected' => $expected, 'actual' => $actual];
        }
        if ($session->attendance()->where('status', 'late')->whereNull('arrival_at')->exists()) {
            $issues[] = ['code' => 'LATE_WITHOUT_ARRIVAL'];
        }
        if ($session->attendance()->where('status', 'left_early')->whereNull('departure_at')->exists()) {
            $issues[] = ['code' => 'EARLY_DEPARTURE_WITHOUT_TIME'];
        }
        if ($session->attendance()->whereColumn('recorded_at', '<', 'created_at')->exists()) {
            $issues[] = ['code' => 'INVALID_RECORDED_TIMESTAMP'];
        }

        return $issues;
    }

    /** @return array<int, array<string, mixed>> */
    public function forBookDate(Book $book, string $date): array
    {
        $issues = [];
        $sessions = $book->sessions()->whereDate('session_date', $date)->with(['rosterSnapshot'])->get();
        if ($sessions->isEmpty()) {
            return [['code' => 'NO_SESSIONS_FOR_DATE']];
        }
        foreach ($sessions as $session) {
            foreach ($this->forSession($session) as $issue) {
                $issues[] = ['session_public_id' => $session->public_id, ...$issue];
            }
            if (! in_array($session->status instanceof \BackedEnum ? $session->status->value : $session->status, ['signed', 'closed', 'cancelled'], true)) {
                $issues[] = ['session_public_id' => $session->public_id, 'code' => 'SESSION_NOT_CLOSED'];
            }
        }

        return $issues;
    }
}
