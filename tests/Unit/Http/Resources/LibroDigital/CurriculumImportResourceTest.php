<?php

namespace Tests\Unit\Http\Resources\LibroDigital;

use App\Http\Resources\LibroDigital\CurriculumImportResource;
use App\Models\LibroDigital\CurriculumImportBatch;
use App\Models\LibroDigital\CurriculumImportEvidence;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Tests\TestCase;

class CurriculumImportResourceTest extends TestCase
{
    public function test_it_exposes_multisource_manifest_and_safe_evidence_metadata(): void
    {
        $batch = new CurriculumImportBatch;
        $batch->forceFill([
            'id' => 10,
            'public_id' => '01MULTISOURCEBATCH',
            'status' => CurriculumImportBatch::STATUS_VALIDATED,
            'lock_version' => 1,
            'catalog_code' => 'CAT-2026',
            'catalog_version' => '2026.1',
            'source_hash' => str_repeat('a', 64),
            'manifest_hash' => str_repeat('b', 64),
            'manifest' => [
                'source_evidence' => [
                    'required_source_keys' => ['BCEP_2018', 'BASES_1B_6B'],
                    'verified_source_keys' => ['BCEP_2018'],
                    'missing_source_keys' => ['BASES_1B_6B'],
                ],
            ],
        ]);
        $batch->evidences_count = 2;
        $batch->setRelation('curriculumCatalog', null);
        $batch->setRelation('requester', null);
        $batch->setRelation('activations', new Collection);
        $batch->setRelation('evidences', new Collection([
            $this->evidence('official_source_file', [
                'source_key' => 'BCEP_2018',
                'source_scope' => 'PARVULARIA_NT1_NT2',
                'hash_scope' => 'official_source_bytes_verified',
            ]),
            $this->evidence('normalized_curriculum_workbook', []),
        ]));

        $payload = (new CurriculumImportResource($batch))->resolve(Request::create('/'));

        $this->assertSame(['BCEP_2018', 'BASES_1B_6B'], $payload['source_evidence']['required_source_keys']);
        $this->assertSame(['BCEP_2018'], $payload['source_evidence']['verified_source_keys']);
        $this->assertSame(['BASES_1B_6B'], $payload['source_evidence']['missing_source_keys']);
        $this->assertSame('BCEP_2018', $payload['evidences'][0]['metadata']['source_key']);
        $this->assertSame('official_source_bytes_verified', $payload['evidences'][0]['metadata']['hash_scope']);
        $this->assertSame('normalized_xlsx_bytes_verified', $payload['evidences'][1]['metadata']['hash_scope']);
        $this->assertArrayNotHasKey('private_path', $payload['evidences'][0]);
    }

    /** @param array<string, mixed> $metadata */
    private function evidence(string $kind, array $metadata): CurriculumImportEvidence
    {
        $evidence = new CurriculumImportEvidence;
        $evidence->forceFill([
            'id' => random_int(1, 10_000),
            'public_id' => '01EVIDENCE'.random_int(10_000, 99_999),
            'evidence_kind' => $kind,
            'status' => CurriculumImportEvidence::STATUS_VERIFIED,
            'title' => 'Evidencia',
            'sha256' => str_repeat('c', 64),
            'metadata' => $metadata,
        ]);

        return $evidence;
    }
}
