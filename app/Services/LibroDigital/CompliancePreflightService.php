<?php

namespace App\Services\LibroDigital;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class CompliancePreflightService
{
    public function __construct(private readonly FeatureFlagService $features) {}

    /** @return array<string, mixed> */
    public function run(?int $schoolId = null): array
    {
        $flags = $this->features->all($schoolId);
        $identityRequired = (bool) ($flags['lcd_identity_verifier_enabled'] ?? false);
        $edeRequired = (bool) ($flags['lcd_ede_export_enabled'] ?? false);
        $parvulariaRequired = (bool) ($flags['lcd_parvularia_enabled'] ?? false);
        $sigeRequired = (bool) ($flags['lcd_sige_reconciliation_enabled'] ?? false);
        $fiscalizationRequired = (bool) ($flags['lcd_fiscalization_download_enabled'] ?? false);

        $coreChecks = [
            $this->check('database_schema', 'Esquema de base de datos', Schema::hasTable('lcd_books'), 'Ejecuta las migraciones LCD aditivas.'),
            $this->check('private_storage', 'Almacenamiento privado', $this->storageAvailable(), 'Configura un disco privado escribible.'),
            $this->check('school_context', 'Establecimiento y RBD', $this->schoolConfigured($schoolId), 'Configura el establecimiento y un RBD valido.'),
            $this->check('regulatory_profiles', 'Perfiles normativos', $this->hasActiveProfiles(), 'Ejecuta LibroDigitalSeeder y revisa la vigencia normativa.'),
            $this->check('normative_sources', 'Fuentes normativas registradas', $this->hasNormativeSources(), 'Registra fuentes oficiales con fecha de consulta y SHA-256 cuando exista archivo.'),
            $this->check('queue_runtime', 'Cola asíncrona de producción', ! app()->environment('production') || config('queue.default') !== 'sync', 'Configura y supervisa un worker de cola no síncrono antes del piloto productivo.'),
            $this->check('core_compliance_blockers', 'Bloqueadores del nucleo cerrados', ! $this->hasOpenBlockers('lcd_enabled', [], $schoolId), 'Cierra con evidencia los bloqueadores normativos asociados al nucleo LCD.'),
        ];
        $identityChecks = [
            $this->check('identity_verifier', 'Verificador de identidad', $this->identityVerifierConfigured(), 'Configura contrato y endpoint oficial del verificador.', $identityRequired, 'identity'),
        ];
        $edeChecks = [
            $this->check('ede_runtime_enabled', 'Runtime EDE autorizado', (bool) config('libro_digital.ede.enabled'), 'Habilita el runtime EDE solo tras aprobar su contrato.', $edeRequired, 'ede'),
            $this->check('ede_version', 'Version EDE activa', $this->hasEdeVersion(), 'Importa, hashea y activa una version EDE oficial.', $edeRequired, 'ede'),
            $this->check('ede_validator_digest', 'Validador EDE fijado por digest', filled(config('libro_digital.ede.validator_digest')), 'Fija la imagen oficial por digest antes de validar.', $edeRequired, 'ede'),
            $this->check('ede_command_contract', 'Contrato EDE verificado', (bool) config('libro_digital.ede.command_contract_verified'), 'Archiva y prueba el contrato parse/insert/check.', $edeRequired, 'ede'),
            $this->check('ede_report_contract', 'Resultado del validador interpretable', filled(config('libro_digital.ede.validation_report.success_path')), 'Configura el campo contractual que declara éxito en el reporte de check.', $edeRequired, 'ede'),
            $this->check('ede_compliance_blockers', 'Bloqueadores EDE cerrados', ! $this->hasOpenBlockers('lcd_ede_export_enabled', ['EDE_VALIDATOR_NOT_EXECUTED']), 'Cierra los bloqueadores EDE previos a generar un candidato.', $edeRequired, 'ede'),
        ];
        $parvulariaChecks = [
            $this->check('parvularia_profile', 'Perfil normativo de parvularia', $this->hasEducationTypeProfile('parvularia'), 'Registra y revisa un perfil normativo vigente para educación parvularia.', $parvulariaRequired, 'parvularia'),
            $this->check('parvularia_compliance_blockers', 'Bloqueadores de parvularia cerrados', ! $this->hasOpenBlockers('lcd_parvularia_enabled'), 'Cierra con evidencia las reglas finas pendientes de parvularia.', $parvulariaRequired, 'parvularia'),
        ];
        $sigeChecks = [
            $this->check('sige_driver', 'Integración SIGE configurada', in_array((string) config('libro_digital.sige.driver'), ['manual'], true), 'Configura un driver SIGE autorizado y verificable.', $sigeRequired, 'sige'),
            $this->check('sige_compliance_blockers', 'Bloqueadores SIGE cerrados', ! $this->hasOpenBlockers('lcd_sige_reconciliation_enabled'), 'Cierra la revisión documental y contractual de la integración SIGE.', $sigeRequired, 'sige'),
        ];
        $fiscalizationChecks = [
            $this->check('fiscalization_ede_ready', 'Base EDE lista para fiscalización', collect($edeChecks)->every('configured'), 'Completa versión, mapeo, runtime, digest y contrato EDE antes de habilitar fiscalización.', $fiscalizationRequired, 'fiscalization'),
            $this->check('fiscalization_compliance_blockers', 'Bloqueadores de fiscalización cerrados', ! $this->hasOpenBlockers('lcd_fiscalization_download_enabled'), 'Cierra los bloqueadores institucionales de descarga fiscalizadora.', $fiscalizationRequired, 'fiscalization'),
        ];
        $checks = [...$coreChecks, ...$identityChecks, ...$edeChecks, ...$parvulariaChecks, ...$sigeChecks, ...$fiscalizationChecks];

        $blockers = collect($checks)
            ->where('required', true)
            ->where('configured', false)
            ->map(fn (array $check): array => [
                'code' => 'COMPLIANCE_BLOCKER_'.strtoupper($check['code']),
                'message' => $check['remediation'],
            ])
            ->values()
            ->all();

        $coreReady = collect($coreChecks)->every('configured');
        $identityReady = collect($identityChecks)->every('configured');
        $edeReady = collect($edeChecks)->every('configured');

        return [
            'ready' => $blockers === [],
            'core_ready' => $coreReady,
            'module_enabled' => (bool) ($flags['lcd_enabled'] ?? false),
            'capabilities' => [
                'identity' => ['enabled' => $identityRequired, 'ready' => $identityReady],
                'ede' => ['enabled' => $edeRequired, 'ready' => $edeReady],
                'parvularia' => ['enabled' => $parvulariaRequired, 'ready' => collect($parvulariaChecks)->every('configured')],
                'sige' => ['enabled' => $sigeRequired, 'ready' => collect($sigeChecks)->every('configured')],
                'fiscalization' => ['enabled' => $fiscalizationRequired, 'ready' => collect($fiscalizationChecks)->every('configured')],
            ],
            'checks' => $checks,
            'blockers' => $blockers,
        ];
    }

