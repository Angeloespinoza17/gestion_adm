<?php

namespace App\Jobs\PedagogicalManagement;

use App\Models\PedagogicalManagement\PedagogicalInstrumentAiReport;
use App\Services\PedagogicalManagement\PedagogicalAiReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GeneratePedagogicalAiReportJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public int $uniqueFor = 900;

    /** @var list<int> */
    public array $backoff = [15, 90, 240];

    public function __construct(public readonly int $reportId) {}

    public function uniqueId(): string
    {
        return 'pedagogical-ai-report:'.$this->reportId;
    }

    public function handle(PedagogicalAiReportService $service): void
    {
        $report = PedagogicalInstrumentAiReport::query()->find($this->reportId);
        if ($report) {
            $service->process($report);
        }
    }

    public function failed(Throwable $exception): void
    {
        app(PedagogicalAiReportService::class)->markFailed(
            $this->reportId,
            'OPENAI_REPORT_FAILED',
            'OpenAI no pudo completar el informe después de los reintentos. Puedes solicitarlo nuevamente.',
        );
    }
}
