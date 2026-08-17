<?php

namespace App\Services\LibroDigital;

use App\Models\LibroDigital\RegulatoryProfile;

class SignaturePolicyResolver
{
    /** @return array<string, mixed> */
    public function for(RegulatoryProfile $profile): array
    {
        $rules = $profile->rules_snapshot ?? [];

        return [
            'required' => (bool) ($rules['signature']['required'] ?? true),
            'provider' => $rules['signature']['provider'] ?? 'mineduc_identity_verifier',
            'allows_local_fallback' => false,
            'requires_complete_attendance' => (bool) ($rules['signature']['requires_complete_attendance'] ?? true),
            'requires_pedagogical_record' => (bool) ($rules['signature']['requires_pedagogical_record'] ?? true),
        ];
    }
}
