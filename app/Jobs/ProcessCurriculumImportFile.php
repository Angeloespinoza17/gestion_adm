<?php

namespace App\Jobs;

use App\Models\LibroDigital\CurriculumImportFile;
use App\Services\LibroDigital\Curriculum\CurriculumPdfImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessCurriculumImportFile implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public function __construct(public readonly int $importFileId, public readonly bool $force = false) {}

    public function handle(CurriculumPdfImportService $service): void
    {
        $configuredLimit = mb_strtoupper(trim((string) config('libro_digital.curriculum_import.worker_memory_limit', '512M')));
        if (preg_match('/^(\d{3,4})M$/', $configuredLimit, $match) === 1
            && (int) $match[1] >= 256
            && (int) $match[1] <= 2048) {
            // smalot/pdfparser materializa la estructura completa del PDF. El
            // límite se aplica únicamente al worker dedicado, nunca al request
            // web, y permanece acotado/configurable para evitar OOM en programas
            // ministeriales extensos.
            ini_set('memory_limit', $configuredLimit);
        }
        $file = CurriculumImportFile::query()->findOrFail($this->importFileId);
        $service->process($file, $this->force);
    }

    public function uniqueId(): string
    {
        return 'lcd-curriculum-import-file-'.$this->importFileId;
    }
}
