<?php

namespace App\Services\LibroDigital;

use App\Models\LibroDigital\RegulatoryProfile;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class RegulatoryProfileResolver
{
    public function resolve(string|Carbon $date, string $educationType, ?string $modality = null): RegulatoryProfile
    {
        $effectiveOn = $date instanceof Carbon ? $date->toDateString() : Carbon::parse($date)->toDateString();
        $normalizedType = mb_strtolower(trim($educationType));

        $profiles = RegulatoryProfile::query()
            ->where('active', true)
            ->whereDate('effective_from', '<=', $effectiveOn)
            ->where(function ($query) use ($effectiveOn): void {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $effectiveOn);
            })
            ->orderByDesc('effective_from')
            ->get();

        $profile = $profiles->first(function (RegulatoryProfile $profile) use ($normalizedType, $modality): bool {
            $rules = $profile->rules_snapshot ?? [];
            $types = array_map('mb_strtolower', (array) ($rules['education_types'] ?? []));
            $modalities = array_map('mb_strtolower', (array) ($rules['modalities'] ?? []));

            return ($types === [] || in_array($normalizedType, $types, true))
                && ($modality === null || $modalities === [] || in_array(mb_strtolower($modality), $modalities, true));
        });

        if (! $profile) {
            throw ValidationException::withMessages([
                'regulatory_profile' => 'No existe un perfil normativo vigente para la fecha, nivel y modalidad seleccionados.',
            ]);
        }

        return $profile;
    }
}
