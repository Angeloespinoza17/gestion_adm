<?php

namespace Tests\Feature\LibroDigital;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\LibroDigital\CurriculumCatalog;
use App\Models\LibroDigital\CurriculumImportBatch;
use App\Models\LibroDigital\FeatureFlag;
use App\Models\LibroDigital\RegulatoryProfile;
use App\Models\LibroDigital\School;
use App\Models\LibroDigital\Setting;
use App\Models\Role;
use App\Models\Schedule\ScheduleSubject;
use App\Models\User;
use App\Services\LibroDigital\CompliancePreflightService;
use App\Services\LibroDigital\CurriculumObjectiveIdentity;
use App\Services\LibroDigital\XlsxReportBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CurriculumImportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_rollout_allows_subject_bootstrap_but_blocks_normal_mutations(): void
    {
        [$requester, $school] = $this->context();

        $this->actingAs($requester)->withHeader('Idempotency-Key', 'lcd-subject-bootstrap-0001')
            ->postJson('/api/libro-digital/v1/subjects', [
                'school_id' => $school->id,
                'name' => 'Lenguaje y Comunicación',
                'code' => 'LEN',
                'area' => 'Lenguaje',
                'active' => true,
            ])->assertCreated()->assertJsonPath('data.code', 'LEN');

        $this->withHeader('Idempotency-Key', 'lcd-normal-mutation-blocked-0001')
            ->postJson('/api/libro-digital/v1/books', ['school_id' => $school->id])
            ->assertStatus(503)->assertJsonPath('code', 'LCD_FEATURE_DISABLED');
        $this->assertDatabaseHas('lcd_audit_events', ['event' => 'lcd.subject.created']);
    }

    public function test_full_nt1_to_4m_import_is_governed_idempotent_and_resolves_only_the_school_blocker(): void
    {
        Storage::fake('local');
        config(['libro_digital.storage.disk' => 'local']);
        [$requester, $school, $year, $subject] = $this->context(withSubject: true);
        $approver = $this->superAdmin();
        $activator = $this->superAdmin();
        Setting::query()->create([
            'scope_key' => 'compliance',
            'key' => 'open_blockers',
            'value' => [['code' => 'CURRICULUM_OA_NOT_IMPORTED', 'feature_flag' => 'lcd_enabled']],
        ]);
        $contents = $this->workbook($school, $year, $subject);

        $validated = $this->actingAs($requester)->withHeader('Idempotency-Key', 'lcd-curriculum-validate-0001')
            ->post('/api/libro-digital/v1/curriculum/imports/validate', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'file' => UploadedFile::fake()->createWithContent('curriculum.xlsx', $contents),
                'evidence_files' => $this->evidenceFiles(),
            ])->assertCreated()->assertJsonPath('data.status', 'validated')
            ->assertJsonPath('data.manifest.coverage.complete_nt1_4m', true)
            ->assertJsonPath('data.manifest.coverage.missing_grade_codes', [])
            ->assertJsonPath('data.counts.objectives', 14)
            ->assertJsonPath('data.counts.sources', 5)
            ->assertJsonPath('data.counts.objective_sources', 14)
            ->assertJsonPath('data.source_evidence.missing_source_keys', [])
            ->assertJsonPath('data.counts.links', 1);
        $publicId = $validated->json('data.public_id');

        $this->withHeader('Idempotency-Key', 'lcd-curriculum-validate-retry-0001')
            ->post('/api/libro-digital/v1/curriculum/imports/validate', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'file' => UploadedFile::fake()->createWithContent('curriculum-copy.xlsx', $contents),
            ])->assertOk()->assertJsonPath('data.created', false)->assertJsonPath('data.public_id', $publicId);

        $this->withHeaders(['Idempotency-Key' => 'lcd-curriculum-self-approve-0001', 'If-Match' => '1'])
            ->postJson('/api/libro-digital/v1/curriculum/imports/'.$publicId.'/approve', [
                'note' => 'Intento del solicitante que debe rechazarse.',
                'lock_version' => 1,
            ])->assertForbidden()->assertJsonPath('code', 'LCD_CURRICULUM_SEPARATION_OF_DUTIES');

        $approved = $this->actingAs($approver)
            ->withHeaders(['Idempotency-Key' => 'lcd-curriculum-approve-0001', 'If-Match' => '1'])
            ->postJson('/api/libro-digital/v1/curriculum/imports/'.$publicId.'/approve', [
                'school_id' => $school->id,
                'note' => 'Cobertura y trazabilidad revisadas por contraparte.',
                'lock_version' => 1,
            ])->assertOk()->assertJsonPath('data.status', 'approved')->assertJsonPath('data.lock_version', 2);

        $this->actingAs($activator)
            ->withHeaders(['Idempotency-Key' => 'lcd-curriculum-activate-0001', 'If-Match' => '2'])
            ->postJson('/api/libro-digital/v1/curriculum/imports/'.$publicId.'/activate', [
                'school_id' => $school->id,
                'note' => 'Activación institucional posterior a aprobación segregada.',
                'lock_version' => 2,
            ])->assertOk()->assertJsonPath('data.status', 'activated')->assertJsonPath('data.lock_version', 3)
            ->assertJsonPath('data.capabilities.can_activate', false);

        $this->assertDatabaseCount('lcd_learning_objectives', 14);
        $this->assertDatabaseCount('lcd_curriculum_sources', 5);
        $this->assertDatabaseCount('lcd_learning_objective_sources', 14);
        $this->assertDatabaseHas('lcd_learning_objectives', [
            'code' => 'OAT-TRANSVERSAL', 'grade_code' => '3M', 'curriculum_track' => 'TP',
            'objective_type' => 'OAG',
        ]);
        $this->assertDatabaseHas('lcd_subject_curriculum_links', [
            'school_id' => $school->id, 'academic_year_id' => $year->id,
            'schedule_subject_id' => $subject->id, 'grade_code' => '5B', 'curriculum_track' => null,
        ]);
        $this->assertDatabaseHas('lcd_curriculum_import_evidences', [
            'evidence_kind' => 'normalized_curriculum_workbook', 'status' => 'verified',
        ]);
        $this->assertDatabaseCount('lcd_curriculum_import_evidences', 11);
        $this->assertDatabaseHas('lcd_audit_events', ['event' => 'compliance.blocker.curriculum.resolved']);
        $preflight = app(CompliancePreflightService::class)->run($school->id);
        $this->assertTrue((bool) collect($preflight['checks'])->firstWhere('code', 'core_compliance_blockers')['configured']);

        $different = $this->workbook($school, $year, $subject, 'Descripción distinta que cambia el hash.');
        $this->actingAs($requester)->withHeader('Idempotency-Key', 'lcd-curriculum-version-conflict-0001')
            ->post('/api/libro-digital/v1/curriculum/imports/validate', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'file' => UploadedFile::fake()->createWithContent('curriculum-conflict.xlsx', $different),
            ])->assertConflict()->assertJsonPath('code', 'LCD_CURRICULUM_VERSION_CONFLICT');
        $this->assertDatabaseCount('lcd_curriculum_catalogs', 1);
        $this->assertSame('approved', $approved->json('data.status'));
    }

    public function test_same_official_oat_code_is_allowed_across_grades_but_exact_duplicate_is_rejected(): void
    {
        [$requester, $school, $year, $subject] = $this->context(withSubject: true);
        $valid = $this->workbook($school, $year, $subject);

        $this->actingAs($requester)->withHeader('Idempotency-Key', 'lcd-curriculum-repeated-code-0001')
            ->post('/api/libro-digital/v1/curriculum/imports/validate', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'file' => UploadedFile::fake()->createWithContent('repeated-code.xlsx', $valid),
            ])->assertCreated()->assertJsonPath('data.status', 'validated');

        $duplicate = $this->workbook($school, $year, $subject, duplicateExactObjective: true, version: '2026.2');
        $this->withHeader('Idempotency-Key', 'lcd-curriculum-exact-duplicate-0001')
            ->post('/api/libro-digital/v1/curriculum/imports/validate', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'file' => UploadedFile::fake()->createWithContent('exact-duplicate.xlsx', $duplicate),
            ])->assertCreated()->assertJsonPath('data.status', 'invalid')
            ->assertJsonFragment(['code' => 'duplicate_objective']);
    }

    public function test_general_formation_track_is_valid_for_3m_and_4m(): void
    {
        [$requester, $school, $year, $subject] = $this->context(withSubject: true);
        $contents = $this->workbook(
            $school,
            $year,
            $subject,
            version: '2026.GENERAL',
            upperSecondaryGeneral: true,
        );

        $this->actingAs($requester)->withHeader('Idempotency-Key', 'lcd-curriculum-general-3m-4m')
            ->post('/api/libro-digital/v1/curriculum/imports/validate', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'file' => UploadedFile::fake()->createWithContent('general-3m-4m.xlsx', $contents),
            ])->assertCreated()
            ->assertJsonPath('data.status', 'validated')
            ->assertJsonPath('data.counts.sources', 4)
            ->assertJsonPath('data.manifest.coverage.matrix.3M.tracks.0', 'GENERAL')
            ->assertJsonPath('data.manifest.coverage.matrix.4M.tracks.0', 'GENERAL');
    }

    public function test_activation_fails_closed_until_every_linked_official_source_is_archived(): void
    {
        Storage::fake('local');
        config(['libro_digital.storage.disk' => 'local']);
        [$requester, $school, $year, $subject] = $this->context(withSubject: true);
        $approver = $this->superAdmin();
        $activator = $this->superAdmin();
        $contents = $this->workbook($school, $year, $subject);

        $validated = $this->actingAs($requester)->withHeader('Idempotency-Key', 'lcd-curriculum-missing-evidence-validate')
            ->post('/api/libro-digital/v1/curriculum/imports/validate', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'file' => UploadedFile::fake()->createWithContent('curriculum.xlsx', $contents),
            ])->assertCreated()->assertJsonPath('data.status', 'validated')
            ->assertJsonCount(5, 'data.source_evidence.missing_source_keys');
        $publicId = $validated->json('data.public_id');

        $this->actingAs($approver)->withHeaders(['Idempotency-Key' => 'lcd-curriculum-missing-evidence-approve', 'If-Match' => '1'])
            ->postJson('/api/libro-digital/v1/curriculum/imports/'.$publicId.'/approve', [
                'school_id' => $school->id,
                'note' => 'Aprobación estructural antes de completar evidencias.',
                'lock_version' => 1,
            ])->assertOk();

        $this->actingAs($activator)->withHeaders(['Idempotency-Key' => 'lcd-curriculum-missing-evidence-activate', 'If-Match' => '2'])
            ->post('/api/libro-digital/v1/curriculum/imports/'.$publicId.'/activate', [
                'school_id' => $school->id,
                'note' => 'Intento de activación sin archivos oficiales completos.',
                'lock_version' => 2,
            ])->assertConflict()->assertJsonPath('code', 'LCD_CURRICULUM_SOURCE_EVIDENCE_REQUIRED');

        $this->withHeaders(['Idempotency-Key' => 'lcd-curriculum-evidence-hash-mismatch', 'If-Match' => '2'])
            ->post('/api/libro-digital/v1/curriculum/imports/'.$publicId.'/activate', [
                'school_id' => $school->id,
                'note' => 'Intento con evidencia cuyo hash no coincide.',
                'lock_version' => 2,
                'evidence_files' => [
                    'SRC-PARVULARIA' => UploadedFile::fake()->createWithContent('parvularia.html', '<html>bytes distintos</html>'),
                ],
            ])->assertUnprocessable()->assertJsonPath('code', 'LCD_CURRICULUM_EVIDENCE_HASH_MISMATCH');

        $this->withHeaders(['Idempotency-Key' => 'lcd-curriculum-evidence-complete', 'If-Match' => '2'])
            ->post('/api/libro-digital/v1/curriculum/imports/'.$publicId.'/activate', [
                'school_id' => $school->id,
                'note' => 'Activación con el corpus normativo oficial completo.',
                'lock_version' => 2,
                'evidence_files' => $this->evidenceFiles(),
            ])->assertOk()->assertJsonPath('data.status', 'activated')
            ->assertJsonPath('data.source_evidence.missing_source_keys', []);
    }

    public function test_same_portable_corpus_is_reused_by_two_schools_with_distinct_workbooks_and_evidence(): void
    {
        Storage::fake('local');
        config(['libro_digital.storage.disk' => 'local']);
        [$requesterA, $schoolA, $yearA, $subject] = $this->context(withSubject: true);
        [$schoolB, $yearB] = $this->additionalSchoolYear();
        $requesterB = $this->superAdmin();
        Setting::query()->create([
            'scope_key' => 'compliance',
            'key' => 'open_blockers',
            'value' => [['code' => 'CURRICULUM_OA_NOT_IMPORTED', 'feature_flag' => 'lcd_enabled']],
        ]);
        $workbookA = $this->workbook($schoolA, $yearA, $subject);
        $workbookB = $this->workbook($schoolB, $yearB, $subject);
        $this->assertNotSame(hash('sha256', $workbookA), hash('sha256', $workbookB));

        $batchA = $this->validateApproveActivate(
            'tenant-a', $requesterA, $this->superAdmin(), $this->superAdmin(),
            $schoolA, $yearA, $workbookA,
        );
        $batchB = $this->validateApproveActivate(
            'tenant-b', $requesterB, $this->superAdmin(), $this->superAdmin(),
            $schoolB, $yearB, $workbookB,
        );

        $this->assertNotSame($batchA->source_hash, $batchB->source_hash);
        $this->assertSame(
            data_get($batchA->manifest, 'catalog_payload_hash'),
            data_get($batchB->manifest, 'catalog_payload_hash'),
        );
        $this->assertDatabaseCount('lcd_curriculum_catalogs', 1);
        $this->assertDatabaseCount('lcd_curriculum_import_batches', 2);
        $this->assertDatabaseCount('lcd_curriculum_catalog_activations', 2);
        $this->assertDatabaseCount('lcd_curriculum_sources', 5);
        $this->assertDatabaseCount('lcd_learning_objectives', 14);
        $this->assertDatabaseCount('lcd_learning_objective_sources', 14);
        $this->assertSame(5, $batchA->evidences()->where('evidence_kind', 'official_source_document')->count());
        $this->assertSame(5, $batchB->evidences()->where('evidence_kind', 'official_source_document')->count());
        $this->assertSame(
            data_get($batchA->manifest, 'catalog_payload_hash'),
            CurriculumCatalog::query()->sole()->source_hash,
        );

        foreach ([$schoolA, $schoolB] as $school) {
            $preflight = app(CompliancePreflightService::class)->run($school->id);
            $this->assertTrue((bool) collect($preflight['checks'])->firstWhere('code', 'core_compliance_blockers')['configured']);
        }
    }

    public function test_forward_only_backfill_uses_the_same_objective_identity_as_new_imports(): void
    {
        [, , , $subject] = $this->context(withSubject: true);
        $catalog = CurriculumCatalog::query()->create([
            'code' => 'HISTORICO', 'name' => 'Catálogo histórico', 'version' => '1', 'active' => true,
        ]);
        $objectiveId = DB::table('lcd_learning_objectives')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'curriculum_catalog_id' => $catalog->id,
            'schedule_subject_id' => $subject->id,
            'level_code' => 'basica',
            'grade_code' => '5b',
            'curriculum_track' => null,
            'axis_code' => 'lectura',
            'objective_type' => 'oat',
            'code' => 'OAT-7',
            'objective_key' => null,
            'description' => 'Fila anterior a la clave compuesta.',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_08_13_000010_add_objective_scope_key_to_lcd_learning_objectives.php');
        $migration->up();

        $this->assertSame(
            CurriculumObjectiveIdentity::key([
                'code' => 'OAT-7',
                'objective_type' => 'OAT',
                'subject_code' => $subject->code,
                'level_code' => 'BASICA',
                'grade_code' => '5B',
                'curriculum_track' => null,
                'axis_code' => 'LECTURA',
            ]),
            DB::table('lcd_learning_objectives')->where('id', $objectiveId)->value('objective_key'),
        );
    }

    /** @return array{User, School, AcademicYear, ScheduleSubject|null} */
    private function context(bool $withSubject = false): array
    {
        $requester = $this->superAdmin();
        $school = School::query()->create([
            'rbd' => '12345-6', 'name' => 'Escuela Curricular', 'timezone' => 'America/Santiago', 'active' => true,
        ]);
        $year = AcademicYear::factory()->create([
            'year' => 2035, 'name' => '2035', 'starts_at' => '2035-03-01', 'ends_at' => '2035-12-20',
            'is_active' => true, 'is_closed' => false,
        ]);
        $profile = RegulatoryProfile::query()->create([
            'code' => 'CL-CURRICULUM', 'name' => 'Perfil curricular', 'version' => '1',
            'effective_from' => '2030-01-01', 'retention_years' => 6, 'rules_snapshot' => [], 'active' => true,
        ]);
        $school->academicYears()->attach($year->id, [
            'regulatory_profile_id' => $profile->id, 'rbd_snapshot' => $school->rbd,
            'year_snapshot' => $year->year, 'timezone_snapshot' => $school->timezone, 'active' => true,
        ]);
        $level = EducationLevel::query()->firstOrCreate(
            ['name' => '5° básico'],
            ['order' => 550, 'type' => 'basica'],
        );
        CourseSection::factory()->create([
            'academic_year_id' => $year->id, 'education_level_id' => $level->id,
            'display_name' => '5° Básico A', 'section_name' => 'A', 'active' => true,
        ]);
        FeatureFlag::query()->create([
            'school_id' => $school->id, 'scope_key' => 'school:'.$school->id,
            'code' => 'lcd_enabled', 'enabled' => false,
        ]);
        $subject = $withSubject ? ScheduleSubject::query()->create([
            'name' => 'Lenguaje', 'code' => 'LEN', 'area' => 'Lenguaje', 'active' => true,
        ]) : null;

        return [$requester, $school, $year, $subject];
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin', 'active' => true]);
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    /** @return array{School, AcademicYear} */
    private function additionalSchoolYear(): array
    {
        $school = School::query()->create([
            'rbd' => '54321-0', 'name' => 'Segundo establecimiento', 'timezone' => 'America/Santiago', 'active' => true,
        ]);
        $year = AcademicYear::factory()->create([
            'year' => 2036, 'name' => '2036', 'starts_at' => '2036-03-01', 'ends_at' => '2036-12-20',
            'is_active' => true, 'is_closed' => false,
        ]);
        $profile = RegulatoryProfile::query()->firstOrFail();
        $school->academicYears()->attach($year->id, [
            'regulatory_profile_id' => $profile->id, 'rbd_snapshot' => $school->rbd,
            'year_snapshot' => $year->year, 'timezone_snapshot' => $school->timezone, 'active' => true,
        ]);
        $level = EducationLevel::query()->firstOrCreate(
            ['name' => '5° básico'],
            ['order' => 550, 'type' => 'basica'],
        );
        CourseSection::factory()->create([
            'academic_year_id' => $year->id, 'education_level_id' => $level->id,
            'display_name' => '5° Básico B', 'section_name' => 'B', 'active' => true,
        ]);
        FeatureFlag::query()->create([
            'school_id' => $school->id, 'scope_key' => 'school:'.$school->id,
            'code' => 'lcd_enabled', 'enabled' => false,
        ]);

        return [$school, $year];
    }

    private function validateApproveActivate(
        string $key,
        User $requester,
        User $approver,
        User $activator,
        School $school,
        AcademicYear $year,
        string $workbook,
    ): CurriculumImportBatch {
        $validated = $this->actingAs($requester)->withHeader('Idempotency-Key', $key.'-validate')
            ->post('/api/libro-digital/v1/curriculum/imports/validate', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'file' => UploadedFile::fake()->createWithContent($key.'.xlsx', $workbook),
                'evidence_files' => $this->evidenceFiles(),
            ])->assertCreated()->assertJsonPath('data.status', 'validated');
        $publicId = $validated->json('data.public_id');
        $this->actingAs($approver)->withHeaders(['Idempotency-Key' => $key.'-approve', 'If-Match' => '1'])
            ->postJson('/api/libro-digital/v1/curriculum/imports/'.$publicId.'/approve', [
                'school_id' => $school->id,
                'note' => 'Aprobación segregada para '.$key.'.',
                'lock_version' => 1,
            ])->assertOk();
        $this->actingAs($activator)->withHeaders(['Idempotency-Key' => $key.'-activate', 'If-Match' => '2'])
            ->post('/api/libro-digital/v1/curriculum/imports/'.$publicId.'/activate', [
                'school_id' => $school->id,
                'note' => 'Activación segregada para '.$key.'.',
                'lock_version' => 2,
            ])->assertOk()->assertJsonPath('data.status', 'activated');

        return CurriculumImportBatch::query()->where('public_id', $publicId)->firstOrFail();
    }

    private function workbook(
        School $school,
        AcademicYear $year,
        ScheduleSubject $subject,
        string $descriptionSuffix = '',
        bool $duplicateExactObjective = false,
        string $version = '2026.1',
        bool $upperSecondaryGeneral = false,
    ): string {
        $grades = ['NT1', 'NT2', '1B', '2B', '3B', '4B', '5B', '6B', '7B', '8B', '1M', '2M', '3M', '4M'];
        $objectives = collect($grades)->map(function (string $grade) use ($subject, $descriptionSuffix, $version, $upperSecondaryGeneral): array {
            $level = in_array($grade, ['NT1', 'NT2'], true) ? 'PARVULARIA' : (str_ends_with($grade, 'B') ? 'BASICA' : 'MEDIA');
            $isOffered = $grade === '5B';
            $isParvularia = $level === 'PARVULARIA';
            $track = $upperSecondaryGeneral && in_array($grade, ['3M', '4M'], true)
                ? 'GENERAL'
                : ($grade === '3M' ? 'TP' : ($grade === '4M' ? 'HC' : ''));

            return [
                'CAT-NT1-4M', $version, 'OAT-TRANSVERSAL', $grade === '3M' ? 'OAG' : ($isParvularia ? 'OA' : 'OAT'),
                $isOffered ? $subject->code : '', $level, $grade, $track,
                'EJE-'.$grade, '', 'Objetivo oficial '.$grade.'. '.$descriptionSuffix, '[]', 'SI', 'p. 1',
            ];
        })->all();
        if ($duplicateExactObjective) {
            $objectives[] = $objectives[2];
        }
        $sourceDefinitions = $this->sourceDefinitions();
        if ($upperSecondaryGeneral) {
            unset($sourceDefinitions['SRC-TP-3M-4M']);
        }
        $sources = collect($sourceDefinitions)->map(fn (array $source, string $key): array => [
            $key,
            $source['scope'],
            $source['name'],
            'MINEDUC',
            $source['document_number'],
            'https://curriculumnacional.mineduc.cl/'.$key,
            hash('sha256', $source['contents']),
            '2026-03-01',
            '',
            $upperSecondaryGeneral && $key === 'SRC-HC-3M-4M' ? 'GENERAL' : $source['track'],
            '',
            '',
        ])->values()->all();
        $objectiveSources = collect($objectives)->map(function (array $objective): array {
            $grade = $objective[6];
            $sourceKey = match (true) {
                in_array($grade, ['NT1', 'NT2'], true) => 'SRC-PARVULARIA',
                preg_match('/^[1-6]B$/', $grade) === 1 => 'SRC-GENERAL-1B-6B',
                in_array($grade, ['7B', '8B', '1M', '2M'], true) => 'SRC-GENERAL-7B-2M',
                $grade === '3M' && $objective[7] === 'TP' => 'SRC-TP-3M-4M',
                default => 'SRC-HC-3M-4M',
            };

            return [
                $objective[2], $objective[3], $objective[4], $objective[5], $objective[6],
                $objective[7], $objective[8], $sourceKey, 'canonical_text', $objective[13],
            ];
        })->all();

        return app(XlsxReportBuilder::class)->build([], [
            [
                'title' => 'Catalogo',
                'headers' => ['catalog_code', 'catalog_name', 'version', 'authority', 'source_url', 'source_sha256', 'effective_from', 'effective_to'],
                'rows' => [['CAT-NT1-4M', 'Currículum nacional NT1–4M', $version, 'MINEDUC', 'https://curriculumnacional.mineduc.cl/', str_repeat('a', 64), '2026-03-01', '']],
            ],
            [
                'title' => 'Objetivos',
                'headers' => ['catalog_code', 'catalog_version', 'code', 'objective_type', 'subject_code', 'level_code', 'grade_code', 'curriculum_track', 'axis_code', 'unit_code', 'description', 'indicators_json', 'active', 'source_page'],
                'rows' => $objectives,
            ],
            [
                'title' => 'Fuentes',
                'headers' => ['source_key', 'source_scope', 'source_name', 'authority', 'document_number', 'source_url', 'source_sha256', 'effective_from', 'effective_to', 'curriculum_track', 'subject_code', 'objective_type'],
                'rows' => $sources,
            ],
            [
                'title' => 'ObjetivoFuentes',
                'headers' => ['objective_code', 'objective_type', 'subject_code', 'level_code', 'grade_code', 'curriculum_track', 'axis_code', 'source_key', 'source_role', 'source_locator'],
                'rows' => $objectiveSources,
            ],
            [
                'title' => 'Vinculos',
                'headers' => ['school_rbd', 'academic_year', 'subject_code', 'catalog_code', 'catalog_version', 'level_code', 'grade_code', 'curriculum_track', 'valid_from', 'valid_to', 'active'],
                'rows' => [[$school->rbd, $year->year, $subject->code, 'CAT-NT1-4M', $version, 'BASICA', '5B', '', '2035-03-01', '2035-12-20', 'SI']],
            ],
            ['title' => 'Referencias', 'headers' => ['tipo', 'valor'], 'rows' => [['fuente', 'Ejemplo sintético de prueba']]],
        ]);
    }

    /** @return array<string, UploadedFile> */
    private function evidenceFiles(): array
    {
        return collect($this->sourceDefinitions())->mapWithKeys(fn (array $source, string $key): array => [
            $key => UploadedFile::fake()->createWithContent($key.'.html', $source['contents']),
        ])->all();
    }

    /** @return array<string, array{scope:string,name:string,document_number:string,track:string,contents:string}> */
    private function sourceDefinitions(): array
    {
        return [
            'SRC-PARVULARIA' => [
                'scope' => 'PARVULARIA_NT1_NT2', 'name' => 'Bases Curriculares Parvularia',
                'document_number' => 'DS-481', 'track' => 'PARVULARIA',
                'contents' => '<!doctype html><html><body>Fuente oficial Parvularia NT1 NT2</body></html>',
            ],
            'SRC-GENERAL-1B-6B' => [
                'scope' => 'GENERAL_1B_6B', 'name' => 'Bases Curriculares 1B a 6B',
                'document_number' => 'DS-439-433', 'track' => 'GENERAL',
                'contents' => '<!doctype html><html><body>Fuente oficial General 1B 6B</body></html>',
            ],
            'SRC-GENERAL-7B-2M' => [
                'scope' => 'GENERAL_7B_2M', 'name' => 'Bases Curriculares 7B a 2M',
                'document_number' => 'DS-614-369', 'track' => 'GENERAL',
                'contents' => '<!doctype html><html><body>Fuente oficial General 7B 2M</body></html>',
            ],
            'SRC-HC-3M-4M' => [
                'scope' => 'HC_3M_4M', 'name' => 'Bases Curriculares HC 3M a 4M',
                'document_number' => 'DS-HC', 'track' => 'HC',
                'contents' => '<!doctype html><html><body>Fuente oficial HC 3M 4M</body></html>',
            ],
            'SRC-TP-3M-4M' => [
                'scope' => 'TP_3M_4M', 'name' => 'Bases Curriculares TP 3M a 4M',
                'document_number' => 'DS-TP', 'track' => 'TP',
                'contents' => '<!doctype html><html><body>Fuente oficial TP 3M 4M</body></html>',
            ],
        ];
    }
}
