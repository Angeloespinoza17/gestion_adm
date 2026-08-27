<?php

namespace App\Http\Resources\PedagogicalManagement;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;

class AnalysisRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $results = $this->whenLoaded('validationResults');

        return [
            'id' => $this->uuid, 'status' => $this->status?->value ?? $this->status,
            'extractor' => $this->extractor, 'extractor_version' => $this->extractor_version,
            'rules_version' => $this->rules_version, 'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(), 'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at?->toIso8601String(), 'updated_at' => $this->updated_at?->toIso8601String(),
            'summary' => $this->relationLoaded('validationResults') ? [
                'errors' => $this->validationResults->where('category', 'error')->count(),
                'suggestions' => $this->validationResults->where('category', 'suggestion')->count(),
            ] : null,
            'review_summary' => $this->extracted_data['summary'] ?? null,
            'results' => $results instanceof MissingValue
                ? $results
                : ValidationResultResource::collection($this->validationResults),
            'disclaimer' => 'Revisión mecánica y reproducible basada en reglas institucionales. Los resultados requieren criterio profesional humano y no constituyen una evaluación automática del contenido pedagógico.',
        ];
    }
}
