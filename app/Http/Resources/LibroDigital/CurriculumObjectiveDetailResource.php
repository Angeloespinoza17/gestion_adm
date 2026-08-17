<?php

namespace App\Http\Resources\LibroDigital;

use App\Models\LibroDigital\LearningObjectiveSource;
use Illuminate\Http\Request;

class CurriculumObjectiveDetailResource extends CurriculumObjectiveResource
{
    public function toArray(Request $request): array
    {
        $payload = parent::toArray($request);
        $payload['sources'] = $this->relationLoaded('objectiveSources')
            ? $this->objectiveSources->map(function (LearningObjectiveSource $relationship): array {
                $payload = $this->source($relationship);
                $source = $relationship->curriculumSource;
                $normative = $source?->normativeSource;

                $payload['normative_source'] = $normative ? [
                    'id' => $normative->id,
                    'public_id' => $normative->public_id,
                    'title' => $normative->title,
                    'authority' => $normative->authority,
                    'document_number' => $normative->document_number,
                    'source_url' => $this->publicUrl($normative->source_url),
                    'sha256' => $normative->sha256,
                    'status' => $normative->status,
                    'published_on' => $normative->published_on?->toDateString(),
                    'consulted_at' => $normative->consulted_at?->toIso8601String(),
                ] : null;

                return $payload;
            })->values()->all()
            : [];

        return $payload;
    }
}
