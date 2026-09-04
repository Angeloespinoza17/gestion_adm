<?php

namespace App\Contracts\PedagogicalManagement;

use App\DTO\PedagogicalManagement\GeneratedPresentationContent;
use App\Models\PedagogicalManagement\ClassPresentation;

interface ClassPresentationContentGenerator
{
    public function isConfigured(): bool;

    /** @param list<array{name:string,content:string}> $referenceMaterials */
    public function generate(ClassPresentation $presentation, array $referenceMaterials): GeneratedPresentationContent;
}
