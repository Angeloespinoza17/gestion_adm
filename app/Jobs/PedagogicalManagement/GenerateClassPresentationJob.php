<?php

namespace App\Jobs\PedagogicalManagement;

use App\Enums\PedagogicalManagement\CanvaPublicationStatus;
use App\Enums\PedagogicalManagement\ClassPresentationStatus;
use App\Models\PedagogicalManagement\ClassPresentation;
use App\Services\PedagogicalManagement\ClassPresentations\ClassPresentationGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateClassPresentationJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 900;

    public int $uniqueFor = 1800;

    /** @var list<int> */
    public array $backoff = [20, 120, 300];

    public function __construct(public readonly int $presentationId) {}

    public function uniqueId(): string
    {
        return 'class-presentation:'.$this->presentationId;
    }

    public function handle(ClassPresentationGenerationService $service): void
    {
        $presentation = ClassPresentation::query()->find($this->presentationId);
        if ($presentation) {
            $service->process($presentation);
            $presentation->refresh();
            $provider = (string) ($presentation->presentation_provider ?: data_get($presentation->configuration, 'presentation_provider', 'powerpoint'));
            if ($provider === 'canva'
                && $presentation->status === ClassPresentationStatus::Ready
                && in_array($presentation->canva_status, [null, CanvaPublicationStatus::Pending, CanvaPublicationStatus::Submitting, CanvaPublicationStatus::InProgress], true)
            ) {
                PublishClassPresentationToCanvaJob::dispatch($presentation->id)->afterCommit();
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        app(ClassPresentationGenerationService::class)->markFailed(
            $this->presentationId, null, 'CLASS_PRESENTATION_RETRIES_EXHAUSTED',
            'No fue posible completar la presentación después de los reintentos. Puedes reintentarla desde el historial.',
        );
    }
}
