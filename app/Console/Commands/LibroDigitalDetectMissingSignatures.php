<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LibroDigitalDetectMissingSignatures extends Command
{
    protected $signature = 'lcd:detect-missing-signatures {--date= : Fecha Y-m-d; predeterminado hoy} {--school= : ID del establecimiento} {--json}';

    protected $description = 'Lista sesiones no firmadas; no firma ni cierra automáticamente.';

    public function handle(): int
    {
        $date = $this->option('date') ?: now(config('libro_digital.timezone'))->toDateString();
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->error('La fecha debe usar Y-m-d.');

            return self::INVALID;
        }
        $rows = DB::table('lcd_class_sessions')->when($this->option('school'), fn ($query, $id) => $query->where('school_id', (int) $id))
            ->whereDate('session_date', $date)->whereNotIn('status', ['signed', 'closed', 'cancelled'])
            ->get(['public_id', 'course_snapshot', 'subject_snapshot', 'status']);
        $payload = ['date' => $date, 'count' => $rows->count(), 'sessions' => $rows];
        $this->line($this->option('json') ? json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : "Sesiones pendientes de firma: {$rows->count()}");

        return $rows->isEmpty() ? self::SUCCESS : self::FAILURE;
    }
}
