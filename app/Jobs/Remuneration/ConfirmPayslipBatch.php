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

class ConfirmPayslipBatch implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public array $backoff = [30, 120, 300];

    public int $uniqueFor = 1200;

    /** @param array<int,array<string,mixed>> $corrections */
    public function __construct(
        public readonly int $batchId,
        public readonly int $actorId,
        public readonly array $corrections = [],
        public readonly string $duplicateAction = 'reject',
    ) {
        $this->onQueue('remunerations');
    }

    public function uniqueId(): string
    {
        return 'payslip-confirm-'.$this->batchId;
    }

    public function handle(PayslipImportService $service): void
    {
        $service->confirm($this->batchId, $this->actorId, $this->corrections, $this->duplicateAction);
    }

    public function failed(?Throwable $exception): void
    {
        app(PayslipImportService::class)->fail($this->batchId);
    }
}
