<?php

namespace App\Jobs\PedagogicalManagement;

use App\Models\PedagogicalManagement\PedagogicalInstrumentAnalysisRun;
use App\Services\PedagogicalManagement\PedagogicalInstrumentAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class AnalyzePedagogicalInstrumentJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public int $uniqueFor = 900;

    /** @var list<int> */
    public array $backoff = [10, 60, 180];

    public function __construct(public readonly int $analysisRunId) {}

    public function uniqueId(): string
    {
        return 'pedagogical-instrument-analysis:'.$this->analysisRunId;
    }

    public function handle(PedagogicalInstrumentAnalysisService $service): void
    {
        $run = PedagogicalInstrumentAnalysisRun::query()->find($this->analysisRunId);
        if ($run) {
            $service->process($run);
        }
    }

    public function failed(Throwable $exception): void
    {
        app(PedagogicalInstrumentAnalysisService::class)->markFailed(
            $this->analysisRunId,
            'DETERMINISTIC_REVIEW_FAILED',
            'La revisión determinística agotó sus reintentos. Intenta analizar nuevamente más tarde.',
        );
    }
}
