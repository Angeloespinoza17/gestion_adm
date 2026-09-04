<?php

namespace App\DTO\PedagogicalManagement;

final readonly class GeneratedPresentationContent
{
    /** @param array<string,mixed> $deck @param array<string,mixed> $usage */
    public function __construct(
        public array $deck,
        public ?string $responseId,
        public string $model,
        public array $usage = [],
    ) {}
}