    /** @return array<string, mixed> */
    private function check(string $code, string $label, bool $configured, string $remediation, bool $required = true, string $capability = 'core'): array
    {
        return [
            'code' => $code,
            'label' => $label,
            'configured' => $configured,
            'required' => $required,
            'passed' => $configured || ! $required,
            'status' => $required ? ($configured ? 'passed' : 'blocked') : ($configured ? 'available' : 'skipped'),
            'capability' => $capability,
            'remediation' => $remediation,
        ];
    }

    private function storageAvailable(): bool
    {
        try {
            $disk = Storage::disk((string) config('libro_digital.storage.disk', 'local'));
            $root = (string) config('libro_digital.storage.root', 'private/libro-digital');
            $disk->makeDirectory($root);

            return $disk->exists($root);
        } catch (\Throwable) {
            return false;
        }
    }

    private function schoolConfigured(?int $schoolId): bool
    {
        if (! Schema::hasTable('lcd_schools')) {
            return false;
        }

        return DB::table('lcd_schools')
            ->when($schoolId, fn ($query) => $query->where('id', $schoolId))
            ->where('active', true)
            ->whereNotNull('rbd')
            ->where('rbd', '<>', '')
            ->exists();
    }

    private function hasActiveProfiles(): bool
    {
        return Schema::hasTable('lcd_regulatory_profiles')
            && DB::table('lcd_regulatory_profiles')->where('active', true)->exists();
    }

