<?php

namespace Tests\Feature\LibroDigital;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\LibroDigital\Book;
use App\Models\LibroDigital\CurriculumCatalog;
use App\Models\LibroDigital\CurriculumCatalogActivation;
use App\Models\LibroDigital\CurriculumImportBatch;
use App\Models\LibroDigital\CurriculumImportEvidence;
use App\Models\LibroDigital\CurriculumSource;
use App\Models\LibroDigital\FeatureFlag;
use App\Models\LibroDigital\LearningObjective;
use App\Models\LibroDigital\LearningObjectiveSource;
use App\Models\LibroDigital\NormativeSource;
use App\Models\LibroDigital\RegulatoryProfile;
use App\Models\LibroDigital\School;
use App\Models\LibroDigital\SubjectCurriculumLink;
use App\Models\LibroDigital\TeachingGroup;
use App\Models\Role;
use App\Models\Schedule\ScheduleSubject;
use App\Models\User;
use App\Services\LibroDigital\CompliancePreflightService;
use App\Services\LibroDigital\CurriculumImportValidator;
use App\Services\LibroDigital\CurriculumObjectiveScopeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use Tests\TestCase;

class CurriculumTraceabilityGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_preflight_fails_closed_when_multisource_traceability_is_corrupted(): void
    {
        $graph = $this->graph();
        $service = app(CompliancePreflightService::class);

        $this->assertTrue($this->curriculumReady($service, $graph['school']->id));

        $graph['canonical']->forceFill(['source_role' => 'legal_basis'])->save();
        $this->assertFalse($this->curriculumReady($service, $graph['school']->id));
        $graph['canonical']->forceFill(['source_role' => 'canonical_text'])->save();

        $graph['source']->forceFill(['status' => 'pending_verification'])->save();
        $this->assertFalse($this->curriculumReady($service, $graph['school']->id));
        $graph['source']->forceFill(['status' => 'verified'])->save();

        $graph['source']->forceFill(['verified_sha256' => str_repeat('c', 64)])->save();
        $this->assertFalse($this->curriculumReady($service, $graph['school']->id));
        $graph['source']->forceFill(['verified_sha256' => $graph['official_hash']])->save();

        $graph['normative_source']->forceFill(['private_path' => null])->save();
        $this->assertFalse($this->curriculumReady($service, $graph['school']->id));
        $graph['normative_source']->forceFill(['private_path' => $graph['official_path']])->save();

        $graph['official_evidence']->forceFill([
            'metadata' => [
                'source_key' => 'OTRA-FUENTE',
                'hash_scope' => 'official_source_bytes_verified',
            ],
        ])->save();
        $this->assertFalse($this->curriculumReady($service, $graph['school']->id));
        $graph['official_evidence']->forceFill([
            'metadata' => [
                'source_key' => $graph['source']->source_key,
                'hash_scope' => 'official_source_bytes_verified',
            ],
        ])->save();

        Storage::disk('local')->put($graph['official_path'], Crypt::encryptString('bytes alterados'));
        $this->assertFalse($this->curriculumReady($service, $graph['school']->id));
    }

    public function test_objective_scope_requires_exactly_one_verified_canonical_source(): void
    {
        $graph = $this->graph();
        $service = app(CurriculumObjectiveScopeService::class);
        $book = $graph['book'];

        $service->assertAllowed($book, $graph['subject']->id, [$graph['scope_objective']->id]);
        $this->assertTrue(true);

        $graph['source']->forceFill(['verified_sha256' => str_repeat('c', 64)])->save();
        $this->assertScopeRejected($service, $book, $graph['subject']->id, $graph['scope_objective']->id);
        $graph['source']->forceFill(['verified_sha256' => $graph['official_hash']])->save();

        LearningObjectiveSource::query()->create([
            'learning_objective_id' => $graph['scope_objective']->id,
            'curriculum_source_id' => $graph['source']->id,
            'source_role' => 'canonical_text',
            'source_locator' => 'p. duplicada',
            'relationship_hash' => str_repeat('d', 64),
            'source_snapshot' => ['source_key' => $graph['source']->source_key],
        ]);
        $this->assertScopeRejected($service, $book, $graph['subject']->id, $graph['scope_objective']->id);
    }

    public function test_objective_scope_rejects_another_grade_even_when_the_subject_has_both_links(): void
    {
        $graph = $this->graph();
        $service = app(CurriculumObjectiveScopeService::class);

        $service->assertAllowed(
            $graph['book'],
            $graph['subject']->id,
            [$graph['scope_objective']->id],
        );
        $this->assertScopeRejected(
            $service,
            $graph['book'],
            $graph['subject']->id,
            $graph['other_grade_objective']->id,
        );

        $this->actingAs($graph['user'])->getJson('/api/libro-digital/v1/curriculum/objectives?'.http_build_query([
            'school_id' => $graph['school']->id,
            'academic_year_id' => $graph['year']->id,
            'book_id' => $graph['book']->public_id,
            'schedule_subject_id' => $graph['subject']->id,
            'per_page' => 100,
        ]))->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $graph['scope_objective']->id)
            ->assertJsonPath('data.0.grade_code', '5B')
            ->assertJsonMissing(['id' => $graph['other_grade_objective']->id]);
    }

    /** @return array<string, mixed> */
    private function graph(): array
    {
        Storage::fake('local');
        $school = School::query()->create([
            'rbd' => '98765-4',
            'name' => 'Escuela de prueba curricular',
            'timezone' => 'America/Santiago',
            'active' => true,
        ]);
        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super Admin', 'active' => true],
        );
        $user->roles()->syncWithoutDetaching([$role->id]);
        FeatureFlag::query()->create([
            'school_id' => $school->id,
            'scope_key' => 'school:'.$school->id,
            'code' => 'lcd_enabled',
            'enabled' => true,
        ]);
        $year = AcademicYear::factory()->create([
            'name' => '2035',
            'year' => 2035,
            'starts_at' => '2035-03-01',
            'ends_at' => '2035-12-20',
            'is_active' => true,
            'is_closed' => false,
        ]);
        $subject = ScheduleSubject::query()->create([
            'name' => 'Lenguaje',
            'code' => 'LEN',
            'area' => 'Lenguaje',
            'active' => true,
        ]);
        $level = EducationLevel::query()->firstOrCreate(
            ['name' => '5° básico'],
            ['order' => 550, 'type' => 'basica'],
        );
        $course = CourseSection::factory()->create([
            'academic_year_id' => $year->id,
            'education_level_id' => $level->id,
            'display_name' => '5° Básico A',
            'section_name' => 'A',
            'active' => true,
        ]);
        $profile = RegulatoryProfile::query()->create([
            'code' => 'CL-TRACEABILITY',
            'name' => 'Perfil trazabilidad curricular',
            'version' => '1',
            'effective_from' => '2030-01-01',
            'retention_years' => 6,
            'rules_snapshot' => [],
            'active' => true,
        ]);
        $school->academicYears()->attach($year->id, [
            'regulatory_profile_id' => $profile->id,
            'rbd_snapshot' => $school->rbd,
            'year_snapshot' => $year->year,
            'timezone_snapshot' => $school->timezone,
            'active' => true,
        ]);
        $book = Book::query()->create([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'regulatory_profile_id' => $profile->id,
            'course_section_id' => $course->id,
            'code' => 'LCD-TRACE-5B-LEN',
            'rbd_snapshot' => $school->rbd,
            'year_snapshot' => $year->year,
            'level_code' => 'basica',
            'grade_code' => $level->name,
            'course_label' => $course->display_name,
            'modality_code' => 'regular',
            'status' => 'draft',
            'revision' => 1,
            'lock_version' => 1,
        ]);
        TeachingGroup::query()->create([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'book_id' => $book->id,
            'course_section_id' => $course->id,
            'schedule_subject_id' => $subject->id,
            'code' => 'LCD-TRACE-5B-LEN-G1',
            'name' => '5° Básico A · Lenguaje',
            'course_snapshot' => $course->display_name,
            'subject_snapshot' => $subject->name,
            'valid_from' => '2035-03-01',
            'valid_to' => '2035-12-20',
            'status' => 'active',
        ]);
        $workbookContents = 'workbook curricular normalizado de prueba';
        $workbookHash = hash('sha256', $workbookContents);
        $catalogPayloadHash = hash('sha256', 'corpus curricular portable de prueba');
        $workbookPath = 'private/libro-digital/curriculum/workbook.xlsx.enc';
        Storage::disk('local')->put($workbookPath, Crypt::encryptString($workbookContents));
        $officialContents = 'bytes oficiales de prueba';
        $officialHash = hash('sha256', $officialContents);
        $officialPath = 'private/libro-digital/curriculum/source.pdf.enc';
        Storage::disk('local')->put($officialPath, Crypt::encryptString($officialContents));
        $workbookSource = NormativeSource::query()->create([
            'title' => 'Workbook normalizado',
            'sha256' => $workbookHash,
            'private_path' => $workbookPath,
            'status' => 'verified_metadata_only',
        ]);
        $catalog = CurriculumCatalog::query()->create([
            'normative_source_id' => $workbookSource->id,
            'code' => 'CAT-NT1-4M',
            'name' => 'Currículum nacional NT1–4M',
            'version' => '2026.1',
            'source_hash' => $catalogPayloadHash,
            'active' => true,
        ]);
        $batch = CurriculumImportBatch::query()->create([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'curriculum_catalog_id' => $catalog->id,
            'normative_source_id' => $workbookSource->id,
            'idempotency_key' => hash('sha256', 'traceability-batch'),
            'status' => CurriculumImportBatch::STATUS_ACTIVATED,
            'catalog_code' => $catalog->code,
            'catalog_version' => $catalog->version,
            'original_name' => 'curriculum.xlsx',
            'disk' => 'local',
            'private_path' => $workbookPath,
            'source_hash' => $workbookHash,
            'manifest' => [
                'coverage' => [
                    'complete_nt1_4m' => true,
                    'missing_grade_codes' => [],
                ],
                'catalog_payload_hash' => $catalogPayloadHash,
            ],
            'manifest_hash' => hash('sha256', 'traceability-manifest'),
            'requested_at' => now('UTC'),
        ]);
        CurriculumImportEvidence::query()->create([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'import_batch_id' => $batch->id,
            'curriculum_catalog_id' => $catalog->id,
            'evidence_kind' => 'normalized_curriculum_workbook',
            'status' => CurriculumImportEvidence::STATUS_VERIFIED,
            'title' => 'Workbook normalizado',
            'disk' => 'local',
            'private_path' => $workbookPath,
            'sha256' => $workbookHash,
            'captured_at' => now('UTC'),
        ]);
        CurriculumCatalogActivation::query()->create([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'curriculum_catalog_id' => $catalog->id,
            'import_batch_id' => $batch->id,
            'activation_version' => 1,
            'idempotency_key' => hash('sha256', 'traceability-activation'),
            'status' => CurriculumCatalogActivation::STATUS_ACTIVATED,
            'scope_snapshot' => ['complete_nt1_4m' => true],
            'requested_at' => now('UTC'),
        ]);
        $normativeSource = NormativeSource::query()->create([
            'title' => 'Bases curriculares oficiales',
            'sha256' => $officialHash,
            'private_path' => $officialPath,
            'status' => 'verified',
        ]);
        $source = CurriculumSource::query()->create([
            'curriculum_catalog_id' => $catalog->id,
            'normative_source_id' => $normativeSource->id,
            'source_key' => 'GENERAL-OFICIAL',
            'source_scope' => 'GENERAL_1B_6B',
            'source_name' => 'Bases curriculares oficiales',
            'authority' => 'MINEDUC',
            'document_number' => 'BC-2026',
            'source_url' => 'https://example.test/bases.pdf',
            'declared_sha256' => $officialHash,
            'verified_sha256' => $officialHash,
            'status' => 'verified',
        ]);
        $officialEvidence = CurriculumImportEvidence::query()->create([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'import_batch_id' => $batch->id,
            'curriculum_catalog_id' => $catalog->id,
            'evidence_kind' => 'official_source_document',
            'status' => CurriculumImportEvidence::STATUS_VERIFIED,
            'title' => 'Bases curriculares oficiales',
            'disk' => 'local',
            'private_path' => $officialPath,
            'sha256' => $officialHash,
            'captured_at' => now('UTC'),
            'metadata' => [
                'source_key' => $source->source_key,
                'hash_scope' => 'official_source_bytes_verified',
            ],
        ]);

        $scopeObjective = null;
        $otherGradeObjective = null;
        foreach (CurriculumImportValidator::GRADE_CODES as $grade) {
            $objective = LearningObjective::query()->create([
                'curriculum_catalog_id' => $catalog->id,
                'schedule_subject_id' => in_array($grade, ['5B', '6B'], true) ? $subject->id : null,
                'level_code' => in_array($grade, ['NT1', 'NT2'], true) ? 'PARVULARIA' : (str_ends_with($grade, 'B') ? 'BASICA' : 'MEDIA'),
                'grade_code' => $grade,
                'objective_type' => 'OA',
                'code' => 'OA-'.$grade,
                'objective_key' => hash('sha256', 'objective-'.$grade),
                'description' => 'Objetivo '.$grade,
                'active' => true,
                'source_page' => 'p. '.$grade,
                'source_row_hash' => hash('sha256', 'row-'.$grade),
            ]);
            $relationship = LearningObjectiveSource::query()->create([
                'learning_objective_id' => $objective->id,
                'curriculum_source_id' => $source->id,
                'source_role' => 'canonical_text',
                'source_locator' => 'p. '.$grade,
                'relationship_hash' => hash('sha256', 'relationship-'.$grade),
                'source_snapshot' => ['source_key' => $source->source_key],
            ]);
            if ($grade === '5B') {
                $scopeObjective = $objective;
                $canonical = $relationship;
            } elseif ($grade === '6B') {
                $otherGradeObjective = $objective;
            }
        }
        foreach (['5B', '6B'] as $linkedGrade) {
            SubjectCurriculumLink::query()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'schedule_subject_id' => $subject->id,
                'curriculum_catalog_id' => $catalog->id,
                'scope_key' => 'BASICA|'.$linkedGrade.'|GENERAL',
                'level_code' => 'BASICA',
                'grade_code' => $linkedGrade,
                'curriculum_track' => null,
                'valid_from' => null,
                'valid_to' => null,
                'active' => true,
            ]);
        }

        return compact(
            'school',
            'year',
            'subject',
            'catalog',
            'batch',
            'source',
            'normativeSource',
            'officialEvidence',
            'scopeObjective',
            'otherGradeObjective',
            'canonical',
            'book',
            'user',
        ) + [
            'normative_source' => $normativeSource,
            'official_evidence' => $officialEvidence,
            'scope_objective' => $scopeObjective,
            'other_grade_objective' => $otherGradeObjective,
            'official_hash' => $officialHash,
            'official_path' => $officialPath,
        ];
    }

    private function curriculumReady(CompliancePreflightService $service, int $schoolId): bool
    {
        $method = new ReflectionMethod($service, 'curriculumReady');

        return (bool) $method->invoke($service, $schoolId);
    }

    private function assertScopeRejected(
        CurriculumObjectiveScopeService $service,
        Book $book,
        int $subjectId,
        int $objectiveId,
    ): void {
        try {
            $service->assertAllowed($book, $subjectId, [$objectiveId]);
            $this->fail('El objetivo sin trazabilidad canónica íntegra fue aceptado.');
        } catch (LibroDigitalException $exception) {
            $this->assertSame('LCD_CURRICULUM_OBJECTIVE_SCOPE_INVALID', $exception->errorCode);
            $this->assertSame([$objectiveId], $exception->details[0]['objective_ids']);
        }
    }
}
