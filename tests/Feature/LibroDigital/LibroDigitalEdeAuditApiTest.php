<?php

namespace Tests\Feature\LibroDigital;

use App\Http\Controllers\LibroDigital\AuditController;
use App\Http\Controllers\LibroDigital\EdeController;
use App\Models\AcademicYear;
use App\Models\LibroDigital\Book;
use App\Models\LibroDigital\EdeExport;
use App\Models\LibroDigital\EdeExportFile;
use App\Models\LibroDigital\EdeMapping;
use App\Models\LibroDigital\EdeValidationResult;
use App\Models\LibroDigital\EdeValidationRun;
use App\Models\LibroDigital\EdeVersion;
use App\Models\LibroDigital\FeatureFlag;
use App\Models\LibroDigital\RegulatoryProfile;
use App\Models\LibroDigital\School;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\LibroDigital\AuditEventWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class LibroDigitalEdeAuditApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->prefix('api/_test/libro-digital')->group(function (): void {
            Route::get('/ede/versions', [EdeController::class, 'versions']);
            Route::get('/ede/mappings', [EdeController::class, 'mappings']);
            Route::post('/ede/import-standard', [EdeController::class, 'importStandard']);
            Route::get('/ede/exports', [EdeController::class, 'index']);
            Route::post('/ede/exports', [EdeController::class, 'store']);
            Route::get('/ede/exports/{edeExport}', [EdeController::class, 'show']);
            Route::post('/ede/exports/{edeExport}/validate', [EdeController::class, 'validateExport']);
            Route::get('/ede/exports/{edeExport}/report', [EdeController::class, 'report']);
            Route::get('/ede/exports/{edeExport}/download', [EdeController::class, 'download']);
            Route::get('/audit', [AuditController::class, 'index']);
            Route::get('/audit/{entityType}/{entityId}', [AuditController::class, 'entity']);
            Route::post('/audit/verify', [AuditController::class, 'verify']);
        });
    }

    public function test_ede_export_request_fails_closed_and_does_not_create_a_candidate(): void
    {
        [$user, $school, , $book, $version] = $this->context([
            'libro_digital.ede.export',
        ]);

        $this->actingAs($user)->postJson('/api/_test/libro-digital/ede/exports', [
            'school_id' => $school->id,
            'book_id' => $book->id,
            'ede_version_id' => $version->id,
        ])->assertConflict()->assertJsonPath('code', 'LCD_EDE_COMPLIANCE_BLOCKED');

        $this->assertDatabaseCount('lcd_ede_exports', 0);
    }

    public function test_standard_import_archives_real_artifacts_encrypted_and_is_idempotent(): void
    {
        Storage::fake('local');
        config(['libro_digital.storage.disk' => 'local']);
        [$user, $school] = $this->context(['libro_digital.ede.manage']);
        $mapping = json_encode([[
            'code' => 'BOOK-CODE',
            'source_entity' => 'book',
            'source_field' => 'code',
            'target_record_type' => 'Book',
            'target_field' => 'Code',
            'data_type' => 'string',
            'transform_definition' => ['type' => 'direct'],
        ]], JSON_THROW_ON_ERROR);
        $payload = static fn (): array => [
            'school_id' => $school->id,
            'version' => '7.1-test',
            'code' => 'CEDS-TEST',
            'authority' => 'MINEDUC (prueba sintética)',
            'source_url' => 'https://example.test/standard',
            'effective_from' => '2035-03-01',
            'source' => UploadedFile::fake()->createWithContent('source.xlsx', 'synthetic-standard-source'),
            'schema' => UploadedFile::fake()->createWithContent('schema.xlsx', 'synthetic-standard-schema'),
            'mappings' => UploadedFile::fake()->createWithContent('mappings.json', $mapping),
        ];

        $first = $this->actingAs($user)->post('/api/_test/libro-digital/ede/import-standard', $payload());
        $first->assertCreated()->assertJsonPath('data.created', true)
            ->assertJsonPath('data.status', 'imported')
            ->assertJsonPath('data.mapping_count', 1)
            ->assertJsonPath('data.activation_required', true);

        $version = EdeVersion::query()->where('code', 'CEDS-TEST')->where('version', '7.1-test')->firstOrFail();
        $this->assertDatabaseHas('lcd_ede_mappings', ['ede_version_id' => $version->id, 'code' => 'BOOK-CODE']);
        $sourcePath = $version->metadata['archive_path'].'/source/source.bin.enc';
        Storage::disk('local')->assertExists($sourcePath);
        $this->assertStringNotContainsString('synthetic-standard-source', Storage::disk('local')->get($sourcePath));

        $this->actingAs($user)->post('/api/_test/libro-digital/ede/import-standard', $payload())
            ->assertOk()->assertJsonPath('data.created', false);
        $this->assertSame(1, EdeVersion::query()->where('code', 'CEDS-TEST')->where('version', '7.1-test')->count());
        $this->assertSame(1, $version->mappings()->count());
    }

    public function test_validation_rechecks_compliance_before_invoking_the_runner(): void
    {
        [$user, $school, , $book, $version] = $this->context([
            'libro_digital.ede.validate',
        ]);
        $export = $this->export($school, $book, $version, $user, 'generated', 'not_run');

        $this->actingAs($user)->postJson('/api/_test/libro-digital/ede/exports/'.$export->public_id.'/validate', [
            'school_id' => $school->id,
            'operation' => 'check',
            'lock_version' => $export->lock_version,
        ])->assertConflict()->assertJsonPath('code', 'LCD_EDE_COMPLIANCE_BLOCKED');

        $this->assertDatabaseCount('lcd_ede_validation_runs', 0);
    }

    public function test_download_never_exposes_the_internal_encrypted_projection(): void
    {
        Storage::fake('local');
        config(['libro_digital.storage.disk' => 'local']);
        [$user, $school, , $book, $version] = $this->context([
            'libro_digital.ede.download',
        ]);
        $this->readyEdeForSyntheticDownload($school, $version);
        $export = $this->export($school, $book, $version, $user, 'released', 'passed');
        $export->forceFill(['validated_at' => now('UTC'), 'released_at' => now('UTC')])->save();
        $run = EdeValidationRun::query()->create([
            'ede_export_id' => $export->id, 'validator_name' => 'EDE official container',
            'validator_image_digest' => 'sha256:'.str_repeat('a', 64), 'status' => 'completed',
            'started_at' => now('UTC')->subMinute(), 'completed_at' => now('UTC'),
            'exit_code' => 0, 'report_hash' => str_repeat('b', 64), 'run_by' => $user->id,
        ]);
        EdeValidationResult::query()->create([
            'validation_run_id' => $run->id, 'ede_export_id' => $export->id,
            'severity' => 'info', 'code' => 'EDE_CHECK_PASSED', 'message' => 'Prueba sintética.',
        ]);
        $encrypted = 'ciphertext-internal-only';
        Storage::disk('local')->put('private/libro-digital/internal.enc', $encrypted);
        EdeExportFile::query()->create([
            'ede_export_id' => $export->id, 'file_type' => 'projection_staging',
            'file_name' => 'projection.json.enc', 'private_path' => 'private/libro-digital/internal.enc',
            'mime_type' => 'application/octet-stream', 'size_bytes' => strlen($encrypted),
            'sha256' => hash('sha256', $encrypted), 'encrypted' => true,
        ]);

        $this->actingAs($user)->getJson('/api/_test/libro-digital/ede/exports/'.$export->public_id.'/download?school_id='.$school->id)
            ->assertConflict()->assertJsonPath('code', 'LCD_EDE_VALIDATED_PACKAGE_MISSING');
    }

    public function test_released_package_is_hash_verified_and_streamed_from_private_storage(): void
    {
        Storage::fake('local');
        config(['libro_digital.storage.disk' => 'local']);
        [$user, $school, , $book, $version] = $this->context(['libro_digital.ede.download']);
        $this->readyEdeForSyntheticDownload($school, $version);
        $export = $this->export($school, $book, $version, $user, 'released', 'passed');
        $export->forceFill(['validated_at' => now('UTC'), 'released_at' => now('UTC')])->save();
        $run = EdeValidationRun::query()->create([
            'ede_export_id' => $export->id, 'validator_name' => 'Validador sintético de prueba',
            'validator_image_digest' => 'sha256:'.str_repeat('a', 64), 'status' => 'completed',
            'started_at' => now('UTC')->subMinute(), 'completed_at' => now('UTC'),
            'exit_code' => 0, 'report_hash' => str_repeat('b', 64), 'run_by' => $user->id,
        ]);
        EdeValidationResult::query()->create([
            'validation_run_id' => $run->id, 'ede_export_id' => $export->id,
            'severity' => 'info', 'code' => 'EDE_CHECK_PASSED', 'message' => 'Resultado sintético de prueba.',
        ]);
        $contents = 'synthetic-released-ede-package';
        $path = 'private/libro-digital/released/test-package.zip';
        Storage::disk('local')->put($path, $contents);
        EdeExportFile::query()->create([
            'ede_export_id' => $export->id, 'file_type' => 'validated_package',
            'file_name' => 'test-package.zip', 'private_path' => $path,
            'mime_type' => 'application/zip', 'size_bytes' => strlen($contents),
            'sha256' => hash('sha256', $contents), 'encrypted' => false,
        ]);

        $response = $this->actingAs($user)->get('/api/_test/libro-digital/ede/exports/'.$export->public_id.'/download?school_id='.$school->id);
        $response->assertOk()->assertHeader('content-type', 'application/zip');
        ob_start();
        $response->baseResponse->sendContent();
        $streamed = (string) ob_get_clean();
        $this->assertSame($contents, $streamed);
    }

    public function test_cross_school_export_is_not_readable_by_a_scoped_user(): void
    {
        [$user, $school, , $book, $version] = $this->context(['libro_digital.ede.export']);
        $otherSchool = School::query()->create(['rbd' => '99999-9', 'name' => 'Otra Escuela', 'timezone' => 'America/Santiago', 'active' => true]);
        $otherBook = $book->replicate(['public_id', 'school_id', 'code']);
        $otherBook->public_id = (string) Str::ulid();
        $otherBook->school_id = $otherSchool->id;
        $otherBook->code = 'BOOK-OTHER';
        $otherBook->save();
        $export = $this->export($otherSchool, $otherBook, $version, $user, 'generated', 'not_run');

        $this->actingAs($user)->getJson('/api/_test/libro-digital/ede/exports/'.$export->public_id.'?school_id='.$school->id)
            ->assertForbidden();
    }

    public function test_audit_list_redacts_sensitive_ciphertexts_and_chain_verification_uses_the_whole_school(): void
    {
        [$user, $school] = $this->context([
            'libro_digital.audit.view', 'libro_digital.audit.verify',
        ]);
        app(AuditEventWriter::class)->write(
            'lcd.synthetic.created', 'create', School::class, $school->id,
            actor: $user, schoolId: $school->id,
            before: ['secret' => 'before'], after: ['secret' => 'after'],
        );

        $list = $this->actingAs($user)->getJson('/api/_test/libro-digital/audit?school_id='.$school->id);
        $list->assertOk()->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.encrypted_diff')
            ->assertJsonMissingPath('data.0.ip_address_encrypted');
        $this->assertStringNotContainsString('"secret"', (string) $list->getContent());

        $this->actingAs($user)->postJson('/api/_test/libro-digital/audit/verify', [
            'school_id' => $school->id, 'from' => now()->addDay()->format('Y-m-d'),
        ])->assertOk()->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.checked', 1)
            ->assertJsonPath('data.scope', 'entire_school_chain')
            ->assertJsonPath('data.filters_ignored_for_integrity.0', 'from');
    }

    public function test_audit_verify_requires_the_dedicated_permission(): void
    {
        [$user, $school] = $this->context(['libro_digital.audit.view']);

        $this->actingAs($user)->postJson('/api/_test/libro-digital/audit/verify', [
            'school_id' => $school->id,
        ])->assertForbidden();
    }

    public function test_audit_entity_view_is_allowlisted_and_reports_global_chain_integrity(): void
    {
        [$user, $school, , $book] = $this->context(['libro_digital.audit.view']);
        app(AuditEventWriter::class)->write(
            'lcd.book.synthetic', 'inspect', $book,
            actor: $user, schoolId: $school->id, academicYearId: $book->academic_year_id,
        );

        $this->actingAs($user)->getJson('/api/_test/libro-digital/audit/book/'.$book->id.'?school_id='.$school->id)
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.entity.type', 'book')
            ->assertJsonPath('meta.entity.id', $book->id)
            ->assertJsonPath('meta.school_chain_valid', true);
        $this->actingAs($user)->getJson('/api/_test/libro-digital/audit/not_allowed/'.$book->id.'?school_id='.$school->id)
            ->assertUnprocessable();
    }

    /** @param list<string> $permissions */
    private function context(array $permissions): array
    {
        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->create(['name' => 'Rol LCD', 'slug' => 'rol-lcd-'.Str::lower(Str::random(6)), 'active' => true]);
        $permissionIds = collect(['libro_digital.access', ...$permissions])->unique()->map(function (string $slug): int {
            return Permission::query()->firstOrCreate(['slug' => $slug], ['name' => $slug, 'active' => true])->id;
        });
        $role->permissions()->sync($permissionIds);
        $user->roles()->sync([$role->id]);

        $school = School::query()->create(['rbd' => '12345-6', 'name' => 'Escuela Prueba', 'timezone' => 'America/Santiago', 'active' => true]);
        $school->users()->attach($user->id, ['active' => true]);
        $year = AcademicYear::factory()->create(['year' => 2035, 'name' => '2035', 'starts_at' => '2035-03-01', 'ends_at' => '2035-12-20', 'is_active' => true]);
        $profile = RegulatoryProfile::query()->create([
            'code' => 'CL-LCD', 'name' => 'Perfil normativo', 'version' => '1.0',
            'effective_from' => '2030-01-01', 'retention_years' => 6,
            'rules_snapshot' => [], 'active' => true,
        ]);
        $book = Book::query()->create([
            'school_id' => $school->id, 'academic_year_id' => $year->id,
            'regulatory_profile_id' => $profile->id, 'code' => 'BOOK-001',
            'rbd_snapshot' => $school->rbd, 'year_snapshot' => 2035,
            'course_label' => '5° Básico A', 'status' => 'closed',
        ]);
        $version = EdeVersion::query()->create([
            'code' => 'EDE-LCD', 'version' => 'unverified-test',
            'source_hash' => str_repeat('1', 64), 'schema_hash' => str_repeat('2', 64),
            'status' => 'active',
        ]);

        return [$user, $school, $year, $book, $version, $profile];
    }

    private function export(School $school, Book $book, EdeVersion $version, User $user, string $status, string $validatorStatus): EdeExport
    {
        return EdeExport::query()->create([
            'school_id' => $school->id, 'academic_year_id' => $book->academic_year_id,
            'book_id' => $book->id, 'regulatory_profile_id' => $book->regulatory_profile_id,
            'ede_version_id' => $version->id, 'export_type' => 'full',
            'scope_snapshot' => ['book_public_id' => $book->public_id], 'status' => $status,
            'deduplication_key' => hash('sha256', Str::random()), 'validator_status' => $validatorStatus,
            'requested_by' => $user->id, 'requested_at' => now('UTC'),
        ]);
    }

    private function readyEdeForSyntheticDownload(School $school, EdeVersion $version): void
    {
        config([
            'libro_digital.ede.enabled' => true,
            'libro_digital.ede.validator_digest' => 'sha256:'.str_repeat('a', 64),
            'libro_digital.ede.command_contract_verified' => true,
        ]);
        foreach (['lcd_ede_export_enabled', 'lcd_fiscalization_download_enabled'] as $code) {
            FeatureFlag::query()->create([
                'school_id' => $school->id, 'scope_key' => 'school:'.$school->id,
                'code' => $code, 'enabled' => true,
            ]);
        }
        EdeMapping::query()->create([
            'ede_version_id' => $version->id, 'code' => 'TEST-MAPPING',
            'source_entity' => 'books', 'source_field' => 'code',
            'target_record_type' => 'Book', 'target_field' => 'Code',
            'data_type' => 'string', 'mapping_hash' => str_repeat('3', 64), 'active' => true,
        ]);
    }
}
