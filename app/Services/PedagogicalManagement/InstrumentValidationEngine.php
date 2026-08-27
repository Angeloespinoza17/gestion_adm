<?php

namespace App\Services\PedagogicalManagement;

use App\Contracts\PedagogicalManagement\InstrumentValidationRule;
use App\DTO\PedagogicalManagement\InstrumentAnalysisContext;
use App\Services\PedagogicalManagement\Validation\ArithmeticValidationRule;
use App\Services\PedagogicalManagement\Validation\CurriculumValidationRule;
use App\Services\PedagogicalManagement\Validation\FilenameTitleValidationRule;
use App\Services\PedagogicalManagement\Validation\StructureValidationRule;
use App\Services\PedagogicalManagement\Validation\TechnicalPdfValidationRule;
use App\Services\PedagogicalManagement\Validation\TechnicalSheetValidationRule;

class InstrumentValidationEngine
{
    /** @var list<InstrumentValidationRule> */
    private array $rules;

    public function __construct(
        TechnicalPdfValidationRule $technicalPdf,
        TechnicalSheetValidationRule $technicalSheet,
        CurriculumValidationRule $curriculum,
        StructureValidationRule $structure,
        ArithmeticValidationRule $arithmetic,
        FilenameTitleValidationRule $filenameTitle,
    ) {
        $this->rules = [$technicalPdf, $technicalSheet, $curriculum, $structure, $arithmetic, $filenameTitle];
    }

    /** @return list<array<string,mixed>> */
    public function validate(InstrumentAnalysisContext $context): array
    {
        return collect($this->rules)
            ->flatMap(fn (InstrumentValidationRule $rule): array => $rule->validate($context))
            ->values()->all();
    }

    public function version(): string
    {
        return (string) config('pedagogical_management.analysis.rules_version', 'deterministic-v1.0.0');
    }
}
