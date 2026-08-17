<?php

namespace App\Jobs;

use App\Models\LibroDigital\ReportExport;
use App\Services\LibroDigital\LibroDigitalReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateLibroDigitalReport implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 180];

    public function __construct(public readonly int $reportExportId) {}

    public function handle(LibroDigitalReportService $service): void
    {
        $export = ReportExport::query()->findOrFail($this->reportExportId);
        if (in_array($export->status->value, ['completed', 'expired', 'cancelled'], true)) {
            return;
        }

        $service->generate($export);
    }

    public function uniqueId(): string
    {
        return 'lcd-report-'.$this->reportExportId;
    }
}
