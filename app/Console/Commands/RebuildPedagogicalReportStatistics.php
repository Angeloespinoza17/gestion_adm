<?php

namespace App\Console\Commands;

use App\Models\PedagogicalManagement\PedagogicalInstrumentReview;
use App\Services\PedagogicalManagement\PedagogicalReportProjectionService;
use Illuminate\Console\Command;

class RebuildPedagogicalReportStatistics extends Command
{
    protected $signature = 'pedagogical:rebuild-report-statistics {--school= : ID interno del establecimiento}';

    protected $description = 'Crea o actualiza las proyecciones estadísticas desde informes oficiales ya revisados';

    public function handle(PedagogicalReportProjectionService $projection): int
    {
        $processed = 0;
        $projected = 0;
        $query = PedagogicalInstrumentReview::query()
            ->whereNotNull('ai_report_id')
            ->with(['instrument', 'instrumentFile', 'aiReport'])
            ->when($this->option('school'), fn ($builder, $schoolId) => $builder
                ->whereHas('instrument', fn ($instrument) => $instrument->where('school_id', (int) $schoolId)));

        $query->chunkById(100, function ($reviews) use ($projection, &$processed, &$projected): void {
            foreach ($reviews as $review) {
                $processed++;
                if ($projection->projectReview($review)) {
                    $projected++;
                }
            }
            $this->output->write('.');
        });

        $this->newLine();
        $this->info("Revisiones procesadas: {$processed}. Proyecciones disponibles: {$projected}.");

        return self::SUCCESS;
    }
}
