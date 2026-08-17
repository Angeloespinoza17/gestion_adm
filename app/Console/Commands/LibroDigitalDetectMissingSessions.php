<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LibroDigitalDetectMissingSessions extends Command
{
    protected $signature = 'lcd:detect-missing-sessions {--date= : Fecha Y-m-d; predeterminado hoy} {--school= : ID del establecimiento} {--json}';

    protected $description = 'Compara horario recurrente con sesiones reales; no crea clases automáticamente.';

    public function handle(): int
    {
        $date = $this->option('date') ?: now(config('libro_digital.timezone'))->toDateString();
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->error('La fecha debe usar Y-m-d.');

            return self::INVALID;
        }
        $day = (int) Carbon::parse($date)->dayOfWeekIso;
        $rows = DB::table('schedule_events as e')
            ->join('lcd_books as b', 'b.course_section_id', '=', 'e.course_section_id')
            ->where('e.day_of_week', $day)->when($this->option('school'), fn ($query, $id) => $query->where('b.school_id', (int) $id))
            ->where('b.status', 'open')->whereNotExists(function ($query) use ($date): void {
                $query->selectRaw('1')->from('lcd_class_sessions as s')->whereColumn('s.book_id', 'b.id')->whereColumn('s.schedule_event_id', 'e.id')->whereDate('s.session_date', $date);
            })->get(['b.public_id as book_public_id', 'e.id as schedule_event_id', 'e.course_section_id', 'e.schedule_subject_id', 'e.staff_id']);
        $payload = ['date' => $date, 'count' => $rows->count(), 'missing' => $rows];
        $this->line($this->option('json') ? json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : "Sesiones esperadas no registradas: {$rows->count()}");

        return $rows->isEmpty() ? self::SUCCESS : self::FAILURE;
    }
}
