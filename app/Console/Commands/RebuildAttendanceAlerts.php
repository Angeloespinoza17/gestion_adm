<?php

namespace App\Console\Commands;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Services\Attendance\AttendanceAlertService;
use Illuminate\Console\Command;

class RebuildAttendanceAlerts extends Command
{
    protected $signature = 'attendance:rebuild-alerts {--year= : ID del año académico}';

    protected $description = 'Recalcula las alertas de asistencia por curso';

    public function handle(AttendanceAlertService $alerts): int
    {
        $years = AcademicYear::query()
            ->when($this->option('year'), fn ($query, $year) => $query->whereKey($year))
            ->when(! $this->option('year'), fn ($query) => $query->where('is_active', true))
            ->pluck('id');

        foreach ($years as $yearId) {
            CourseSection::query()
                ->where('academic_year_id', $yearId)
                ->where('active', true)
                ->pluck('id')
                ->each(fn (int $courseId) => $alerts->rebuild((int) $yearId, $courseId));
        }

        $this->info('Alertas de asistencia recalculadas.');

        return self::SUCCESS;
    }
}
