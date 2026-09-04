<?php

namespace App\DTO\PedagogicalManagement;

final readonly class RenderedPresentationArtifacts
{
    /** @param list<string> $previews */
    public function __construct(public ?string $pdf, public array $previews) {}
}
