<?php

namespace App\Services\LibroDigital;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Exceptions\LibroDigital\VersionConflictException;
use App\Models\AcademicYear;
use App\Models\LibroDigital\CurriculumCatalog;
use App\Models\LibroDigital\CurriculumCatalogActivation;
use App\Models\LibroDigital\CurriculumImportBatch;
use App\Models\LibroDigital\CurriculumImportEvidence;
use App\Models\LibroDigital\CurriculumSource;
use App\Models\LibroDigital\LearningObjective;
use App\Models\LibroDigital\LearningObjectiveSource;
use App\Models\LibroDigital\NormativeSource;
use App\Models\LibroDigital\School;
use App\Models\LibroDigital\SubjectCurriculumLink;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class CurriculumImportService
{
    private const MAX_EVIDENCE_BYTES = 20 * 1024 * 1024;

    public function __construct(
        private readonly CurriculumXlsxReader $reader,
        private readonly CurriculumImportValidator $validator,
        private readonly CurriculumCorpusHasher $corpus,
        private readonly CanonicalJson $canonical,
        private readonly AuditEventWriter $audit,
    ) {}

    /** @return array{batch:CurriculumImportBatch,created:bool} */
    public function validateUpload(
        UploadedFile $file,
        School $school,
        AcademicYear $year,
        ?User $actor,
        Request $request,
    ): array {
        $this->assertSchoolYear($school, $year);
        $realPath = $file->getRealPath();
        if (! is_string($realPath)) {
            throw new LibroDigitalException('No se pudo acceder al archivo temporal.', 'LCD_CURRICULUM_UPLOAD_INVALID', 422);
        }

        $workbook = $this->reader->read($realPath);
        $sourceHash = (string) $workbook['file_hash'];
        $evidenceFiles = $this->evidenceFiles($request);
        $existing = CurriculumImportBatch::query()
            ->where('school_id', $school->id)
            ->where('academic_year_id', $year->id)
            ->where('source_hash', $sourceHash)
            ->first();
        if ($existing) {
            $attached = $this->attachToExistingBatch($existing, $evidenceFiles, $actor, $request);

            return ['batch' => $attached, 'created' => false];
        }

        $result = $this->validator->validate($workbook, $school, $year);
        $catalog = (array) ($result['payload']['catalog'] ?? []);
        $catalogCode = (string) ($catalog['code'] ?: 'INVALID-'.substr($sourceHash, 0, 12));
        $catalogVersion = (string) ($catalog['version'] ?: 'invalid');
        $catalogPayloadHash = (string) data_get($result, 'manifest.catalog_payload_hash', '');
        $this->assertVersionAvailable(
            $catalogCode,
            $catalogVersion,
            $catalogPayloadHash,
            $school,
            $year,
        );

        $idempotencyHash = $this->idempotencyHash($request, $sourceHash);
        $usedKey = CurriculumImportBatch::query()
            ->where('school_id', $school->id)
            ->where('academic_year_id', $year->id)
            ->where('idempotency_key', $idempotencyHash)
            ->first();
        if ($usedKey) {
            if (! hash_equals((string) $usedKey->source_hash, $sourceHash)) {
                throw new LibroDigitalException(
                    'La clave de idempotencia ya fue usada con otro archivo.',
                    'LCD_CURRICULUM_IDEMPOTENCY_REUSED',
                    409,
                );
            }

            $attached = $this->attachToExistingBatch($usedKey, $evidenceFiles, $actor, $request);

            return ['batch' => $attached, 'created' => false];
        }

        $privatePath = $this->archive($file, $school, $year, $sourceHash);
        $errors = (array) $result['errors'];
        $warnings = (array) $result['warnings'];
        $counts = (array) ($result['manifest']['counts'] ?? []);
        $totalRows = count((array) $workbook['catalogs'])
            + count((array) $workbook['objectives'])
            + count((array) $workbook['sources'])
            + count((array) $workbook['objective_sources'])
            + count((array) $workbook['links'])
            + count((array) $workbook['references']);
        $invalidRows = collect($errors)
            ->filter(fn (array $error): bool => filled($error['sheet'] ?? null) && filled($error['row'] ?? null))
            ->unique(fn (array $error): string => $error['sheet'].':'.$error['row'])
            ->count();

        try {
            $batch = DB::transaction(function () use (
                $school, $year, $actor, $request, $file, $workbook, $result, $catalog,
                $catalogCode, $catalogVersion, $sourceHash, $idempotencyHash, $privatePath,
                $errors, $warnings, $counts, $totalRows, $invalidRows, $evidenceFiles,
            ): CurriculumImportBatch {
                $batch = CurriculumImportBatch::query()->create([
                    'school_id' => $school->id,
                    'academic_year_id' => $year->id,
                    'idempotency_key' => $idempotencyHash,
                    'status' => $result['valid'] ? CurriculumImportBatch::STATUS_VALIDATED : CurriculumImportBatch::STATUS_INVALID,
                    'catalog_code' => $catalogCode,
                    'catalog_version' => $catalogVersion,
                    'import_format' => 'xlsx',
                    'format_version' => 'lcd-curriculum-import/v1',
                    'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                    'detected_mime_type' => $file->getMimeType(),
                    'size_bytes' => (int) $workbook['size_bytes'],
                    'disk' => (string) config('libro_digital.storage.disk', 'local'),
                    'private_path' => $privatePath,
                    'storage_metadata' => ['encrypted' => true, 'cipher' => config('app.cipher')],
                    'source_hash' => $sourceHash,
                    'declared_source_hash' => $catalog['declared_source_hash'] ?? null,
                    'manifest' => $result['manifest'],
                    'manifest_hash' => $result['manifest_hash'],
                    'validated_payload_encrypted' => $result['valid'] ? $result['payload'] : null,
                    'validation_errors' => ['errors' => $errors, 'warnings' => $warnings],
                    'total_row_count' => $totalRows,
                    'catalog_row_count' => count((array) $workbook['catalogs']),
                    'objective_row_count' => count((array) $workbook['objectives']),
                    'link_row_count' => count((array) $workbook['links']),
                    'reference_row_count' => count((array) $workbook['references']),
                    'valid_row_count' => max(0, $totalRows - $invalidRows),
                    'invalid_row_count' => $invalidRows,
                    'warning_count' => count($warnings),
                    'error_count' => count($errors),
                    'objective_count' => (int) ($counts['active_objectives'] ?? 0),
                    'oa_count' => collect($result['payload']['objectives'] ?? [])->where('active', true)->where('objective_type', 'OA')->count(),
                    'oat_count' => collect($result['payload']['objectives'] ?? [])->where('active', true)->where('objective_type', 'OAT')->count(),
                    'subject_link_count' => (int) ($counts['active_links'] ?? 0),
                    'requested_by' => $actor?->id,
                    'requested_at' => now('UTC'),
                    'validated_at' => now('UTC'),
                    'failed_at' => $result['valid'] ? null : now('UTC'),
                    'error_summary' => $result['valid'] ? null : 'La validación encontró '.count($errors).' observaciones bloqueantes.',
                    'metadata' => [
                        'coverage' => $result['payload']['coverage'] ?? [],
                        'correlation_id' => $request->attributes->get('lcd_correlation_id'),
                    ],
                ]);

                CurriculumImportEvidence::query()->create([
                    'school_id' => $school->id,
                    'academic_year_id' => $year->id,
                    'import_batch_id' => $batch->id,
                    'evidence_kind' => 'normalized_curriculum_workbook',
                    'status' => CurriculumImportEvidence::STATUS_VERIFIED,
                    'title' => 'Archivo fuente de importación curricular',
                    'description' => 'Artefacto XLSX original, almacenado cifrado y fijado por SHA-256.',
                    'source_url' => $catalog['source_url'] ?? null,
                    'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                    'detected_mime_type' => $file->getMimeType(),
                    'extension' => 'xlsx',
                    'size_bytes' => (int) $workbook['size_bytes'],
                    'disk' => (string) config('libro_digital.storage.disk', 'local'),
                    'private_path' => $privatePath,
                    'sha256' => $sourceHash,
                    'storage_metadata' => ['encrypted' => true],
                    'manifest' => $result['manifest'],
                    'captured_by' => $actor?->id,
                    'captured_at' => now('UTC'),
                    'verified_by' => $actor?->id,
                    'verified_at' => now('UTC'),
                    'verification_notes' => 'Integridad OOXML y hash verificados durante la carga.',
                    'metadata' => [
                        'hash_scope' => 'normalized_xlsx_bytes_verified',
                        'evidence_kind' => 'normalized_curriculum_workbook',
                    ],
                ]);

                if ($result['valid']) {
                    $this->storeSourceDeclarations($batch, (array) $result['payload'], $actor);
                    $this->storeOfficialEvidenceFiles(
                        $batch,
                        (array) $result['payload'],
                        $evidenceFiles,
                        $actor,
                    );
                    $this->refreshSourceEvidenceManifest($batch, (array) $result['payload']);
                }

                return $batch;
            }, 3);
        } catch (QueryException $exception) {
            $concurrent = CurriculumImportBatch::query()
                ->where('school_id', $school->id)->where('academic_year_id', $year->id)
                ->where('source_hash', $sourceHash)->first();
            if ($concurrent) {
                return ['batch' => $concurrent, 'created' => false];
            }
            throw $exception;
        }

        $this->audit->write(
            eventType: 'curriculum.import.validated',
            action: 'validate',
            auditable: $batch,
            actor: $actor,
            schoolId: $school->id,
            academicYearId: $year->id,
            after: $this->auditSnapshot($batch),
            reason: $result['valid'] ? 'Importación curricular validada.' : 'Importación curricular rechazada por validación.',
            request: $request,
        );

        return ['batch' => $batch->fresh(), 'created' => true];
    }

    public function approve(
        CurriculumImportBatch $batch,
        User $actor,
        string $note,
        int $expectedVersion,
        Request $request,
    ): CurriculumImportBatch {
        $this->assertBatchScopeOpen($batch);
        $before = $this->auditSnapshot($batch);
        $approved = DB::transaction(function () use ($batch, $actor, $note, $expectedVersion): CurriculumImportBatch {
            $locked = CurriculumImportBatch::query()->lockForUpdate()->findOrFail($batch->id);
            $this->assertVersion($locked, $expectedVersion);
            if ($locked->status !== CurriculumImportBatch::STATUS_VALIDATED) {
                throw new LibroDigitalException('Solo un lote validado puede aprobarse.', 'LCD_CURRICULUM_APPROVAL_STATE_INVALID', 409);
            }
            if ((int) $locked->requested_by === (int) $actor->id) {
                throw new LibroDigitalException('La persona solicitante no puede aprobar su propia importación.', 'LCD_CURRICULUM_SEPARATION_OF_DUTIES', 403);
            }
            $metadata = (array) $locked->metadata;
            $metadata['approval'] = [
                'approved_by' => $actor->id,
                'approved_at' => now('UTC')->toIso8601String(),
                'note' => $note,
            ];
            $locked->forceFill([
                'status' => CurriculumImportBatch::STATUS_APPROVED,
                'lock_version' => $locked->lock_version + 1,
                'metadata' => $metadata,
            ])->save();

            return $locked;
        }, 3);

        $this->audit->write(
            eventType: 'curriculum.import.approved', action: 'approve', auditable: $approved,
            actor: $actor, schoolId: $approved->school_id, academicYearId: $approved->academic_year_id,
            before: $before, after: $this->auditSnapshot($approved), reason: $note, request: $request,
            entityRevision: $approved->lock_version,
        );

        return $approved->fresh();
    }

    public function activate(
        CurriculumImportBatch $batch,
        User $actor,
        string $note,
        int $expectedVersion,
        Request $request,
    ): CurriculumImportBatch {
        $this->assertBatchScopeOpen($batch);
        $before = $this->auditSnapshot($batch);
        $activated = DB::transaction(function () use ($batch, $actor, $note, $expectedVersion, $request): CurriculumImportBatch {
            $locked = CurriculumImportBatch::query()->lockForUpdate()->findOrFail($batch->id);
            $this->assertVersion($locked, $expectedVersion);
            if ($locked->status !== CurriculumImportBatch::STATUS_APPROVED) {
                throw new LibroDigitalException('Solo un lote aprobado puede activarse.', 'LCD_CURRICULUM_ACTIVATION_STATE_INVALID', 409);
            }
            if ((int) $locked->requested_by === (int) $actor->id) {
                throw new LibroDigitalException('La persona solicitante no puede activar su propia importación.', 'LCD_CURRICULUM_SEPARATION_OF_DUTIES', 403);
            }

            $payload = $locked->validated_payload_encrypted;
            if (! is_array($payload) || ! is_array($payload['catalog'] ?? null)) {
                throw new LibroDigitalException('El lote no conserva un payload validado.', 'LCD_CURRICULUM_VALIDATED_PAYLOAD_MISSING', 409);
            }
            $coverage = (array) ($payload['coverage'] ?? []);
            if (($coverage['complete_nt1_4m'] ?? false) !== true || ($coverage['missing_grade_codes'] ?? []) !== []) {
                throw new LibroDigitalException(
                    'El catálogo no puede activarse sin cobertura completa NT1–4M.',
                    'LCD_CURRICULUM_COVERAGE_INCOMPLETE',
                    409,
                    [['missing_grade_codes' => array_values((array) ($coverage['missing_grade_codes'] ?? []))]],
                );
            }
            $expectedPayloadHash = (string) data_get($locked->manifest, 'normalized_payload_hash');
            if ($expectedPayloadHash === '' || ! hash_equals($expectedPayloadHash, $this->canonical->hash($payload))) {
                throw new LibroDigitalException('El payload curricular no supera su verificación de integridad.', 'LCD_CURRICULUM_PAYLOAD_HASH_MISMATCH', 409);
            }
            if (! hash_equals((string) $locked->source_hash, $this->archivedSourceHash($locked))) {
                throw new LibroDigitalException('El archivo curricular archivado no supera su verificación de integridad.', 'LCD_CURRICULUM_SOURCE_HASH_MISMATCH', 409);
            }

            $this->assertPayloadTraceability($payload);
            $this->storeSourceDeclarations($locked, $payload, $actor);
            $this->storeOfficialEvidenceFiles(
                $locked,
                $payload,
                $this->evidenceFiles($request),
                $actor,
            );
            $this->refreshSourceEvidenceManifest($locked, $payload);
            $verifiedEvidenceByKey = $this->assertRequiredOfficialEvidence($locked, $payload);

            $catalogPayload = (array) $payload['catalog'];
            $declaredSourceHash = (string) ($catalogPayload['declared_source_hash'] ?? '');
            $catalogPayloadHash = $this->corpus->hashPayload($payload);
            $manifestCatalogHash = (string) data_get($locked->manifest, 'catalog_payload_hash', '');
            if ($manifestCatalogHash === '' || ! hash_equals($manifestCatalogHash, $catalogPayloadHash)) {
                throw new LibroDigitalException('El corpus portable no coincide con su manifiesto.', 'LCD_CURRICULUM_CATALOG_HASH_MISMATCH', 409);
            }
            $objectivesHash = (string) data_get($locked->manifest, 'corpus_hashes.objectives', '');
            $sourcesHash = (string) data_get($locked->manifest, 'corpus_hashes.sources', '');
            $objectiveSourcesHash = (string) data_get($locked->manifest, 'corpus_hashes.objective_sources', '');
            $catalog = CurriculumCatalog::query()
                ->where('code', $locked->catalog_code)
                ->where('version', $locked->catalog_version)
                ->lockForUpdate()->first();
            if ($catalog && ! hash_equals((string) $catalog->source_hash, $catalogPayloadHash)) {
                throw new LibroDigitalException('La versión ya existe con un corpus curricular diferente; usa una versión nueva.', 'LCD_CURRICULUM_VERSION_CONFLICT', 409);
            }

            $catalogSource = $catalog?->normativeSource;
            if ($catalog && ! $catalogSource) {
                throw new LibroDigitalException('El catálogo existente no conserva su artefacto normalizado.', 'LCD_CURRICULUM_CATALOG_EVIDENCE_MISSING', 409);
            }
            if ($catalog && (string) data_get($catalogSource?->metadata, 'objectives_hash') !== $objectivesHash) {
                throw new LibroDigitalException('La versión ya existe con un conjunto de objetivos diferente; no se sobrescribió.', 'LCD_CURRICULUM_VERSION_CONFLICT', 409);
            }
            if ($catalog && filled(data_get($catalogSource?->metadata, 'sources_hash'))
                && (! hash_equals((string) data_get($catalogSource?->metadata, 'sources_hash'), $sourcesHash)
                    || ! hash_equals((string) data_get($catalogSource?->metadata, 'objective_sources_hash'), $objectiveSourcesHash))) {
                throw new LibroDigitalException('La versión ya existe con otra trazabilidad normativa; no se sobrescribió.', 'LCD_CURRICULUM_VERSION_CONFLICT', 409);
            }
            $batchSource = NormativeSource::query()->create([
                'title' => 'Workbook curricular normalizado '.$locked->catalog_code.' '.$locked->catalog_version,
                'authority' => $catalogPayload['authority'] ?? null,
                'document_number' => $locked->catalog_code.'-'.$locked->catalog_version,
                'published_on' => $catalogPayload['effective_from'] ?? null,
                'source_url' => null,
                'consulted_at' => now('UTC'),
                'sha256' => $locked->source_hash,
                'private_path' => $locked->private_path,
                'status' => 'verified_metadata_only',
                'metadata' => [
                    'curriculum_import_batch' => $locked->public_id,
                    'evidence_kind' => 'normalized_curriculum_workbook',
                    'hash_scope' => 'normalized_xlsx_bytes_verified',
                    'catalog_payload_hash' => $catalogPayloadHash,
                    'objectives_hash' => $objectivesHash,
                    'sources_hash' => $sourcesHash,
                    'objective_sources_hash' => $objectiveSourcesHash,
                    'official_source_url' => $catalogPayload['source_url'] ?? null,
                    'declared_official_source_sha256' => $declaredSourceHash ?: null,
                    'declared_official_source_hash_scope' => $declaredSourceHash !== ''
                        ? 'legacy_declared_manifest_not_evidence'
                        : null,
                ],
                'created_by' => $actor->id,
            ]);
            if (! $catalog) {
                $catalogSource = $batchSource;
                $catalog = CurriculumCatalog::query()->create([
                    'normative_source_id' => $catalogSource->id,
                    'code' => $locked->catalog_code,
                    'name' => $catalogPayload['name'],
                    'version' => $locked->catalog_version,
                    'authority' => $catalogPayload['authority'] ?? null,
                    'source_url' => $catalogPayload['source_url'] ?? null,
                    'source_hash' => $catalogPayloadHash,
                    'effective_from' => $catalogPayload['effective_from'] ?? null,
                    'effective_to' => $catalogPayload['effective_to'] ?? null,
                    'active' => true,
                ]);
            }

            $objectivesByKey = $catalog->learningObjectives()->get()->keyBy('objective_key');
            foreach ((array) $payload['objectives'] as $objective) {
                $rowHash = $this->canonical->hash($this->corpus->objective($objective));
                /** @var LearningObjective|null $objectiveModel */
                $objectiveModel = $objectivesByKey->get($objective['objective_key']);
                if ($objectiveModel) {
                    if (! hash_equals((string) $objectiveModel->source_row_hash, $rowHash)) {
                        throw new LibroDigitalException('La versión contiene un objetivo distinto con la misma identidad.', 'LCD_CURRICULUM_VERSION_CONFLICT', 409);
                    }

                    continue;
                }
                $objectiveModel = LearningObjective::query()->create([
                    'curriculum_catalog_id' => $catalog->id,
                    'schedule_subject_id' => $objective['schedule_subject_id'] ?: null,
                    'level_code' => $objective['level_code'],
                    'grade_code' => $objective['grade_code'],
                    'curriculum_track' => $objective['curriculum_track'] ?? null,
                    'axis_code' => $objective['axis_code'] ?: null,
                    'unit_code' => $objective['unit_code'] ?: null,
                    'objective_type' => $objective['objective_type'],
                    'code' => $objective['code'],
                    'objective_key' => $objective['objective_key'],
                    'description' => $objective['description'],
                    'indicators' => $objective['indicators'],
                    'active' => $objective['active'],
                    'source_page' => $objective['source_page'] ?: null,
                    'source_row_hash' => $rowHash,
                ]);
                $objectivesByKey->put($objective['objective_key'], $objectiveModel);
            }

            $sourcesByKey = collect();
            foreach ((array) $payload['sources'] as $sourcePayload) {
                $sourceKey = (string) ($sourcePayload['source_key'] ?? '');
                /** @var CurriculumImportEvidence|null $officialEvidence */
                $officialEvidence = $verifiedEvidenceByKey->get($sourceKey);
                if (! $officialEvidence) {
                    // Una declaración complementaria no vinculada puede quedar
                    // solo como metadata; nunca se presenta como fuente verificada.
                    continue;
                }
                $curriculumSource = CurriculumSource::query()
                    ->where('curriculum_catalog_id', $catalog->id)
                    ->where('source_key', $sourceKey)
                    ->first();
                if ($curriculumSource) {
                    if (! hash_equals((string) $curriculumSource->declared_sha256, (string) $sourcePayload['source_sha256'])
                        || ! hash_equals((string) $curriculumSource->verified_sha256, (string) $officialEvidence->sha256)) {
                        throw new LibroDigitalException('La fuente normativa ya existe con otra evidencia.', 'LCD_CURRICULUM_SOURCE_CONFLICT', 409);
                    }
                    $sourcesByKey->put($sourceKey, $curriculumSource);

                    continue;
                }
                $officialNormativeSource = NormativeSource::query()->create([
                    'title' => $sourcePayload['source_name'],
                    'authority' => $sourcePayload['authority'],
                    'document_number' => $sourcePayload['document_number'],
                    'published_on' => $sourcePayload['effective_from'] ?: null,
                    'source_url' => $sourcePayload['source_url'],
                    'consulted_at' => now('UTC'),
                    'sha256' => $officialEvidence->sha256,
                    'private_path' => $officialEvidence->private_path,
                    'status' => 'verified',
                    'metadata' => [
                        'curriculum_import_batch' => $locked->public_id,
                        'evidence_public_id' => $officialEvidence->public_id,
                        'evidence_kind' => 'official_source_document',
                        'hash_scope' => 'official_source_bytes_verified',
                        'source_key' => $sourceKey,
                        'source_scope' => $sourcePayload['source_scope'],
                    ],
                    'created_by' => $actor->id,
                ]);
                $curriculumSource = CurriculumSource::query()->create([
                    'curriculum_catalog_id' => $catalog->id,
                    'normative_source_id' => $officialNormativeSource->id,
                    'source_key' => $sourceKey,
                    'source_scope' => $sourcePayload['source_scope'],
                    'source_name' => $sourcePayload['source_name'],
                    'authority' => $sourcePayload['authority'],
                    'document_number' => $sourcePayload['document_number'],
                    'source_url' => $sourcePayload['source_url'],
                    'declared_sha256' => $sourcePayload['source_sha256'],
                    'verified_sha256' => $officialEvidence->sha256,
                    'effective_from' => $sourcePayload['effective_from'] ?: null,
                    'effective_to' => $sourcePayload['effective_to'] ?: null,
                    'curriculum_track' => $sourcePayload['curriculum_track'] ?: null,
                    'schedule_subject_id' => $sourcePayload['schedule_subject_id'] ?: null,
                    'objective_type' => $sourcePayload['objective_type'] ?: null,
                    'status' => 'verified',
                    'metadata' => [
                        'import_batch_public_id' => $locked->public_id,
                        'evidence_public_id' => $officialEvidence->public_id,
                        'hash_scope' => 'official_source_bytes_verified',
                    ],
                ]);
                $sourcesByKey->put($sourceKey, $curriculumSource);
            }

            $relationshipHashes = [];
            foreach ((array) $payload['objective_sources'] as $relationship) {
                /** @var LearningObjective|null $objectiveModel */
                $objectiveModel = $objectivesByKey->get($relationship['objective_key']);
                /** @var CurriculumSource|null $curriculumSource */
                $curriculumSource = $sourcesByKey->get($relationship['source_key']);
                if (! $objectiveModel || ! $curriculumSource) {
                    throw new LibroDigitalException('No fue posible resolver la trazabilidad objetivo-fuente validada.', 'LCD_CURRICULUM_SOURCE_RELATION_INVALID', 409);
                }
                $snapshot = [
                    'source_key' => $curriculumSource->source_key,
                    'source_scope' => $curriculumSource->source_scope,
                    'source_name' => $curriculumSource->source_name,
                    'authority' => $curriculumSource->authority,
                    'document_number' => $curriculumSource->document_number,
                    'source_url' => $curriculumSource->source_url,
                    'sha256' => $curriculumSource->verified_sha256,
                ];
                $relationshipHash = $this->canonical->hash([
                    'objective_key' => $relationship['objective_key'],
                    'source_key' => $relationship['source_key'],
                    'source_role' => $relationship['source_role'],
                    'source_locator' => $relationship['source_locator'],
                    'source_sha256' => $curriculumSource->verified_sha256,
                ]);
                $attributes = [
                    'learning_objective_id' => $objectiveModel->id,
                    'curriculum_source_id' => $curriculumSource->id,
                    'source_role' => $relationship['source_role'],
                    'source_locator' => $relationship['source_locator'],
                ];
                $existingRelationship = LearningObjectiveSource::query()->where($attributes)->first();
                if ($existingRelationship && ! hash_equals((string) $existingRelationship->relationship_hash, $relationshipHash)) {
                    throw new LibroDigitalException('La relación objetivo-fuente existente no coincide con el manifiesto.', 'LCD_CURRICULUM_SOURCE_RELATION_CONFLICT', 409);
                }
                if (! $existingRelationship) {
                    LearningObjectiveSource::query()->create([
                        ...$attributes,
                        'relationship_hash' => $relationshipHash,
                        'source_snapshot' => $snapshot,
                    ]);
                }
                $relationshipHashes[] = $relationshipHash;
            }
            foreach ($objectivesByKey as $objectiveKey => $objectiveModel) {
                $canonicalCount = LearningObjectiveSource::query()
                    ->where('learning_objective_id', $objectiveModel->id)
                    ->where('source_role', 'canonical_text')
                    ->count();
                if ($canonicalCount !== 1) {
                    throw new LibroDigitalException(
                        'La materialización no conserva exactamente una fuente canonical_text por objetivo.',
                        'LCD_CURRICULUM_CANONICAL_SOURCE_INVALID',
                        409,
                        [['objective_key' => $objectiveKey, 'canonical_count' => $canonicalCount]],
                    );
                }
            }

            $importedLinks = 0;
            foreach ((array) $payload['links'] as $link) {
                $attributes = [
                    'school_id' => $locked->school_id,
                    'academic_year_id' => $locked->academic_year_id,
                    'schedule_subject_id' => $link['schedule_subject_id'],
                    'curriculum_catalog_id' => $catalog->id,
                    'scope_key' => $link['scope_key'],
                ];
                $existingLink = SubjectCurriculumLink::query()->where($attributes)->first();
                $values = [
                    'level_code' => $link['level_code'],
                    'grade_code' => $link['grade_code'],
                    'curriculum_track' => $link['curriculum_track'] ?? null,
                    'valid_from' => $link['valid_from'],
                    'valid_to' => $link['valid_to'],
                    'active' => $link['active'],
                ];
                if ($existingLink) {
                    $same = collect($values)->every(fn (mixed $value, string $key): bool => $this->comparable($existingLink->getAttribute($key)) === $this->comparable($value)
                    );
                    if (! $same) {
                        throw new LibroDigitalException('Ya existe un vínculo curricular distinto para el mismo alcance.', 'LCD_CURRICULUM_LINK_CONFLICT', 409);
                    }

                    continue;
                }
                SubjectCurriculumLink::query()->create([...$attributes, ...$values]);
                $importedLinks++;
            }

            $previous = CurriculumCatalogActivation::query()
                ->where('school_id', $locked->school_id)
                ->where('academic_year_id', $locked->academic_year_id)
                ->where('status', CurriculumCatalogActivation::STATUS_ACTIVATED)
                ->lockForUpdate()->latest('activation_version')->first();
            if ($previous) {
                $previous->forceFill(['status' => CurriculumCatalogActivation::STATUS_SUPERSEDED])->save();
            }
            $activationVersion = ((int) CurriculumCatalogActivation::query()
                ->where('school_id', $locked->school_id)
                ->where('academic_year_id', $locked->academic_year_id)
                ->max('activation_version')) + 1;
            $approval = (array) data_get($locked->metadata, 'approval', []);
            $decisionManifest = [
                'batch_public_id' => $locked->public_id,
                'catalog_public_id' => $catalog->public_id,
                'workbook_source_hash' => $locked->source_hash,
                'catalog_payload_hash' => $catalogPayloadHash,
                'declared_official_source_hash' => $declaredSourceHash,
                'manifest_hash' => $locked->manifest_hash,
                'official_sources' => $sourcesByKey->map(fn (CurriculumSource $item): array => [
                    'source_key' => $item->source_key,
                    'source_scope' => $item->source_scope,
                    'declared_sha256' => $item->declared_sha256,
                    'verified_sha256' => $item->verified_sha256,
                ])->sortBy('source_key')->values()->all(),
                'objective_sources_hash' => $this->canonical->hash($relationshipHashes),
                'source_evidence' => data_get($locked->manifest, 'source_evidence'),
                'coverage' => $coverage,
                'note' => $note,
            ];
            CurriculumCatalogActivation::query()->create([
                'school_id' => $locked->school_id,
                'academic_year_id' => $locked->academic_year_id,
                'curriculum_catalog_id' => $catalog->id,
                'import_batch_id' => $locked->id,
                'supersedes_activation_id' => $previous?->id,
                'activation_version' => $activationVersion,
                'idempotency_key' => $this->idempotencyHash($request, $locked->source_hash.':activate'),
                'status' => CurriculumCatalogActivation::STATUS_ACTIVATED,
                'effective_from' => $catalogPayload['effective_from'] ?? now('UTC')->toDateString(),
                'effective_to' => $catalogPayload['effective_to'] ?? null,
                'scope_snapshot' => $coverage,
                'decision_manifest' => $decisionManifest,
                'decision_hash' => $this->canonical->hash($decisionManifest),
                'requested_by' => $locked->requested_by,
                'requested_at' => $locked->requested_at,
                'approved_by' => $approval['approved_by'] ?? null,
                'approved_at' => $approval['approved_at'] ?? null,
                'activated_by' => $actor->id,
                'activated_at' => now('UTC'),
                'decision_notes' => $note,
            ]);

            $locked->evidences()->update(['curriculum_catalog_id' => $catalog->id]);
            $locked->evidences()->where('status', CurriculumImportEvidence::STATUS_VERIFIED)->update([
                'verified_by' => $actor->id,
                'verified_at' => now('UTC'),
            ]);
            $locked->forceFill([
                'curriculum_catalog_id' => $catalog->id,
                'normative_source_id' => $batchSource->id,
                'status' => CurriculumImportBatch::STATUS_ACTIVATED,
                'lock_version' => $locked->lock_version + 1,
                'imported_row_count' => $catalog->learningObjectives()->count()
                    + $sourcesByKey->count()
                    + count($relationshipHashes)
                    + $importedLinks,
                'completed_at' => now('UTC'),
            ])->save();

            return $locked;
        }, 3);

        $this->audit->write(
            eventType: 'curriculum.import.activated', action: 'activate', auditable: $activated,
            actor: $actor, schoolId: $activated->school_id, academicYearId: $activated->academic_year_id,
            before: $before, after: $this->auditSnapshot($activated), reason: $note, request: $request,
            entityRevision: $activated->lock_version,
        );
        $this->audit->write(
            eventType: 'compliance.blocker.curriculum.resolved',
            action: 'resolve',
            auditable: $activated,
            actor: $actor,
            schoolId: $activated->school_id,
            academicYearId: $activated->academic_year_id,
            after: [
                'blocker_code' => 'CURRICULUM_OA_NOT_IMPORTED',
                'catalog_code' => $activated->catalog_code,
                'catalog_version' => $activated->catalog_version,
                'source_hash' => $activated->source_hash,
                'coverage' => data_get($activated->manifest, 'coverage'),
            ],
            reason: 'Cobertura NT1–4M activada con XLSX normalizado, fuentes oficiales archivadas y separación de funciones.',
            request: $request,
            entityRevision: $activated->lock_version,
        );

        return $activated->fresh(['curriculumCatalog']);
    }

    /** @param array<string, UploadedFile> $files */
    private function attachToExistingBatch(
        CurriculumImportBatch $batch,
        array $files,
        ?User $actor,
        Request $request,
    ): CurriculumImportBatch {
        if ($files === []) {
            return $batch;
        }

        $before = $this->auditSnapshot($batch);
        [$updated, $created] = DB::transaction(function () use ($batch, $files, $actor): array {
            $locked = CurriculumImportBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if ($locked->status !== CurriculumImportBatch::STATUS_VALIDATED) {
                throw new LibroDigitalException(
                    'La evidencia adicional se carga al validar un lote validado o junto con su activación aprobada.',
                    'LCD_CURRICULUM_EVIDENCE_STATE_INVALID',
                    409,
                );
            }
            $payload = $locked->validated_payload_encrypted;
            if (! is_array($payload)) {
                throw new LibroDigitalException('El lote no conserva un payload validado.', 'LCD_CURRICULUM_VALIDATED_PAYLOAD_MISSING', 409);
            }
            $this->assertPayloadTraceability($payload);
            $this->storeSourceDeclarations($locked, $payload, $actor);
            $created = $this->storeOfficialEvidenceFiles($locked, $payload, $files, $actor);
            $this->refreshSourceEvidenceManifest($locked, $payload);

            return [$locked, $created];
        }, 3);

        if ($created > 0) {
            $this->audit->write(
                eventType: 'curriculum.source_evidence.verified',
                action: 'attach',
                auditable: $updated,
                actor: $actor,
                schoolId: $updated->school_id,
                academicYearId: $updated->academic_year_id,
                before: $before,
                after: $this->auditSnapshot($updated),
                reason: "Se archivaron y verificaron {$created} fuentes oficiales adicionales.",
                request: $request,
                entityRevision: $updated->lock_version,
            );
        }

        return $updated->fresh();
    }

    /** @param array<string, mixed> $payload */
    private function assertPayloadTraceability(array $payload): void
    {
        $objectives = collect((array) ($payload['objectives'] ?? []))->keyBy('objective_key');
        $sources = collect((array) ($payload['sources'] ?? []))->keyBy('source_key');
        $relationships = collect((array) ($payload['objective_sources'] ?? []));
        if ($objectives->isEmpty() || $sources->isEmpty() || $relationships->isEmpty()) {
            throw new LibroDigitalException(
                'El payload no contiene la trazabilidad curricular multifuente validada.',
                'LCD_CURRICULUM_SOURCE_TRACEABILITY_MISSING',
                409,
            );
        }

        foreach ($relationships as $relationship) {
            $objectiveKey = (string) ($relationship['objective_key'] ?? '');
            $sourceKey = (string) ($relationship['source_key'] ?? '');
            if (! $objectives->has($objectiveKey) || ! $sources->has($sourceKey)
                || ! in_array((string) ($relationship['source_role'] ?? ''), CurriculumImportValidator::SOURCE_ROLES, true)
                || ! filled($relationship['source_locator'] ?? null)) {
                throw new LibroDigitalException(
                    'Una relación objetivo-fuente no coincide con el manifiesto validado.',
                    'LCD_CURRICULUM_SOURCE_RELATION_INVALID',
                    409,
                );
            }
        }
        $canonicalCounts = $relationships->where('source_role', 'canonical_text')->countBy('objective_key');
        foreach ($objectives->keys() as $objectiveKey) {
            if ((int) ($canonicalCounts[$objectiveKey] ?? 0) !== 1) {
                throw new LibroDigitalException(
                    'Cada objetivo debe conservar exactamente una fuente canonical_text.',
                    'LCD_CURRICULUM_CANONICAL_SOURCE_INVALID',
                    409,
                    [['objective_key' => $objectiveKey, 'canonical_count' => (int) ($canonicalCounts[$objectiveKey] ?? 0)]],
                );
            }
        }
    }

    /** @return array<string, UploadedFile> */
    private function evidenceFiles(Request $request): array
    {
        $files = $request->file('evidence_files', []);
        if ($files === null || $files === []) {
            return [];
        }
        if (! is_array($files)) {
            throw new LibroDigitalException('evidence_files debe ser un mapa por source_key.', 'LCD_CURRICULUM_EVIDENCE_SHAPE_INVALID', 422);
        }

        $normalized = [];
        foreach ($files as $sourceKey => $file) {
            $key = mb_strtoupper(trim((string) $sourceKey));
            if (! preg_match('/^[A-Z0-9._-]{1,100}$/', $key) || ! $file instanceof UploadedFile) {
                throw new LibroDigitalException(
                    'Cada evidencia debe asociarse mediante un source_key válido.',
                    'LCD_CURRICULUM_EVIDENCE_SHAPE_INVALID',
                    422,
                    [['source_key' => (string) $sourceKey]],
                );
            }
            if (isset($normalized[$key])) {
                throw new LibroDigitalException('Se repitió un source_key en evidence_files.', 'LCD_CURRICULUM_EVIDENCE_DUPLICATE', 422);
            }
            $normalized[$key] = $file;
        }

        return $normalized;
    }

    /** @param array<string, mixed> $payload */
    private function storeSourceDeclarations(CurriculumImportBatch $batch, array $payload, ?User $actor): void
    {
        foreach ((array) ($payload['sources'] ?? []) as $source) {
            $hash = (string) ($source['source_sha256'] ?? '');
            $key = (string) ($source['source_key'] ?? '');
            if ($hash === '' || $key === '') {
                continue;
            }
            CurriculumImportEvidence::query()->firstOrCreate(
                [
                    'import_batch_id' => $batch->id,
                    'evidence_kind' => 'official_source_declaration',
                    'sha256' => $hash,
                ],
                [
                    'school_id' => $batch->school_id,
                    'academic_year_id' => $batch->academic_year_id,
                    'status' => CurriculumImportEvidence::STATUS_PENDING_VERIFICATION,
                    'title' => 'Declaración normativa: '.($source['source_name'] ?? $key),
                    'description' => 'Metadatos declarados en Fuentes; no acreditan los bytes hasta adjuntar y verificar el archivo.',
                    'source_url' => $source['source_url'] ?? null,
                    'disk' => (string) config('libro_digital.storage.disk', 'local'),
                    'captured_by' => $actor?->id,
                    'captured_at' => now('UTC'),
                    'verification_notes' => 'Hash declarado; documento aún no archivado ni verificado.',
                    'metadata' => [
                        'source_key' => $key,
                        'source_scope' => $source['source_scope'] ?? null,
                        'hash_scope' => 'declared_official_source_not_locally_verified',
                        'declaration' => $source,
                    ],
                ],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, UploadedFile>  $files
     */
    private function storeOfficialEvidenceFiles(
        CurriculumImportBatch $batch,
        array $payload,
        array $files,
        ?User $actor,
    ): int {
        if ($files === []) {
            return 0;
        }

        $sources = collect((array) ($payload['sources'] ?? []))->keyBy('source_key');
        $created = 0;
        foreach ($files as $sourceKey => $file) {
            $source = $sources->get($sourceKey);
            if (! is_array($source)) {
                throw new LibroDigitalException(
                    "El archivo de evidencia {$sourceKey} no corresponde a una fila de Fuentes.",
                    'LCD_CURRICULUM_EVIDENCE_SOURCE_UNKNOWN',
                    422,
                    [['source_key' => $sourceKey]],
                );
            }
            if (! $file->isValid() || ! is_string($file->getRealPath())) {
                throw new LibroDigitalException("No se pudo leer la evidencia {$sourceKey}.", 'LCD_CURRICULUM_EVIDENCE_UPLOAD_INVALID', 422);
            }
            $size = (int) ($file->getSize() ?: 0);
            if ($size < 1 || $size > self::MAX_EVIDENCE_BYTES) {
                throw new LibroDigitalException(
                    "La evidencia {$sourceKey} debe pesar entre 1 byte y 20 MiB.",
                    'LCD_CURRICULUM_EVIDENCE_SIZE_INVALID',
                    422,
                    [['source_key' => $sourceKey, 'size_bytes' => $size]],
                );
            }
            $extension = mb_strtolower($file->getClientOriginalExtension());
            $contents = file_get_contents((string) $file->getRealPath());
            if (! is_string($contents) || ! $this->isAllowedEvidence($file, $extension, $contents)) {
                throw new LibroDigitalException(
                    "La evidencia {$sourceKey} debe ser PDF, HTML/XHTML o XLSX válido.",
                    'LCD_CURRICULUM_EVIDENCE_TYPE_INVALID',
                    422,
                    [['source_key' => $sourceKey, 'mime_type' => $file->getMimeType(), 'extension' => $extension]],
                );
            }
            $actualHash = hash('sha256', $contents);
            $declaredHash = mb_strtolower((string) ($source['source_sha256'] ?? ''));
            if (! hash_equals($declaredHash, $actualHash)) {
                throw new LibroDigitalException(
                    "El SHA-256 real de {$sourceKey} no coincide con Fuentes.source_sha256.",
                    'LCD_CURRICULUM_EVIDENCE_HASH_MISMATCH',
                    422,
                    [['source_key' => $sourceKey, 'declared_sha256' => $declaredHash, 'actual_sha256' => $actualHash]],
                );
            }

            $privatePath = $this->archiveOfficialEvidence($batch, $sourceKey, $actualHash, $extension, $contents);
            $evidence = CurriculumImportEvidence::query()->firstOrCreate(
                [
                    'import_batch_id' => $batch->id,
                    'evidence_kind' => 'official_source_document',
                    'sha256' => $actualHash,
                ],
                [
                    'school_id' => $batch->school_id,
                    'academic_year_id' => $batch->academic_year_id,
                    'status' => CurriculumImportEvidence::STATUS_VERIFIED,
                    'title' => (string) ($source['source_name'] ?? $sourceKey),
                    'description' => 'Documento oficial archivado en privado y verificado contra Fuentes.source_sha256.',
                    'source_url' => $source['source_url'] ?? null,
                    'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                    'detected_mime_type' => $file->getMimeType(),
                    'extension' => $extension,
                    'size_bytes' => $size,
                    'disk' => (string) config('libro_digital.storage.disk', 'local'),
                    'private_path' => $privatePath,
                    'storage_metadata' => ['encrypted' => true, 'cipher' => config('app.cipher')],
                    'manifest' => ['source' => $source, 'actual_sha256' => $actualHash],
                    'captured_by' => $actor?->id,
                    'captured_at' => now('UTC'),
                    'verified_by' => $actor?->id,
                    'verified_at' => now('UTC'),
                    'verification_notes' => 'Bytes archivados y SHA-256 verificado contra la declaración del XLSX.',
                    'metadata' => [
                        'source_key' => $sourceKey,
                        'source_scope' => $source['source_scope'] ?? null,
                        'hash_scope' => 'official_source_bytes_verified',
                    ],
                ],
            );
            if ($evidence->wasRecentlyCreated) {
                $created++;
            } elseif ((string) data_get($evidence->metadata, 'source_key') !== $sourceKey
                || ! hash_equals((string) $evidence->sha256, $actualHash)
                || ! hash_equals($actualHash, $this->archivedEvidenceHash($evidence))) {
                throw new LibroDigitalException('La evidencia archivada no coincide con la fuente declarada.', 'LCD_CURRICULUM_EVIDENCE_CONFLICT', 409);
            }
        }

        return $created;
    }

    /** @param array<string, mixed> $payload @return array<string, list<string>> */
    private function refreshSourceEvidenceManifest(CurriculumImportBatch $batch, array $payload): array
    {
        $sources = collect((array) ($payload['sources'] ?? []))->keyBy('source_key');
        $required = collect((array) ($payload['objective_sources'] ?? []))
            ->pluck('source_key')->filter()->unique()->sort()->values();
        $verified = $batch->evidences()->where('evidence_kind', 'official_source_document')
            ->where('status', CurriculumImportEvidence::STATUS_VERIFIED)->get()
            ->filter(function (CurriculumImportEvidence $evidence) use ($sources): bool {
                $key = (string) data_get($evidence->metadata, 'source_key');
                $source = $sources->get($key);

                return is_array($source)
                    && hash_equals((string) ($source['source_sha256'] ?? ''), (string) $evidence->sha256);
            })
            ->map(fn (CurriculumImportEvidence $evidence): string => (string) data_get($evidence->metadata, 'source_key'))
            ->unique()->sort()->values();
        $missing = $required->diff($verified)->values();
        $state = [
            'required_source_keys' => $required->all(),
            'verified_source_keys' => $verified->all(),
            'missing_source_keys' => $missing->all(),
        ];

        $manifest = (array) $batch->manifest;
        $manifest['source_evidence'] = $state;
        data_set($manifest, 'counts.sources', $sources->count());
        data_set($manifest, 'counts.objective_sources', count((array) ($payload['objective_sources'] ?? [])));
        $validation = (array) $batch->validation_errors;
        $warnings = collect((array) ($validation['warnings'] ?? []))
            ->reject(fn (array $warning): bool => ($warning['code'] ?? null) === 'official_source_evidence_missing')
            ->values();
        if ($missing->isNotEmpty()) {
            $warnings->push([
                'code' => 'official_source_evidence_missing',
                'message' => 'La estructura es válida, pero la activación exige archivar todas las fuentes vinculadas.',
                'source_keys' => $missing->all(),
            ]);
        }
        $validation['warnings'] = $warnings->all();
        data_set($manifest, 'counts.warnings', $warnings->count());
        $batch->forceFill([
            'manifest' => $manifest,
            'manifest_hash' => $this->canonical->hash($manifest),
            'validation_errors' => $validation,
            'warning_count' => $warnings->count(),
        ])->save();

        return $state;
    }

    /** @param array<string, mixed> $payload */
    private function assertRequiredOfficialEvidence(CurriculumImportBatch $batch, array $payload): Collection
    {
        $state = $this->refreshSourceEvidenceManifest($batch, $payload);
        if ($state['missing_source_keys'] !== []) {
            throw new LibroDigitalException(
                'No se puede activar: faltan documentos oficiales vinculados a objetivos.',
                'LCD_CURRICULUM_SOURCE_EVIDENCE_REQUIRED',
                409,
                [['missing_source_keys' => $state['missing_source_keys']]],
            );
        }

        $sources = collect((array) ($payload['sources'] ?? []))->keyBy('source_key');
        $evidences = $batch->evidences()->where('evidence_kind', 'official_source_document')
            ->where('status', CurriculumImportEvidence::STATUS_VERIFIED)->get()
            ->keyBy(fn (CurriculumImportEvidence $evidence): string => (string) data_get($evidence->metadata, 'source_key'));
        foreach ($state['required_source_keys'] as $sourceKey) {
            /** @var CurriculumImportEvidence|null $evidence */
            $evidence = $evidences->get($sourceKey);
            $source = $sources->get($sourceKey);
            if (! $evidence || ! is_array($source)
                || ! hash_equals((string) $source['source_sha256'], (string) $evidence->sha256)
                || ! hash_equals((string) $evidence->sha256, $this->archivedEvidenceHash($evidence))) {
                throw new LibroDigitalException(
                    "La evidencia oficial {$sourceKey} no supera la verificación de integridad.",
                    'LCD_CURRICULUM_SOURCE_EVIDENCE_INTEGRITY_FAILED',
                    409,
                    [['source_key' => $sourceKey]],
                );
            }
        }

        return $evidences;
    }

    private function isAllowedEvidence(UploadedFile $file, string $extension, string $contents): bool
    {
        $mime = (string) $file->getMimeType();
        if ($extension === 'pdf') {
            return $mime === 'application/pdf' && str_starts_with($contents, '%PDF-');
        }
        if (in_array($extension, ['html', 'htm', 'xhtml'], true)) {
            return in_array($mime, ['text/html', 'application/xhtml+xml', 'application/xml', 'text/xml'], true)
                && preg_match('/<\s*(?:!doctype\s+html|html|head|body)\b/i', substr($contents, 0, 8192)) === 1;
        }
        if ($extension !== 'xlsx'
            || ! in_array($mime, [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/zip',
                'application/octet-stream',
            ], true)) {
            return false;
        }
        $zip = new ZipArchive;
        if ($zip->open((string) $file->getRealPath(), ZipArchive::RDONLY) !== true) {
            return false;
        }
        try {
            return $zip->locateName('[Content_Types].xml') !== false
                && $zip->locateName('xl/workbook.xml') !== false
                && $zip->locateName('xl/vbaProject.bin', ZipArchive::FL_NOCASE) === false;
        } finally {
            $zip->close();
        }
    }

    private function archiveOfficialEvidence(
        CurriculumImportBatch $batch,
        string $sourceKey,
        string $hash,
        string $extension,
        string $contents,
    ): string {
        $root = trim((string) config('libro_digital.storage.root', 'private/libro-digital'), '/');
        $path = $root.'/curriculum/'.$batch->school_id.'/'.$batch->academic_year_id
            .'/sources/'.$sourceKey.'/'.$hash.'.'.$extension.'.enc';
        $disk = Storage::disk((string) config('libro_digital.storage.disk', 'local'));
        if (! $disk->exists($path) && ! $disk->put($path, Crypt::encryptString($contents))) {
            throw new LibroDigitalException('No fue posible archivar una fuente oficial.', 'LCD_CURRICULUM_EVIDENCE_ARCHIVE_FAILED', 500);
        }

        return $path;
    }

    private function archivedEvidenceHash(CurriculumImportEvidence $evidence): string
    {
        try {
            if (! filled($evidence->private_path)) {
                return '';
            }
            $encrypted = Storage::disk($evidence->disk)->get($evidence->private_path);
            $contents = Crypt::decryptString($encrypted);

            return hash('sha256', $contents);
        } catch (\Throwable) {
            return '';
        }
    }

    private function assertSchoolYear(School $school, AcademicYear $year): void
    {
        if (! $school->academicYears()->where('academic_years.id', $year->id)->wherePivot('active', true)->exists()) {
            throw new LibroDigitalException('El año académico no está habilitado para el establecimiento.', 'LCD_ACADEMIC_YEAR_SCOPE_INVALID', 422);
        }
    }

    private function assertBatchScopeOpen(CurriculumImportBatch $batch): void
    {
        $school = School::query()->whereKey($batch->school_id)->where('active', true)->first();
        $year = AcademicYear::query()->whereKey($batch->academic_year_id)->first();
        if (! $school || ! $year) {
            throw new LibroDigitalException('El lote perdió su contexto académico.', 'LCD_CURRICULUM_SCOPE_INVALID', 409);
        }
        $this->assertSchoolYear($school, $year);
        if ($year->is_closed) {
            throw new LibroDigitalException('No se puede decidir una importación en un año académico cerrado.', 'LCD_ACADEMIC_YEAR_CLOSED', 409);
        }
    }

    private function assertVersionAvailable(
        string $code,
        string $version,
        string $catalogPayloadHash,
        School $school,
        AcademicYear $year,
    ): void {
        $catalog = CurriculumCatalog::query()->where('code', $code)->where('version', $version)->first();
        if ($catalog && ($catalogPayloadHash === '' || ! hash_equals((string) $catalog->source_hash, $catalogPayloadHash))) {
            throw new LibroDigitalException('La versión ya existe con otro corpus curricular; no se sobrescribió ningún registro.', 'LCD_CURRICULUM_VERSION_CONFLICT', 409);
        }
        $batch = CurriculumImportBatch::query()
            ->where('school_id', $school->id)->where('academic_year_id', $year->id)
            ->where('catalog_code', $code)->where('catalog_version', $version)->first();
        if ($batch && ! hash_equals((string) data_get($batch->manifest, 'catalog_payload_hash', ''), $catalogPayloadHash)) {
            throw new LibroDigitalException('La versión ya fue presentada con otro corpus curricular; usa una versión nueva.', 'LCD_CURRICULUM_VERSION_CONFLICT', 409);
        }
    }

    private function assertVersion(CurriculumImportBatch $batch, int $expected): void
    {
        if ((int) $batch->lock_version !== $expected) {
            throw new VersionConflictException($expected, (int) $batch->lock_version);
        }
    }

    private function idempotencyHash(Request $request, string $fallback): string
    {
        $key = trim((string) $request->header('Idempotency-Key')) ?: $fallback;

        return hash('sha256', $key);
    }

    private function archive(UploadedFile $file, School $school, AcademicYear $year, string $hash): string
    {
        $contents = file_get_contents((string) $file->getRealPath());
        if (! is_string($contents)) {
            throw new LibroDigitalException('No se pudo leer el archivo para archivarlo.', 'LCD_CURRICULUM_ARCHIVE_FAILED', 500);
        }
        $root = trim((string) config('libro_digital.storage.root', 'private/libro-digital'), '/');
        $path = $root.'/curriculum/'.$school->public_id.'/'.$year->year.'/'.$hash.'.xlsx.enc';
        $disk = Storage::disk((string) config('libro_digital.storage.disk', 'local'));
        if (! $disk->exists($path) && ! $disk->put($path, Crypt::encryptString($contents))) {
            throw new LibroDigitalException('No fue posible archivar el XLSX en almacenamiento privado.', 'LCD_CURRICULUM_ARCHIVE_FAILED', 500);
        }

        return $path;
    }

    private function archivedSourceHash(CurriculumImportBatch $batch): string
    {
        $disk = Storage::disk($batch->disk);
        $encrypted = $disk->get($batch->private_path);
        try {
            $contents = Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            throw new LibroDigitalException('No se pudo descifrar el artefacto curricular.', 'LCD_CURRICULUM_ARCHIVE_INVALID', 409);
        }

        return hash('sha256', $contents);
    }

    /** @return array<string, mixed> */
    private function auditSnapshot(CurriculumImportBatch $batch): array
    {
        return $batch->only([
            'public_id', 'school_id', 'academic_year_id', 'curriculum_catalog_id', 'status',
            'lock_version', 'catalog_code', 'catalog_version', 'source_hash', 'manifest_hash',
            'objective_count', 'subject_link_count', 'error_count', 'warning_count',
        ]);
    }

    private function comparable(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return $value === null ? '' : (string) $value;
    }
}
