<?php

namespace App\Console\Commands;

use App\Models\Attendance\AttendanceScheduledReport;
use App\Services\Attendance\AttendanceExportService;
use Illuminate\Console\Command;
use Throwable;

class RunAttendanceScheduledReports extends Command
{
    protected $signature = 'attendance:run-scheduled-reports';

    protected $description = 'Encola los reportes de asistencia programados que están vencidos';

    public function handle(AttendanceExportService $exports): int
    {
        AttendanceScheduledReport::query()
            ->where('active', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->with('owner')
            ->chunkById(50, function ($reports) use ($exports) {
                foreach ($reports as $report) {
                    try {
                        if (! $report->owner || ! $report->academic_year_id) {
                            throw new \RuntimeException('El reporte no tiene propietario o año académico vigente.');
                        }
                        $exports->create([
                            'academic_year_id' => $report->academic_year_id,
                            'report_type' => $report->report_type,
                            'format' => $report->format,
                            'filters' => [...($report->filters ?? []), '_scheduled_report_id' => $report->id],
                        ], $report->owner);
                        $report->update([
                            'last_run_at' => now(), 'next_run_at' => $this->nextRun($report->frequency, $report->next_run_at),
                            'last_error' => null, 'active' => $report->frequency !== 'once',
                        ]);
                    } catch (Throwable $exception) {
                        $report->update(['last_run_at' => now(), 'last_error' => mb_strimwidth($exception->getMessage(), 0, 1900)]);
                        report($exception);
                    }
                }
            });

        return self::SUCCESS;
    }

    private function nextRun(string $frequency, $from)
    {
        return match ($frequency) {
            'daily' => $from->addDay(),
            'weekly' => $from->addWeek(),
            'monthly' => $from->addMonthNoOverflow(),
            'semester' => $from->addMonthsNoOverflow(6),
            'annual' => $from->addYear(),
            default => null,
        };
    }
}
