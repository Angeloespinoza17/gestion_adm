<?php

namespace App\Http\Resources\LibroDigital;

use App\Models\LibroDigital\CurriculumImportBatch;
use App\Models\LibroDigital\CurriculumImportEvidence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurriculumImportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var CurriculumImportBatch $batch */
        $batch = $this->resource;
        $activation = $batch->relationLoaded('activations')
            ? $batch->activations->sortByDesc('id')->first()
            : null;
        $user = $request->user();
        $isRequester = $user !== null && (int) $batch->requested_by === (int) $user->id;

        return [
            'id' => $batch->id,
            'public_id' => $batch->public_id,
            'school_id' => $batch->school_id,
            'academic_year_id' => $batch->academic_year_id,
            'curriculum_catalog_id' => $batch->curriculum_catalog_id,
            'status' => (string) $batch->status,
            'lock_version' => max(1, (int) $batch->lock_version),
            'catalog_code' => $batch->catalog_code,
            'catalog_name' => $batch->curriculumCatalog?->name
                ?? data_get($batch->manifest, 'catalog.name')
                ?? data_get($batch->metadata, 'catalog_name'),
            'catalog_version' => $batch->catalog_version,
            'format' => [
                'type' => $batch->import_format,
                'version' => $batch->format_version,
            ],
            'file' => [
                'original_name' => $batch->original_name,
                'mime_type' => $batch->detected_mime_type,
                'size_bytes' => (int) ($batch->size_bytes ?? 0),
                'sha256' => $batch->source_hash,
                'declared_source_sha256' => $batch->declared_source_hash,
            ],
            'integrity' => [
                'source_sha256' => $batch->source_hash,
                'manifest_sha256' => $batch->manifest_hash,
                'evidence_count' => (int) ($batch->evidences_count ?? 0),
            ],
            'source_evidence' => [
                'required_source_keys' => $this->sourceKeys($batch, 'required_source_keys'),
                'verified_source_keys' => $this->sourceKeys($batch, 'verified_source_keys'),
                'missing_source_keys' => $this->sourceKeys($batch, 'missing_source_keys'),
            ],
            'manifest' => $batch->manifest,
            'validation' => [
                'valid' => (int) $batch->error_count === 0
                    && in_array((string) $batch->status, [
                        CurriculumImportBatch::STATUS_VALIDATED,
                        CurriculumImportBatch::STATUS_APPROVED,
                        CurriculumImportBatch::STATUS_ACTIVATED,
                    ], true),
                'errors' => data_get($batch->validation_errors, 'errors', $batch->validation_errors ?? []),
                'warnings' => data_get($batch->validation_errors, 'warnings', []),
                'error_summary' => $batch->error_summary,
                'validated_at' => $batch->validated_at?->toIso8601String(),
            ],
            'counts' => [
                'total_rows' => (int) $batch->total_row_count,
                'catalog_rows' => (int) $batch->catalog_row_count,
                'objective_rows' => (int) $batch->objective_row_count,
                'link_rows' => (int) $batch->link_row_count,
                'reference_rows' => (int) $batch->reference_row_count,
                'valid_rows' => (int) $batch->valid_row_count,
                'invalid_rows' => (int) $batch->invalid_row_count,
                'warnings' => (int) $batch->warning_count,
                'errors' => (int) $batch->error_count,
                'imported_rows' => (int) $batch->imported_row_count,
                'skipped_rows' => (int) $batch->skipped_row_count,
                'objectives' => (int) $batch->objective_count,
                'sources' => (int) data_get($batch->manifest, 'counts.sources', 0),
                'objective_sources' => (int) data_get($batch->manifest, 'counts.objective_sources', 0),
                'oa' => (int) $batch->oa_count,
                'oat' => (int) $batch->oat_count,
                'links' => (int) $batch->subject_link_count,
                'subject_links' => (int) $batch->subject_link_count,
            ],
            'catalog' => $this->whenLoaded('curriculumCatalog', fn (): ?array => $batch->curriculumCatalog ? [
                'id' => $batch->curriculumCatalog->id,
                'public_id' => $batch->curriculumCatalog->public_id,
                'code' => $batch->curriculumCatalog->code,
                'name' => $batch->curriculumCatalog->name,
                'version' => $batch->curriculumCatalog->version,
                'source_hash' => $batch->curriculumCatalog->source_hash,
                'active' => (bool) $batch->curriculumCatalog->active,
            ] : null),
            'requester' => $this->whenLoaded('requester', fn (): ?array => $batch->requester ? [
                'id' => $batch->requester->id,
                'name' => $batch->requester->name,
            ] : null),
            'activation' => $activation ? [
                'id' => $activation->id,
                'public_id' => $activation->public_id,
                'status' => $activation->status,
                'activation_version' => (int) $activation->activation_version,
                'approved_by' => $activation->approved_by,
                'approved_at' => $activation->approved_at?->toIso8601String(),
                'activated_by' => $activation->activated_by,
                'activated_at' => $activation->activated_at?->toIso8601String(),
                'decision_notes' => $activation->decision_notes,
                'decision_hash' => $activation->decision_hash,
            ] : null,
            'evidences' => $this->whenLoaded('evidences', fn () => $batch->evidences->map(fn ($evidence): array => [
                'id' => $evidence->id,
                'public_id' => $evidence->public_id,
                'kind' => $evidence->evidence_kind,
                'status' => $evidence->status,
                'title' => $evidence->title,
                'mime_type' => $evidence->detected_mime_type,
                'size_bytes' => (int) ($evidence->size_bytes ?? 0),
                'sha256' => $evidence->sha256,
                'metadata' => $this->evidenceMetadata($evidence),
                'captured_at' => $evidence->captured_at?->toIso8601String(),
                'verified_at' => $evidence->verified_at?->toIso8601String(),
            ])->values()->all()),
            'capabilities' => [
                'can_approve' => (bool) ($user?->hasPermission('libro_digital.curriculum.approve'))
                    && ! $isRequester
                    && (string) $batch->status === CurriculumImportBatch::STATUS_VALIDATED,
                'can_activate' => (bool) ($user?->hasPermission('libro_digital.curriculum.activate'))
                    && ! $isRequester
                    && (string) $batch->status === CurriculumImportBatch::STATUS_APPROVED,
                'can_retry' => (bool) ($user?->hasPermission('libro_digital.curriculum.import'))
                    && (string) $batch->status === CurriculumImportBatch::STATUS_INVALID,
            ],
            'requested_at' => $batch->requested_at?->toIso8601String(),
            'completed_at' => $batch->completed_at?->toIso8601String(),
            'failed_at' => $batch->failed_at?->toIso8601String(),
            'created_at' => $batch->created_at?->toIso8601String(),
            'updated_at' => $batch->updated_at?->toIso8601String(),
        ];
    }

    /** @return list<string> */
    private function sourceKeys(CurriculumImportBatch $batch, string $key): array
    {
        foreach ([
            data_get($batch->manifest, 'source_evidence.'.$key),
            data_get($batch->manifest, $key),
            data_get($batch->metadata, 'source_evidence.'.$key),
            data_get($batch->metadata, $key),
        ] as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            return collect($candidate)
                ->filter(fn (mixed $value): bool => is_string($value) || is_int($value))
                ->map(fn (string|int $value): string => (string) $value)
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return [];
    }

    /** @return array{source_key:?string, source_scope:?string, hash_scope:?string} */
    private function evidenceMetadata(CurriculumImportEvidence $evidence): array
    {
        $metadata = (array) ($evidence->metadata ?? []);
        $hashScope = data_get($metadata, 'hash_scope')
            ?? data_get($evidence->storage_metadata, 'hash_scope')
            ?? data_get($evidence->manifest, 'hash_scope');
        if ($hashScope === null && $evidence->evidence_kind === 'normalized_curriculum_workbook') {
            $hashScope = 'normalized_xlsx_bytes_verified';
        }

        return [
            'source_key' => data_get($metadata, 'source_key') ?? data_get($evidence->manifest, 'source_key'),
            'source_scope' => data_get($metadata, 'source_scope') ?? data_get($evidence->manifest, 'source_scope'),
            'hash_scope' => $hashScope,
        ];
    }
}
