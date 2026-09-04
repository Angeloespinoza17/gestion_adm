<?php

namespace App\Services\PedagogicalManagement\ClassPresentations;

use App\Contracts\PedagogicalManagement\ClassPresentationContentGenerator;
use App\Enums\PedagogicalManagement\ClassPresentationFileType;
use App\Enums\PedagogicalManagement\ClassPresentationStatus;
use App\Exceptions\PedagogicalManagement\ClassPresentationGenerationException;
use App\Models\PedagogicalManagement\ClassPresentation;
use App\Models\PedagogicalManagement\ClassPresentationGenerationRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

class ClassPresentationGenerationService
{
    public function __construct(
        private readonly ClassPresentationContentGenerator $contentGenerator,
        private readonly ReferenceMaterialService $references,
        private readonly PowerPointGenerator $powerPoint,
        private readonly TeacherGuidePdfGenerator $teacherGuidePdf,
        private readonly OfficePresentationRenderer $renderer,
        private readonly PresentationArtifactQualityService $quality,
        private readonly TeacherGuideArtifactQualityService $teacherGuideQuality,
        private readonly PresentationFileStorage $files,
    ) {}

    public function process(ClassPresentation $presentation): void
    {
        $claimed = DB::transaction(function () use ($presentation): ?ClassPresentationGenerationRun {
            $locked = ClassPresentation::query()->whereKey($presentation->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, [ClassPresentationStatus::Queued, ClassPresentationStatus::Failed], true)) {
                return null;
            }
            $locked->forceFill(['status' => ClassPresentationStatus::PreparingContent, 'progress' => 10, 'failure_code' => null, 'failure_message' => null])->save();

            return $locked->generationRuns()->create([
                'version' => $locked->version, 'status' => ClassPresentationStatus::PreparingContent,
                'model' => $locked->model, 'started_at' => now(),
            ]);
        }, 3);
        if (! $claimed) {
            return;
        }

        $presentation->refresh()->load(['referenceFiles', 'files']);
        $workingDirectory = storage_path('app/private/tmp/class-presentations/'.$presentation->uuid.'-'.$claimed->uuid);
        $started = hrtime(true);
        try {
            if ($presentation->files->isNotEmpty()) {
                $this->files->purgeGeneratedFiles($presentation);
            }
            $materials = $this->references->extractAll($presentation);
            $this->transition($presentation, $claimed, ClassPresentationStatus::PreparingContent, 30);
            $generated = $this->contentGenerator->generate($presentation, $materials);
            $presentation->forceFill([
                'deck_json' => $generated->deck, 'model' => $generated->model,
                'openai_response_id' => $generated->responseId,
            ])->save();

            $this->transition($presentation, $claimed, ClassPresentationStatus::GeneratingPresentation, 55);
            $teacherGuidePdf = $this->teacherGuidePdf->generate($presentation, $generated->deck, $workingDirectory);
            $pptx = $this->powerPoint->generate($presentation, $generated->deck, $workingDirectory);
            $rendered = $this->renderer->render($pptx, $workingDirectory, $generated->deck, (bool) data_get($presentation->configuration, 'generate_pdf'));
            $this->transition($presentation, $claimed, ClassPresentationStatus::Validating, 75);
            $this->quality->validate($presentation, $pptx, $rendered->pdf, $rendered->previews);
            $this->teacherGuideQuality->validate($presentation, $teacherGuidePdf, $generated->deck);

            $presentation->forceFill(['progress' => 90])->save();
            $this->files->store($presentation, ClassPresentationFileType::PowerPoint, $pptx);
            if ($rendered->pdf && (bool) data_get($presentation->configuration, 'generate_pdf')) {
                $this->files->store($presentation, ClassPresentationFileType::Pdf, $rendered->pdf);
            }
            $this->files->store($presentation, ClassPresentationFileType::TeacherGuidePdf, $teacherGuidePdf, [
                'artifact_role' => 'teacher_guide',
                'schema_version' => (string) data_get($generated->deck, 'teacher_guide.schema_version', 'v1.0'),
            ]);
            $jsonPath = $workingDirectory.'/presentation.json';
            File::put($jsonPath, json_encode($generated->deck, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $this->files->store($presentation, ClassPresentationFileType::Json, $jsonPath);
            foreach ($rendered->previews as $index => $preview) {
                $metadata = ['slide_number' => $index + 1];
                $this->files->store($presentation, ClassPresentationFileType::Preview, $preview, $metadata);
                if ($index === 0) {
                    $this->files->store($presentation, ClassPresentationFileType::Thumbnail, $preview, $metadata);
                }
            }

            $duration = (int) round((hrtime(true) - $started) / 1_000_000);
            DB::transaction(function () use ($presentation, $claimed, $generated, $duration): void {
                ClassPresentation::query()->whereKey($presentation->id)->lockForUpdate()->update([
                    'status' => ClassPresentationStatus::Ready->value, 'progress' => 100, 'generated_at' => now(),
                    'failure_code' => null, 'failure_message' => null, 'updated_at' => now(),
                ]);
                ClassPresentationGenerationRun::query()->whereKey($claimed->id)->update([
                    'status' => ClassPresentationStatus::Ready->value, 'model' => $generated->model,
                    'openai_response_id' => $generated->responseId,
                    'input_tokens' => data_get($generated->usage, 'input_tokens'),
                    'output_tokens' => data_get($generated->usage, 'output_tokens'),
                    'total_tokens' => data_get($generated->usage, 'total_tokens'),
                    'duration_ms' => $duration, 'finished_at' => now(), 'updated_at' => now(),
                ]);
            }, 3);
        } catch (Throwable $exception) {
            $known = $exception instanceof ClassPresentationGenerationException;
            $code = $known ? $exception->failureCode : 'CLASS_PRESENTATION_GENERATION_FAILED';
            $message = $known ? $exception->getMessage() : 'No fue posible completar la presentación. La cola realizará un nuevo intento.';
            $this->markFailed($presentation->id, $claimed->id, $code, $message, (int) round((hrtime(true) - $started) / 1_000_000));
            throw $exception;
        } finally {
            if (is_dir($workingDirectory)) {
                File::deleteDirectory($workingDirectory);
            }
        }
    }

    public function markFailed(int $presentationId, ?int $runId, string $code, string $message, ?int $durationMs = null): void
    {
        DB::transaction(function () use ($presentationId, $runId, $code, $message, $durationMs): void {
            $presentation = ClassPresentation::query()->whereKey($presentationId)->lockForUpdate()->first();
            if ($presentation && $presentation->status !== ClassPresentationStatus::Ready) {
                $presentation->forceFill([
                    'status' => ClassPresentationStatus::Failed, 'failure_code' => $code,
                    'failure_message' => $message, 'updated_at' => now(),
                ])->save();
            }
            if ($runId) {
                ClassPresentationGenerationRun::query()->whereKey($runId)->update([
                    'status' => ClassPresentationStatus::Failed->value, 'failure_code' => $code,
                    'failure_message' => $message, 'duration_ms' => $durationMs, 'finished_at' => now(), 'updated_at' => now(),
                ]);
            }
        }, 3);
    }

    private function transition(ClassPresentation $presentation, ClassPresentationGenerationRun $run, ClassPresentationStatus $status, int $progress): void
    {
        $presentation->forceFill(['status' => $status, 'progress' => $progress])->save();
        $run->forceFill(['status' => $status])->save();
    }
}
