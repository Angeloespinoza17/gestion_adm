<?php

namespace App\Services\LibroDigital\Curriculum;

class CurriculumPrioritizationParser extends GenericCurriculumDocumentParser
{
    public function supports(string $documentType): bool
    {
        return $documentType === 'curriculum_prioritization';
    }
}
