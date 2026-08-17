<?php

namespace App\Http\Resources\LibroDigital;

use App\Models\LibroDigital\CurriculumSource;
use App\Models\LibroDigital\LearningObjectiveSource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurriculumObjectiveResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $relationships = $this->relationLoaded('objectiveSources')
            ? $this->objectiveSources
            : collect();
        $canonicalCount = $relationships->where('source_role', 'canonical_text')->count();
        $verifiedCount = $relationships->filter(function (LearningObjectiveSource $relationship): bool {
            $source = $relationship->curriculumSource;

            return $source instanceof CurriculumSource
                && $source->status === 'verified'
                && filled($source->declared_sha256)
                && filled($source->verified_sha256)
                && hash_equals((string) $source->declared_sha256, (string) $source->verified_sha256);
        })->count();

        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'code' => $this->code,
            'description' => $this->description,
            'objective_type' => $this->objective_type,
            'level_code' => $this->level_code,
            'grade_code' => $this->grade_code,
            'curriculum_track' => $this->curriculum_track,
            'axis_code' => $this->axis_code,
            'unit_code' => $this->unit_code,
            'indicators' => $this->indicators ?? [],
            'active' => (bool) $this->active,
            'status' => $this->active ? 'active' : 'inactive',
            'source_page' => $this->source_page,
            'catalog' => $this->whenLoaded('curriculumCatalog', fn (): ?array => $this->curriculumCatalog ? [
                'id' => $this->curriculumCatalog->id,
                'public_id' => $this->curriculumCatalog->public_id,
                'code' => $this->curriculumCatalog->code,
                'name' => $this->curriculumCatalog->name,
                'version' => $this->curriculumCatalog->version,
                'authority' => $this->curriculumCatalog->authority,
                'source_url' => $this->publicUrl($this->curriculumCatalog->source_url),
                'source_hash' => $this->curriculumCatalog->source_hash,
                'effective_from' => $this->curriculumCatalog->effective_from?->toDateString(),
                'effective_to' => $this->curriculumCatalog->effective_to?->toDateString(),
                'active' => (bool) $this->curriculumCatalog->active,
            ] : null),
            'subject' => $this->whenLoaded('subject', fn (): ?array => $this->subject ? [
                'id' => $this->subject->id,
                'code' => $this->subject->code,
                'name' => $this->subject->name,
                'area' => $this->subject->area,
                'color' => $this->subject->color,
                'active' => (bool) $this->subject->active,
            ] : null),
            'sources' => $relationships->map(fn (LearningObjectiveSource $relationship): array => $this->source($relationship))->values()->all(),
            'traceability' => [
                'relationship_count' => $relationships->count(),
                'canonical_source_count' => $canonicalCount,
                'legal_basis_count' => $relationships->where('source_role', 'legal_basis')->count(),
                'verified_relationship_count' => $verifiedCount,
                'status' => $canonicalCount === 1 && $verifiedCount === $relationships->count() && $relationships->isNotEmpty()
                    ? 'verified'
                    : 'attention_required',
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    protected function source(LearningObjectiveSource $relationship): array
    {
        $source = $relationship->curriculumSource;
        $hashMatches = $source
            && filled($source->declared_sha256)
            && filled($source->verified_sha256)
            && hash_equals((string) $source->declared_sha256, (string) $source->verified_sha256);

        return [
            'relationship_id' => $relationship->id,
            'role' => $relationship->source_role,
            'locator' => $relationship->source_locator,
            'relationship_hash' => $relationship->relationship_hash,
            'id' => $source?->id,
            'public_id' => $source?->public_id,
            'source_key' => $source?->source_key,
            'scope' => $source?->source_scope,
            'name' => $source?->source_name,
            'authority' => $source?->authority,
            'document_number' => $source?->document_number,
            'url' => $this->publicUrl($source?->source_url),
            'status' => $source?->status,
            'curriculum_track' => $source?->curriculum_track,
            'objective_type' => $source?->objective_type,
            'declared_sha256' => $source?->declared_sha256,
            'verified_sha256' => $source?->verified_sha256,
            'hash_matches' => (bool) $hashMatches,
            'effective_from' => $source?->effective_from?->toDateString(),
            'effective_to' => $source?->effective_to?->toDateString(),
        ];
    }

    protected function publicUrl(mixed $value): ?string
    {
        $url = trim((string) $value);
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }
        $scheme = mb_strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $url : null;
    }
}
