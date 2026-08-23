<?php

namespace App\Console\Commands;

use App\Models\AcademicYear;
use App\Services\Attendance\AttendanceAnalysisService;
use Illuminate\Console\Command;

class AnalyzeAttendanceManagement extends Command
{
    protected $signature = 'attendance:analyze {--academic-year-id=} {--date=} {--course-section-id=}';

    protected $description = 'Calcula snapshots, patrones, riesgos y alertas explicables de gestión de ausencia.';

    public function handle(AttendanceAnalysisService $analysis): int
    {
        $year = $this->option('academic-year-id')
            ? AcademicYear::query()->find($this->option('academic-year-id'))
            : AcademicYear::query()->where('year', now(config('attendance_management.timezone'))->year)->first()
                ?? AcademicYear::query()->where('is_active', true)->first();
        if (! $year) {
            $this->error('No existe un año académico para analizar.');

            return self::FAILURE;
        }
        $result = $analysis->analyzeYear($year, $this->option('date'), $this->option('course-section-id') ? (int) $this->option('course-section-id') : null);
        $this->info(sprintf(
            'Análisis %s: %d estudiantes, %d snapshots, %d patrones activos, %d alertas nuevas.',
            $result['snapshot_date'], $result['students'], $result['snapshots'], $result['patterns'], $result['alerts'],
        ));

        return self::SUCCESS;
    }
}
