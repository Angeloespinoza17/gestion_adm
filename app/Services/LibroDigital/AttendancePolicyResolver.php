<?php

namespace App\Services\LibroDigital;

use App\Models\LibroDigital\RegulatoryProfile;

class AttendancePolicyResolver
{
    /** @return array<string, mixed> */
    public function for(RegulatoryProfile $profile): array
    {
        $rules = $profile->rules_snapshot ?? [];

        return [
            'statuses' => ['present', 'absent', 'late', 'not_applicable'],
            'daily_resolution' => $rules['attendance']['daily_resolution'] ?? 'requires_configured_policy',
            'subsidy_resolution' => $rules['attendance']['subsidy_resolution'] ?? 'compliance_blocker',
            'parvularia_arrival_window_minutes' => $rules['attendance']['parvularia_arrival_window_minutes'] ?? null,
            'not_applicable_reasons' => (array) ($rules['attendance']['not_applicable_reasons'] ?? ['not_enrolled_yet', 'withdrawn_effective', 'authorized_non_applicability']),
            'requires_session_signature' => (bool) ($rules['attendance']['requires_session_signature'] ?? true),
        ];
    }
}
