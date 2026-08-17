<?php

namespace App\Jobs;

use App\Models\LibroDigital\EdeExport;
use App\Services\LibroDigital\EdeExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class GenerateLibroDigitalEdeExport implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public int $tries = 2;

    public int $uniqueFor = 3600;

    /** @var array<int, int> */
    public array $backoff = [30, 180];

    public function __construct(public readonly int $exportId) {}

    public function handle(EdeExportService $service): void
    {
        $export = EdeExport::query()->findOrFail($this->exportId);
        if (in_array($export->status->value, ['generated', 'validating', 'validated', 'released', 'stale', 'revoked'], true)) {
            return;
        }

        $service->generate($export);
    }

    public function failed(\Throwable $exception): void
    {
        EdeExport::query()->whereKey($this->exportId)->update([
            'status' => 'preflight_failed',
            'error_summary' => mb_strimwidth($exception->getMessage(), 0, 1800),
            'lock_version' => DB::raw('lock_version + 1'),
        ]);
    }

    public function uniqueId(): string
    {
        return 'lcd-ede-export-'.$this->exportId;
    }
}
