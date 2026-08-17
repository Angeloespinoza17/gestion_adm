<?php

namespace Tests\Feature\LibroDigital;

use App\Models\AcademicYear;
use App\Models\LibroDigital\CurriculumCatalog;
use App\Models\LibroDigital\CurriculumCatalogActivation;
use App\Models\LibroDigital\CurriculumImportBatch;
use App\Models\LibroDigital\CurriculumImportEvidence;
use App\Models\LibroDigital\CurriculumSource;
use App\Models\LibroDigital\LearningObjective;
use App\Models\LibroDigital\LearningObjectiveSource;
use App\Models\LibroDigital\NormativeSource;
use App\Models\LibroDigital\RegulatoryProfile;
use App\Models\LibroDigital\School;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Schedule\ScheduleSubject;
use App\Models\User;
use App\Services\LibroDigital\CurriculumObjectiveIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CurriculumExplorerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_explorer_returns_a_read_only_empty_state_when_the_catalog_has_not_been_imported(): void
    {
        [$user, $school, $year] = $this->context();

        $this->actingAs($user)->getJson('/api/libro-digital/v1/curriculum/objectives?'.http_build_query([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
        ]))->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('meta.context.catalog_status', 'not_imported')
            ->assertJsonPath('meta.compliance_blocker.code', 'COMPLIANCE_BLOCKER_CURRICULUM_NOT_IMPORTED');

        $this->assertDatabaseCount('lcd_curriculum_catalogs', 0);
        $this->assertDatabaseCount('lcd_curriculum_import_batches', 0);
    }

    public function test_explorer_rejects_non_scalar_book_identifiers_and_unlinked_academic_years(): void
    {
        [$user, $school, $year] = $this->context();
        $otherYear = AcademicYear::factory()->create([
            'year' => 2040,
            'name' => '2040',
            'starts_at' => '2040-03-01',
            'ends_at' => '2040-12-20',
            'is_active' => true,
            'is_closed' => false,
        ]);

        $this->actingAs($user)->getJson('/api/libro-digital/v1/curriculum/objectives?'.http_build_query([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'book_id' => ['unexpected-array'],
        ]))->assertUnprocessable()->assertJsonValidationErrors('book_id');

        $this->getJson('/api/libro-digital/v1/curriculum/objectives?'.http_build_query([
            'school_id' => $school->id,
            'academic_year_id' => $otherYear->id,
        ]))->assertUnprocessable()
            ->assertJsonPath('code', 'LCD_CURRICULUM_SCHOOL_YEAR_REQUIRED')
            ->assertJsonMissingPath('meta.context');
    }

    public function test_global_explorer_lists_active_and_inactive_objectives_with_filters_facets_and_detail_traceability(): void
    {
        [$user, $school, $year] = $this->context();
        $graph = $this->activatedCatalog($school, $year);

        $response = $this->actingAs($user)->getJson('/api/libro-digital/v1/curriculum/objectives?'.http_build_query([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'status' => 'all',
            'grade_code' => '5B',
            'source' => 'DS-TEST',
            'per_page' => 25,
        ]))->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.summary.total_objectives', 2)
            ->assertJsonPath('meta.summary.filtered_objectives', 2)
            ->assertJsonPath('meta.summary.active_objectives', 1)
            ->assertJsonPath('meta.summary.inactive_objectives', 1)
            ->assertJsonPath('meta.context.catalog_status', 'activated')
            ->assertJsonPath('meta.compliance_blocker', null)
            ->assertJsonPath('data.0.source_page', 'p. 42');
        $this->assertNotEmpty($response->json('meta.facets.subjects'));
        $this->assertNotEmpty($response->json('meta.facets.sources'));

        $this->getJson('/api/libro-digital/v1/curriculum/objectives?'.http_build_query([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'status' => 'inactive',
        ]))->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $graph['inactive']->id)
            ->assertJsonPath('data.0.status', 'inactive');

        $detail = $this->getJson('/api/libro-digital/v1/curriculum/objectives/'.$graph['active']->public_id.'?'.http_build_query([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
        ]))->assertOk()
            ->assertJsonPath('data.id', $graph['active']->id)
            ->assertJsonPath('data.traceability.status', 'verified')
            ->assertJsonPath('data.sources.0.source_key', 'DS-TEST')
            ->assertJsonPath('data.sources.0.evidence.status', 'verified')
            ->assertJsonPath('data.import_state.catalog_status', 'activated');

        $serialized = json_encode($detail->json(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('private_path', $serialized);
        $this->assertStringNotContainsString('storage_metadata', $serialized);
        $this->assertStringNotContainsString('validated_payload', $serialized);
    }

    public function test_global_explorer_rejects_cross_school_context_without_leaking_catalog_state(): void
    {
        [, $schoolA, $yearA] = $this->context();
        [, $schoolB, $yearB] = $this->context('54321-0', 2036);
        $this->activatedCatalog($schoolB, $yearB);
        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->create(['name' => 'Consulta curricular', 'slug' => 'consulta-curricular', 'active' => true]);
        $permissions = collect([
            ['slug' => 'libro_digital.access', 'name' => 'Acceso al libro digital'],
            ['slug' => 'libro_digital.books.view', 'name' => 'Ver libros digitales'],
        ])->map(fn (array $permission) => Permission::query()->firstOrCreate(
            ['slug' => $permission['slug']],
            ['name' => $permission['name'], 'active' => true],
        ));
        $role->permissions()->sync($permissions->pluck('id'));
        $user->roles()->sync([$role->id]);
        $schoolA->users()->attach($user->id, ['active' => true]);

        $this->actingAs($user)->getJson('/api/libro-digital/v1/curriculum/objectives?'.http_build_query([
            'school_id' => $schoolB->id,
            'academic_year_id' => $yearB->id,
        ]))->assertForbidden()
            ->assertJsonMissingPath('meta.context')
            ->assertJsonMissingPath('data.0');

        $this->getJson('/api/libro-digital/v1/curriculum/objectives?'.http_build_query([
            'school_id' => $schoolA->id,
            'academic_year_id' => $yearA->id,
        ]))->assertOk()->assertJsonPath('meta.context.catalog_status', 'not_imported');
    }

    /** @return array{User, School, AcademicYear} */
    private function context(string $rbd = '12345-6', int $yearNumber = 2035): array
    {
        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super Admin', 'active' => true],
        );
        $user->roles()->syncWithoutDetaching([$role->id]);
        $school = School::query()->create([
            'rbd' => $rbd,
            'name' => 'Escuela '.$rbd,
            'timezone' => 'America/Santiago',
            'active' => true,
        ]);
        $year = AcademicYear::factory()->create([
            'year' => $yearNumber,
            'name' => (string) $yearNumber,
            'starts_at' => $yearNumber.'-03-01',
            'ends_at' => $yearNumber.'-12-20',
            'is_active' => true,
            'is_closed' => false,
        ]);
        $profile = RegulatoryProfile::query()->firstOrCreate(
            ['code' => 'CL-CURRICULUM', 'version' => '1'],
            [
                'name' => 'Perfil curricular',
                'effective_from' => '2030-01-01',
                'retention_years' => 6,
                'rules_snapshot' => [],
                'active' => true,
            ],
        );
        $school->academicYears()->attach($year->id, [
            'regulatory_profile_id' => $profile->id,
            'rbd_snapshot' => $school->rbd,
            'year_snapshot' => $year->year,
            'timezone_snapshot' => $school->timezone,
            'active' => true,
        ]);

        return [$user, $school, $year];
    }

    /** @return array{active:LearningObjective,inactive:LearningObjective} */
    private function activatedCatalog(School $school, AcademicYear $year): array
    {
        $subject = ScheduleSubject::query()->firstOrCreate(
            ['code' => 'LEN'],
            ['name' => 'Lenguaje', 'area' => 'Lenguaje', 'color' => '#405189', 'active' => true],
        );
        $workbookHash = hash('sha256', 'workbook-'.$school->id.'-'.$year->id);
        $catalogHash = hash('sha256', 'catalog-'.$school->id.'-'.$year->id);
        $officialHash = hash('sha256', 'official-'.$school->id.'-'.$year->id);
        $workbookSource = NormativeSource::query()->create([
            'title' => 'Workbook normalizado',
            'sha256' => $workbookHash,
            'private_path' => 'private/workbook-'.$school->id.'.enc',
            'status' => 'verified_metadata_only',
        ]);
        $catalog = CurriculumCatalog::query()->create([
            'normative_source_id' => $workbookSource->id,
            'code' => 'CAT-'.$school->id,
            'name' => 'Currículum NT1–4M',
            'version' => '2026.1',
            'authority' => 'MINEDUC',
            'source_url' => 'https://www.curriculumnacional.cl/',
            'source_hash' => $catalogHash,
            'active' => true,
        ]);
        $batch = CurriculumImportBatch::query()->create([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'curriculum_catalog_id' => $catalog->id,
            'normative_source_id' => $workbookSource->id,
            'idempotency_key' => hash('sha256', 'batch-'.$school->id.'-'.$year->id),
            'status' => CurriculumImportBatch::STATUS_ACTIVATED,
            'catalog_code' => $catalog->code,
            'catalog_version' => $catalog->version,
            'original_name' => 'curriculum.xlsx',
            'private_path' => 'private/curriculum-'.$school->id.'.xlsx.enc',
            'source_hash' => $workbookHash,
            'manifest_hash' => hash('sha256', 'manifest-'.$school->id.'-'.$year->id),
            'objective_count' => 2,
            'requested_at' => now('UTC'),
            'completed_at' => now('UTC'),
        ]);
        CurriculumCatalogActivation::query()->create([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'curriculum_catalog_id' => $catalog->id,
            'import_batch_id' => $batch->id,
            'activation_version' => 1,
            'idempotency_key' => hash('sha256', 'activation-'.$school->id.'-'.$year->id),
            'status' => CurriculumCatalogActivation::STATUS_ACTIVATED,
            'scope_snapshot' => ['complete_nt1_4m' => true],
            'requested_at' => now('UTC'),
            'activated_at' => now('UTC'),
        ]);
        $normativeSource = NormativeSource::query()->create([
            'title' => 'Bases curriculares oficiales',
            'authority' => 'MINEDUC',
            'document_number' => 'DS-TEST',
            'source_url' => 'https://www.curriculumnacional.cl/bases.pdf',
            'sha256' => $officialHash,
            'private_path' => 'private/source-'.$school->id.'.pdf.enc',
            'status' => 'verified',
            'metadata' => ['private_path' => 'must-never-leak'],
        ]);
        $source = CurriculumSource::query()->create([
            'curriculum_catalog_id' => $catalog->id,
            'normative_source_id' => $normativeSource->id,
            'source_key' => 'DS-TEST',
            'source_scope' => 'GENERAL_1B_6B',
            'source_name' => 'Bases curriculares oficiales',
            'authority' => 'MINEDUC',
            'document_number' => 'DS-TEST',
            'source_url' => 'https://www.curriculumnacional.cl/bases.pdf',
            'declared_sha256' => $officialHash,
            'verified_sha256' => $officialHash,
            'status' => 'verified',
            'metadata' => ['private_path' => 'must-never-leak'],
        ]);
        CurriculumImportEvidence::query()->create([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'import_batch_id' => $batch->id,
            'curriculum_catalog_id' => $catalog->id,
            'evidence_kind' => 'official_source_document',
            'status' => CurriculumImportEvidence::STATUS_VERIFIED,
            'title' => 'Bases curriculares oficiales',
            'detected_mime_type' => 'application/pdf',
            'private_path' => 'private/source-'.$school->id.'.pdf.enc',
            'sha256' => $officialHash,
            'metadata' => ['source_key' => 'DS-TEST', 'private_path' => 'must-never-leak'],
            'captured_at' => now('UTC'),
            'verified_at' => now('UTC'),
        ]);

        $active = $this->objective($catalog, $subject, 'OA-5B-01', 'Comprender textos aplicando estrategias.', true, 'p. 42');
        $inactive = $this->objective($catalog, $subject, 'OA-5B-02', 'Objetivo conservado como referencia.', false, 'p. 43');
        foreach ([$active, $inactive] as $objective) {
            LearningObjectiveSource::query()->create([
                'learning_objective_id' => $objective->id,
                'curriculum_source_id' => $source->id,
                'source_role' => 'canonical_text',
                'source_locator' => $objective->source_page,
                'relationship_hash' => hash('sha256', 'relationship-'.$objective->id),
                'source_snapshot' => [
                    'source_key' => 'DS-TEST',
                    'private_path' => 'must-never-leak',
                ],
            ]);
        }

        return compact('active', 'inactive');
    }

    private function objective(
        CurriculumCatalog $catalog,
        ScheduleSubject $subject,
        string $code,
        string $description,
        bool $active,
        string $sourcePage,
    ): LearningObjective {
        return LearningObjective::query()->create([
            'public_id' => (string) Str::ulid(),
            'curriculum_catalog_id' => $catalog->id,
            'schedule_subject_id' => $subject->id,
            'level_code' => 'BASICA',
            'grade_code' => '5B',
            'curriculum_track' => null,
            'axis_code' => 'LECTURA',
            'objective_type' => 'OA',
            'code' => $code,
            'objective_key' => CurriculumObjectiveIdentity::key([
                'code' => $code,
                'objective_type' => 'OA',
                'subject_code' => $subject->code,
                'level_code' => 'BASICA',
                'grade_code' => '5B',
                'curriculum_track' => null,
                'axis_code' => 'LECTURA',
            ]),
            'description' => $description,
            'source_page' => $sourcePage,
            'active' => $active,
        ]);
    }
}
