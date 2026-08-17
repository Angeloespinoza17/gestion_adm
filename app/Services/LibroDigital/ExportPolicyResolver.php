<?php

namespace App\Services\LibroDigital;

use App\Models\LibroDigital\RegulatoryProfile;

class ExportPolicyResolver
{
    /** @return array<string, mixed> */
    public function for(RegulatoryProfile $profile): array
    {
        $rules = $profile->rules_snapshot ?? [];

        return [
            'ede_required' => (bool) ($rules['export']['ede_required'] ?? true),
            'official_validator_required' => true,
            'allow_release_without_validation' => false,
            'formats' => $rules['export']['formats'] ?? ['json', 'csv', 'sqlite'],
        ];
    }
}
