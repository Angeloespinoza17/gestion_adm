<?php

namespace App\Contracts\LibroDigital\Curriculum;

interface CurriculumDocumentClassifierInterface
{
    /**
     * @param  list<array<string,mixed>>  $pages
     * @param  array<string,mixed>  $hints
     * @return array<string,mixed>
     */
    public function classify(array $pages, array $hints = []): array;
}
