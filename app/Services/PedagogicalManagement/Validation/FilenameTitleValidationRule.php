<?php

namespace App\Services\PedagogicalManagement\Validation;

use App\Contracts\PedagogicalManagement\InstrumentValidationRule;
use App\DTO\PedagogicalManagement\InstrumentAnalysisContext;
use App\Services\PedagogicalManagement\FilenameTitleSimilarity;

class FilenameTitleValidationRule implements InstrumentValidationRule
{
    use ValidationRuleSupport;

    public function __construct(private readonly FilenameTitleSimilarity $similarity) {}

    public function validate(InstrumentAnalysisContext $context): array
    {
        $detectedTitle = (string) data_get($context->extractedData, 'title.value', '');
        if ($detectedTitle === '') {
            return [$this->notEvaluable('FILENAME_TITLE_MISMATCH', 'structure', 'Título no evaluable automáticamente', 'No se detectó un título interno confiable para efectuar la comparación léxica.')];
        }
        $filenameComparison = $this->similarity->compare($context->file->original_filename, $detectedTitle);
        $registeredComparison = $this->similarity->compare($context->instrument->title.'.pdf', $detectedTitle);
        $threshold = (float) config('pedagogical_management.analysis.filename_title_similarity_threshold', 0.28);
        $minimum = min($filenameComparison['score'], $registeredComparison['score']);

        return [$minimum < $threshold
            ? $this->finding('FILENAME_TITLE_MISMATCH', 'structure', 'warning', 'warning', 'heuristic', 'Nombre o título posiblemente inconsistente', 'La similitud léxica transparente entre archivo, ficha y título interno es baja. Revisa manualmente el documento.', ['detected_title' => $detectedTitle, 'filename_score' => $filenameComparison['score'], 'registered_title_score' => $registeredComparison['score'], 'method' => $filenameComparison['method']], ['minimum_score' => $threshold], excerpt: data_get($context->extractedData, 'title.source_excerpt'), page: data_get($context->extractedData, 'title.page'))
            : $this->pass('FILENAME_TITLE_MISMATCH', 'structure', 'Nombre y título sin conflicto léxico', 'La similitud de términos supera el umbral configurado.')];
    }
}
