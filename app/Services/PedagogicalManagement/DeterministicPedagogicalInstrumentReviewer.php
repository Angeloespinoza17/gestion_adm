<?php

namespace App\Services\PedagogicalManagement;

use App\Contracts\PedagogicalManagement\PdfTextExtractorInterface;
use App\Contracts\PedagogicalManagement\PedagogicalInstrumentReviewerInterface;
use App\DTO\PedagogicalManagement\InstrumentAnalysisContext;
use App\Models\PedagogicalManagement\PedagogicalInstrument;
use App\Models\PedagogicalManagement\PedagogicalInstrumentFile;

class DeterministicPedagogicalInstrumentReviewer implements PedagogicalInstrumentReviewerInterface
{
    public function __construct(
        private readonly PdfTextExtractorInterface $extractor,
        private readonly InstrumentFieldExtractor $fields,
        private readonly InstrumentValidationEngine $engine,
    ) {}

    public function isConfigured(): bool
    {
        return true;
    }

    public function provider(): string
    {
        return 'laravel-deterministic-rules';
    }

    public function model(): string
    {
        return $this->extractor->name().'@'.$this->extractor->version();
    }

    public function promptVersion(): string
    {
        return $this->engine->version();
    }

    public function review(
        PedagogicalInstrument $instrument,
        PedagogicalInstrumentFile $file,
        string $absolutePath,
    ): array {
        $extraction = $this->extractor->extract($absolutePath);
        $pages = array_values((array) ($extraction['pages'] ?? []));
        $normalizedText = collect($pages)
            ->pluck('normalized_text')
            ->filter(fn (mixed $text): bool => is_string($text) && trim($text) !== '')
            ->implode("\n\n");
        $extractedData = $this->fields->extract($pages, $file->original_filename);
        $findings = collect($this->engine->validate(new InstrumentAnalysisContext(
            $instrument,
            $file,
            $extractedData,
            $normalizedText,
        )))->reject(fn (array $finding): bool => ($finding['outcome'] ?? null) === 'pass')->values()->all();
        $errors = collect($findings)->filter(fn (array $finding): bool => ($finding['outcome'] ?? null) === 'fail')->values();
        $suggestions = collect($findings)->reject(fn (array $finding): bool => ($finding['outcome'] ?? null) === 'fail')->values();

        return [
            'summary' => sprintf(
                'Revisión determinística completada con %d observaciones críticas y %d aspectos para revisión profesional.',
                $errors->count(),
                $suggestions->count(),
            ),
            'errors' => $errors->all(),
            'suggestions' => $suggestions->all(),
            'findings' => $findings,
            'provider_response_id' => null,
            'model' => $this->model(),
            'usage' => [
                'pages' => count($pages),
                'text_characters' => mb_strlen($normalizedText),
                'external_requests' => 0,
            ],
            'extracted_text' => $normalizedText,
            'normalized_text' => $normalizedText,
            'extracted_data' => $extractedData,
        ];
    }
}
