<?php

namespace App\Contracts\PedagogicalManagement;

use App\DTO\PedagogicalManagement\InstrumentAnalysisContext;

interface InstrumentValidationRule
{
    /** @return list<array<string,mixed>> */
    public function validate(InstrumentAnalysisContext $context): array;
}
