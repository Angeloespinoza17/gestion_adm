<?php

namespace App\Jobs\Remuneration;

use App\Services\Remuneration\Payslips\PayslipImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class AnalyzePayslipBatch implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 900;

    public array $backoff = [30, 120, 300];

    public int $uniqueFor = 1800;

    public function __construct(public readonly int $batchId)
    {
        $this->onQueue('remunerations');
    }

    public function uniqueId(): string
    {
        return 'payslip-analysis-'.$this->batchId;
    }

    public function handle(PayslipImportService $service): void
    {
        $service->analyze($this->batchId);
    }

    public function failed(?Throwable $exception): void
    {
        app(PayslipImportService::class)->fail($this->batchId);
    }
}
