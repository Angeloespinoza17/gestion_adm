<?php

namespace App\Services\LibroDigital;

use App\Models\LibroDigital\RegulatoryProfile;
use Illuminate\Support\Carbon;

class RetentionPolicyResolver
{
    public function retentionUntil(RegulatoryProfile $profile, string|Carbon $closedAt, ?string $recordType = null): Carbon
    {
        $rules = $profile->rules_snapshot ?? [];
        $overrides = (array) ($rules['retention']['record_types'] ?? []);
        $years = (int) ($recordType && isset($overrides[$recordType])
            ? $overrides[$recordType]
            : $profile->retention_years);

        return ($closedAt instanceof Carbon ? $closedAt->copy() : Carbon::parse($closedAt))
            ->endOfDay()
            ->addYears(max(1, $years));
    }
}
