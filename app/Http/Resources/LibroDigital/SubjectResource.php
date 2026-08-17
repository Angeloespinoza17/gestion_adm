<?php

namespace App\Http\Resources\LibroDigital;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'official_code' => $this->official_code ?? null,
            'area' => $this->area,
            'type' => $this->type ?? 'common',
            'color' => $this->color,
            'active' => (bool) $this->active,
            'description' => $this->description ?? null,
            'lock_version' => $this->updated_at?->getTimestamp() ?: 1,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
