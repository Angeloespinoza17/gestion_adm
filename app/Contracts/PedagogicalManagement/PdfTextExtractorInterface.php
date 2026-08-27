<?php

namespace App\Contracts\PedagogicalManagement;

interface PdfTextExtractorInterface
{
    /**
     * @return array{details:array<string,mixed>,pages:list<array{page_number:int,raw_text:string,normalized_text:string,has_images:bool,warnings:list<string>}>}
     */
    public function extract(string $absolutePath): array;

    public function name(): string;

    public function version(): string;
}