    private function hasNormativeSources(): bool
    {
        return Schema::hasTable('lcd_normative_sources')
            && DB::table('lcd_normative_sources')->whereNotNull('consulted_at')->exists();
    }

    private function hasEdeVersion(): bool
    {
        return Schema::hasTable('lcd_ede_versions')
            && DB::table('lcd_ede_versions')
                ->where('status', 'active')
                ->whereNotNull('source_hash')
                ->whereNotNull('schema_hash')
                ->whereExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('lcd_ede_mappings')
                        ->whereColumn('lcd_ede_mappings.ede_version_id', 'lcd_ede_versions.id')
                        ->where('lcd_ede_mappings.active', true);
                })
                ->exists();
    }

    private function identityVerifierConfigured(): bool
    {
        $driver = (string) config('libro_digital.identity_verifier.driver', 'disabled');
        if ($driver !== 'mineduc_transactional') {
            return false;
        }

        return filled(config('libro_digital.identity_verifier.transactional_url'));
    }

    private function hasEducationTypeProfile(string $educationType): bool
    {
        if (! Schema::hasTable('lcd_regulatory_profiles')) {
            return false;
        }

        return DB::table('lcd_regulatory_profiles')->where('active', true)->get(['rules_snapshot'])->contains(function ($profile) use ($educationType): bool {
            $rules = json_decode((string) $profile->rules_snapshot, true) ?: [];

            return in_array($educationType, (array) ($rules['education_types'] ?? []), true);
        });
    }

    /** @param array<int, string> $ignoredCodes */
    private function hasOpenBlockers(string $featureFlag, array $ignoredCodes = [], ?int $schoolId = null): bool
    {
        if (! Schema::hasTable('lcd_settings')) {
            return true;
        }

        $raw = DB::table('lcd_settings')->where('scope_key', 'compliance')->where('key', 'open_blockers')->value('value');
        if ($raw === null) {
            return false;
        }

        return collect(json_decode((string) $raw, true) ?: [])->contains(function (array $item) use ($featureFlag, $ignoredCodes, $schoolId): bool {
            $code = (string) ($item['code'] ?? '');
            if (($item['feature_flag'] ?? null) !== $featureFlag || in_array($code, $ignoredCodes, true)) {
                return false;
            }
            if ($code === 'CURRICULUM_OA_NOT_IMPORTED' && $schoolId && $this->curriculumReady($schoolId)) {
                return false;
            }

            return true;
        });
    }

    private function curriculumReady(int $schoolId): bool
    {
        foreach ([
            'lcd_curriculum_catalog_activations',
            'lcd_curriculum_import_batches',
            'lcd_curriculum_import_evidences',
            'lcd_curriculum_catalogs',
            'lcd_learning_objectives',
            'lcd_curriculum_sources',
            'lcd_learning_objective_sources',
            'lcd_normative_sources',
            'lcd_subject_curriculum_links',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        $requiredGrades = CurriculumImportValidator::GRADE_CODES;
        $activations = DB::table('lcd_curriculum_catalog_activations as activations')
            ->join('lcd_curriculum_import_batches as batches', 'batches.id', '=', 'activations.import_batch_id')
            ->join('lcd_curriculum_catalogs as catalogs', 'catalogs.id', '=', 'activations.curriculum_catalog_id')
            ->where('activations.school_id', $schoolId)
            ->where('activations.status', 'activated')
            ->where('batches.status', 'activated')
            ->whereColumn('batches.curriculum_catalog_id', 'catalogs.id')
            ->whereColumn('batches.school_id', 'activations.school_id')
            ->whereColumn('batches.academic_year_id', 'activations.academic_year_id')
            ->whereNotNull('batches.source_hash')
            ->whereNotNull('batches.manifest_hash')
            ->whereNotNull('catalogs.source_hash')
            ->where('catalogs.active', true)
            ->whereExists(function ($evidence): void {
                $evidence->selectRaw('1')
                    ->from('lcd_curriculum_import_evidences as evidences')
                    ->whereColumn('evidences.import_batch_id', 'batches.id')
                    ->whereColumn('evidences.sha256', 'batches.source_hash')
                    ->where('evidences.evidence_kind', 'normalized_curriculum_workbook')
                    ->where('evidences.status', 'verified');
            })
            ->select([
                'activations.academic_year_id',
                'catalogs.id as catalog_id',
                'catalogs.source_hash as catalog_payload_hash',
                'batches.id as batch_id',
                'batches.source_hash as workbook_hash',
                'batches.disk as workbook_disk',
                'batches.private_path as workbook_path',
                'batches.manifest',
            ])
            ->get();

        return $activations->contains(function ($activation) use ($schoolId, $requiredGrades): bool {
            $manifest = $this->jsonArray($activation->manifest);
            $coverage = (array) ($manifest['coverage'] ?? []);
            if (($coverage['complete_nt1_4m'] ?? false) !== true
                || array_values((array) ($coverage['missing_grade_codes'] ?? [])) !== []) {
                return false;
            }
            if (! $this->sameHash($activation->catalog_payload_hash, $manifest['catalog_payload_hash'] ?? null)
                || ! $this->archivedPathMatches($activation->workbook_disk, $activation->workbook_path, $activation->workbook_hash)) {
                return false;
            }
            $workbookEvidence = DB::table('lcd_curriculum_import_evidences')
                ->where('import_batch_id', $activation->batch_id)
                ->where('evidence_kind', 'normalized_curriculum_workbook')
                ->where('status', 'verified')
                ->where('sha256', $activation->workbook_hash)
                ->first(['sha256', 'disk', 'private_path']);
            if (! $workbookEvidence
                || $workbookEvidence->private_path !== $activation->workbook_path
                || ! $this->archivedEvidenceMatches($workbookEvidence)) {
                return false;
            }

            $objectives = DB::table('lcd_learning_objectives')
                ->where('curriculum_catalog_id', $activation->catalog_id)
                ->where('active', true)
                ->get(['id', 'grade_code', 'objective_type', 'objective_key', 'source_row_hash']);
            $coveredGrades = $objectives
                ->whereIn('objective_type', CurriculumImportValidator::OBJECTIVE_TYPES)
                ->whereIn('grade_code', $requiredGrades)
                ->pluck('grade_code')->unique()->sort()->values()->all();
            $expected = collect($requiredGrades)->sort()->values()->all();
            if ($coveredGrades !== $expected) {
                return false;
            }
            if ($objectives->isEmpty() || $objectives->contains(fn ($objective): bool => ! filled($objective->source_row_hash) || ! filled($objective->objective_key))) {
                return false;
            }

            $allRelationships = DB::table('lcd_learning_objective_sources as relationships')
                ->join('lcd_learning_objectives as objectives', 'objectives.id', '=', 'relationships.learning_objective_id')
                ->join('lcd_curriculum_sources as sources', 'sources.id', '=', 'relationships.curriculum_source_id')
                ->join('lcd_normative_sources as normative_sources', 'normative_sources.id', '=', 'sources.normative_source_id')
                ->where('objectives.curriculum_catalog_id', $activation->catalog_id)
                ->get([
                    'relationships.learning_objective_id',
                    'relationships.source_role',
                    'sources.id as source_id',
                    'sources.curriculum_catalog_id as source_catalog_id',
                    'sources.source_key',
                    'sources.status as source_status',
                    'sources.declared_sha256',
                    'sources.verified_sha256',
                    'normative_sources.status as normative_status',
                    'normative_sources.sha256 as normative_sha256',
                    'normative_sources.private_path as normative_private_path',
                ]);
            $relationshipsByObjective = $allRelationships->groupBy('learning_objective_id');
            if ($objectives->contains(function ($objective) use ($relationshipsByObjective): bool {
                return $relationshipsByObjective->get($objective->id, collect())
                    ->where('source_role', 'canonical_text')
                    ->count() !== 1;
            })) {
                return false;
            }

            $linkedSources = $allRelationships->unique('source_id')->values();
            if ($linkedSources->isEmpty() || $linkedSources->contains(function ($source) use ($activation): bool {
                return (int) $source->source_catalog_id !== (int) $activation->catalog_id
                    || $source->source_status !== 'verified'
                    || ! $this->sameHash($source->declared_sha256, $source->verified_sha256)
                    || $source->normative_status !== 'verified'
                    || ! $this->sameHash($source->verified_sha256, $source->normative_sha256)
                    || ! filled($source->normative_private_path)
                    || ! $this->archivedPathMatches(
                        (string) config('libro_digital.storage.disk', 'local'),
                        $source->normative_private_path,
                        $source->normative_sha256,
                    );
            })) {
                return false;
            }

            $officialEvidence = DB::table('lcd_curriculum_import_evidences')
                ->where('import_batch_id', $activation->batch_id)
                ->where('evidence_kind', 'official_source_document')
                ->where('status', 'verified')
                ->get(['sha256', 'disk', 'private_path', 'metadata']);
            if ($linkedSources->contains(function ($source) use ($officialEvidence): bool {
                return ! $officialEvidence->contains(function ($evidence) use ($source): bool {
                    $metadata = $this->jsonArray($evidence->metadata);

                    return ($metadata['source_key'] ?? null) === $source->source_key
                        && ($metadata['hash_scope'] ?? null) === 'official_source_bytes_verified'
                        && filled($evidence->private_path)
                        && $this->sameHash($evidence->sha256, $source->verified_sha256);
                });
            })) {
                return false;
            }
            $linkedSourceKeys = $linkedSources->pluck('source_key')->all();
            if ($officialEvidence
                ->filter(fn ($evidence): bool => in_array($this->jsonArray($evidence->metadata)['source_key'] ?? null, $linkedSourceKeys, true))
                ->contains(fn ($evidence): bool => ! $this->archivedEvidenceMatches($evidence))) {
                return false;
            }

            return DB::table('lcd_subject_curriculum_links')
                ->where('school_id', $schoolId)
                ->where('academic_year_id', $activation->academic_year_id)
                ->where('curriculum_catalog_id', $activation->catalog_id)
                ->where('active', true)
                ->exists();
        });
    }

    /** @return array<string, mixed> */
    private function jsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_object($value)) {
            return (array) $value;
        }
        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function sameHash(mixed $left, mixed $right): bool
    {
        return is_string($left)
            && is_string($right)
            && preg_match('/^[a-f0-9]{64}$/', $left) === 1
            && preg_match('/^[a-f0-9]{64}$/', $right) === 1
            && hash_equals($left, $right);
    }

    private function archivedEvidenceMatches(object $evidence): bool
    {
        return $this->archivedPathMatches(
            filled($evidence->disk) ? (string) $evidence->disk : (string) config('libro_digital.storage.disk', 'local'),
            $evidence->private_path ?? null,
            $evidence->sha256 ?? null,
        );
    }

    private function archivedPathMatches(mixed $diskName, mixed $path, mixed $expectedHash): bool
    {
        try {
            if (! is_string($diskName) || ! filled($diskName) || ! is_string($path) || ! filled($path)
                || ! is_string($expectedHash) || preg_match('/^[a-f0-9]{64}$/', $expectedHash) !== 1) {
                return false;
            }
            $disk = Storage::disk($diskName);
            if (! $disk->exists($path)) {
                return false;
            }
            $contents = Crypt::decryptString($disk->get($path));

            return hash_equals($expectedHash, hash('sha256', $contents));
        } catch (\Throwable) {
            return false;
        }
    }
}
