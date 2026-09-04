<?php

namespace App\Services\PedagogicalManagement\ClassPresentations;

use App\DTO\PedagogicalManagement\RenderedPresentationArtifacts;
use App\Exceptions\PedagogicalManagement\ClassPresentationGenerationException;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class OfficePresentationRenderer
{
    /** @param array<string,mixed> $deck */
    public function render(string $pptx, string $workingDirectory, array $deck, bool $pdfRequired): RenderedPresentationArtifacts
    {
        $renderDirectory = $workingDirectory.'/rendered';
        File::ensureDirectoryExists($renderDirectory, 0700, true);
        if (! $this->commandAvailable((string) config('class_presentations.generation.libreoffice_binary', 'soffice'))) {
            if ($pdfRequired) {
                throw new ClassPresentationGenerationException('LibreOffice no está disponible para generar el PDF solicitado.', 'LIBREOFFICE_UNAVAILABLE');
            }

            return new RenderedPresentationArtifacts(null, $this->fallbackSvgPreviews($deck, $renderDirectory));
        }

        $office = new Process([
            (string) config('class_presentations.generation.libreoffice_binary', 'soffice'),
            '--headless', '--convert-to', 'pdf', '--outdir', $renderDirectory, $pptx,
        ]);
        $office->setTimeout((int) config('class_presentations.generation.external_process_timeout_seconds', 180));
        $office->run();
        $pdf = $renderDirectory.'/'.pathinfo($pptx, PATHINFO_FILENAME).'.pdf';
        if (! $office->isSuccessful() || ! is_file($pdf) || filesize($pdf) === 0) {
            if ($pdfRequired) {
                throw new ClassPresentationGenerationException('LibreOffice no pudo convertir la presentación a PDF.', 'PDF_RENDER_FAILED');
            }

            return new RenderedPresentationArtifacts(null, $this->fallbackSvgPreviews($deck, $renderDirectory));
        }

        $previews = [];
        $pdftoppm = (string) config('class_presentations.generation.pdftoppm_binary', 'pdftoppm');
        if ($this->commandAvailable($pdftoppm, ['-v'])) {
            $prefix = $renderDirectory.'/slide';
            $render = new Process([$pdftoppm, '-png', '-r', '120', $pdf, $prefix]);
            $render->setTimeout((int) config('class_presentations.generation.external_process_timeout_seconds', 180));
            $render->run();
            if ($render->isSuccessful()) {
                $previews = glob($prefix.'-*.png') ?: [];
                natsort($previews);
                $previews = array_values($previews);
            }
        }
        if ($previews === []) {
            $previews = $this->fallbackSvgPreviews($deck, $renderDirectory);
        }

        return new RenderedPresentationArtifacts($pdf, $previews);
    }

    /** @param list<string> $versionArguments */
    private function commandAvailable(string $binary, array $versionArguments = ['--version']): bool
    {
        $process = new Process([$binary, ...$versionArguments]);
        $process->setTimeout(10);
        try {
            $process->run();

            return $process->isSuccessful();
        } catch (\Throwable) {
            return false;
        }
    }

    /** @param array<string,mixed> $deck @return list<string> */
    private function fallbackSvgPreviews(array $deck, string $directory): array
    {
        $paths = [];
        foreach (array_values((array) ($deck['slides'] ?? [])) as $index => $slide) {
            $title = htmlspecialchars((string) ($slide['title'] ?? 'Diapositiva'), ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $idea = htmlspecialchars((string) ($slide['main_idea'] ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $number = $index + 1;
            $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1280" height="720" viewBox="0 0 1280 720" role="img" aria-label="Previsualización de la diapositiva {$number}">
  <rect width="1280" height="720" fill="#f7fafc"/>
  <rect x="0" y="0" width="26" height="720" fill="#1a8d86"/>
  <text x="82" y="112" font-family="Arial, sans-serif" font-size="46" font-weight="700" fill="#17243a">{$title}</text>
  <line x1="82" y1="145" x2="190" y2="145" stroke="#f2a65a" stroke-width="8"/>
  <foreignObject x="82" y="205" width="1090" height="360"><div xmlns="http://www.w3.org/1999/xhtml" style="font-family:Arial,sans-serif;font-size:31px;line-height:1.35;color:#334155">{$idea}</div></foreignObject>
  <text x="82" y="675" font-family="Arial, sans-serif" font-size="18" fill="#6b7c93">Vista simplificada · {$number}</text>
</svg>
SVG;
            $path = $directory.'/fallback-slide-'.$number.'.svg';
            File::put($path, $svg);
            $paths[] = $path;
        }

        return $paths;
    }
}
