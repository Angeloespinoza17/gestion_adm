<?php

namespace App\Services\LibroDigital;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Models\LibroDigital\RegulatoryProfile;
use Illuminate\Support\Arr;

class RegulatoryRuleRegistry
{
    public function value(RegulatoryProfile $profile, string $rule, mixed $default = null): mixed
    {
        return Arr::get($profile->rules_snapshot ?? [], $rule, $default);
    }

    public function requireVerified(RegulatoryProfile $profile, string $rule): mixed
    {
        $value = $this->value($profile, $rule);
        if ($value === null || $value === 'compliance_blocker') {
            throw new LibroDigitalException(
                'La regla normativa requerida no está verificada para este perfil.',
                'COMPLIANCE_BLOCKER_REGULATORY_RULE',
                409,
                [['profile' => $profile->code, 'version' => $profile->version, 'rule' => $rule]],
            );
        }

        return $value;
    }

    /** @return array<string, mixed> */
    public function snapshot(RegulatoryProfile $profile): array
    {
        return [
            'profile_public_id' => $profile->public_id,
            'code' => $profile->code,
            'version' => $profile->version,
            'effective_from' => $profile->effective_from?->toDateString(),
            'effective_to' => $profile->effective_to?->toDateString(),
            'source_hash' => $profile->source_hash,
            'rules' => $profile->rules_snapshot ?? [],
        ];
    }
}
