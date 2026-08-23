<?php

namespace App\Services\LibroDigital\Curriculum;

class CurricularBasesDocumentParser extends GenericCurriculumDocumentParser
{
    public function supports(string $documentType): bool
    {
        return $documentType === 'curricular_bases';
    }
}
