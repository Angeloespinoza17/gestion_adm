<?php

namespace App\Http\Resources\LibroDigital;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->relationLoaded('catalogProfile') ? $this->catalogProfile : null;
        $configuredTypes = collect($profile?->education_types ?? [])->filter()->values();
        $derivedTypes = collect($this->getAttribute('derived_education_types') ?? [])->filter()->values();
        $educationTypes = $configuredTypes->isNotEmpty() ? $configuredTypes : $derivedTypes;
        $aliases = $this->relationLoaded('externalAliases')
            ? $this->externalAliases->where('active', true)->values()
            : collect();

        return [
            'id' => $this->id,
            'name' => $this->resolvedDisplayName(),
            'display_name' => $this->resolvedDisplayName(),
            'technical_name' => $this->name,
            'code' => $this->code,
            'official_code' => $this->official_code ?? null,
            'area' => $this->area,
            'type' => $profile?->subject_type ?? 'official',
            'education_types' => $educationTypes->all(),
            'color' => $this->color,
            'active' => (bool) $this->active,
            'description' => $profile?->description,
            'alias_count' => $aliases->count(),
            'aliases' => $aliases->map(fn ($alias): array => [
                'id' => $alias->id,
                'source_system' => $alias->source_system,
                'scope_code' => $alias->scope_code,
                'education_type' => $alias->education_type,
                'external_name' => $alias->external_name,
            ])->all(),
            'lock_version' => $this->updated_at?->getTimestamp() ?: 1,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
