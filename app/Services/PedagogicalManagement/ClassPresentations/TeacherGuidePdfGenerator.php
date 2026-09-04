<?php

namespace App\Services\PedagogicalManagement\ClassPresentations;

use App\Exceptions\PedagogicalManagement\ClassPresentationGenerationException;
use App\Models\PedagogicalManagement\ClassPresentation;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class TeacherGuidePdfGenerator
{
    /** @param array<string,mixed> $deck */
    public function generate(ClassPresentation $presentation, array $deck, string $workingDirectory): string
    {
        File::ensureDirectoryExists($workingDirectory, 0700, true);
        $input = $workingDirectory.'/teacher-guide-input.json';
        $output = $workingDirectory.'/teacher-guide.pdf';
        File::put($input, json_encode([
            'title' => $presentation->title,
            'configuration' => $presentation->configuration,
            'curricular_snapshot' => $presentation->curricular_snapshot,
            'deck' => $deck,
            'version' => $presentation->version,
            'generated_at' => now()->toIso8601String(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        $script = (string) config('class_presentations.generation.teacher_guide_builder_script');
        if (! is_file($script)) {
            throw new ClassPresentationGenerationException('No se encontró el constructor de la guía docente.', 'TEACHER_GUIDE_BUILDER_MISSING');
        }
        $process = new Process([
            (string) config('class_presentations.generation.node_binary', 'node'),
            $script,
            $input,
            $output,
        ], base_path());
        $process->setTimeout((int) config('class_presentations.generation.teacher_guide_process_timeout_seconds', 180));
        $process->run();
        if (! $process->isSuccessful() || ! is_file($output) || filesize($output) === 0) {
            $diagnostic = app()->environment(['local', 'testing'])
                ? ' '.trim($process->getErrorOutput() ?: $process->getOutput())
                : '';
            throw new ClassPresentationGenerationException('No fue posible construir la guía docente en PDF.'.$diagnostic, 'TEACHER_GUIDE_BUILD_FAILED');
        }

        return $output;
    }
}
