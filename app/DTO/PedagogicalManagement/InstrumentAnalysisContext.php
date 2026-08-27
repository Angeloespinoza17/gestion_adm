<?php

namespace App\DTO\PedagogicalManagement;

use App\Models\PedagogicalManagement\PedagogicalInstrument;
use App\Models\PedagogicalManagement\PedagogicalInstrumentFile;

final readonly class InstrumentAnalysisContext
{
    /** @param array<string,mixed> $extractedData */
    public function __construct(
        public PedagogicalInstrument $instrument,
        public PedagogicalInstrumentFile $file,
        public array $extractedData,
        public string $normalizedText,
    ) {}
}
