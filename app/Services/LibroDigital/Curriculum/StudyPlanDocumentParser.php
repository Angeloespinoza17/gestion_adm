<?php

namespace App\Services\LibroDigital\Curriculum;

class StudyPlanDocumentParser extends GenericCurriculumDocumentParser
{
    public function supports(string $documentType): bool
    {
        return $documentType === 'study_plan';
    }
}
