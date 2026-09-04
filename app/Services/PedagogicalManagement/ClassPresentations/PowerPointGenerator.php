<?php

namespace App\Services\PedagogicalManagement\ClassPresentations;

use App\Exceptions\PedagogicalManagement\ClassPresentationGenerationException;
use App\Models\PedagogicalManagement\ClassPresentation;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class PowerPointGenerator
{
    public function __construct(private readonly PresentationStyleContract $styleContract) {}

    /** @param array<string,mixed> $deck */
    public function generate(ClassPresentation $presentation, array $deck, string $workingDirectory): string
    {
        File::ensureDirectoryExists($workingDirectory, 0700, true);
        $input = $workingDirectory.'/deck-input.json';
        $output = $workingDirectory.'/presentation.pptx';
        $configuration = $this->styleContract->ensure(
            (array) $presentation->configuration,
            (array) data_get($presentation->curricular_snapshot, 'course', []),
        );
        File::put($input, json_encode([
            'title' => $presentation->title,
            'configuration' => $configuration,
            'curricular_snapshot' => $presentation->curricular_snapshot,
            'deck' => $deck,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        $script = (string) config('class_presentations.generation.builder_script');
        if (! is_file($script)) {
            throw new ClassPresentationGenerationException('No se encontró el constructor de PowerPoint.', 'PPTX_BUILDER_MISSING');
        }
        $process = new Process([(string) config('class_presentations.generation.node_binary', 'node'), $script, $input, $output], base_path());
        $process->setTimeout((int) config('class_presentations.generation.process_timeout_seconds', 180));
        $process->run();
        if (! $process->isSuccessful() || ! is_file($output) || filesize($output) === 0) {
            $diagnostic = app()->environment(['local', 'testing'])
                ? ' '.trim($process->getErrorOutput() ?: $process->getOutput())
                : '';
            throw new ClassPresentationGenerationException('No fue posible construir el PowerPoint editable.'.$diagnostic, 'PPTX_BUILD_FAILED');
        }

        return $output;
    }
}
