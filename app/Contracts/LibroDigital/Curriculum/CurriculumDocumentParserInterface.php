<?php

namespace App\Contracts\LibroDigital\Curriculum;

interface CurriculumDocumentParserInterface
{
    public function supports(string $documentType): bool;

    /**
     * @param  array<string,mixed>  $classification
     * @param  list<array<string,mixed>>  $pages
     * @return array{sections:list<array<string,mixed>>,candidates:list<array<string,mixed>>,warnings:list<string>,summary:array<string,mixed>}
     */
    public function parse(array $classification, array $pages): array;
}
