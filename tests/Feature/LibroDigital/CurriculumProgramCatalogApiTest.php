<?php

namespace Tests\Feature\LibroDigital;

use App\Models\AcademicYear;
use App\Models\EducationLevel;
use App\Models\LibroDigital\CurriculumCatalog;
use App\Models\LibroDigital\CurriculumDocument;
use App\Models\LibroDigital\CurriculumImportConflict;
use App\Models\LibroDigital\CurriculumImportFile;
use App\Models\LibroDigital\CurriculumProgram;
use App\Models\LibroDigital\FeatureFlag;
use App\Models\LibroDigital\LearningObjective;
use App\Models\LibroDigital\RegulatoryProfile;
use App\Models\LibroDigital\ReportExport;
use App\Models\LibroDigital\School;
use App\Models\Role;
use App\Models\Schedule\ScheduleSubject;
use App\Models\User;
use App\Services\LibroDigital\CurriculumObjectiveIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CurriculumProgramCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_create_and_publish_a_manual_program_with_an_encrypted_source(): void
    {
        Storage::fake('local');
        config([
            'libro_digital.enabled' => true,
            'libro_digital.storage.disk' => 'local',
            'libro_digital.reports.sync_curriculum_program_exports' => true,
            'queue.default' => 'database',
        ]);
        [$user, $school, $year, $subject] = $this->context();
        $objectives = LearningObjective::query()
            ->where('schedule_subject_id', $subject->id)
            ->where('grade_code', '1B')
            ->orderBy('id')
            ->get();
        $objectives->first()->forceFill(['source_page' => 'CN01 OA 01'])->save();
        $oaIds = $objectives->where('objective_type', 'OA')->pluck('id')->values()->all();
        $skillIds = $objectives->where('objective_type', 'OAH')->pluck('id')->values()->all();
        $source = UploadedFile::fake()->createWithContent('programa-manual.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF");

        $response = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'curriculum-program-manual-001')
            ->post('/api/libro-digital/v1/curriculum/program-catalog/programs/manual', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'schedule_subject_id' => $subject->id,
                'education_level_id' => EducationLevel::query()->where('name', '1° básico')->value('id'),
                'official_name' => 'Ciencias Naturales · Programa de Estudio · Primer Año Básico',
                'official_code' => 'CN-1B-2018',
                'description' => 'Programa creado manualmente sin esperar extracción automática.',
                'issuing_authority' => 'Ministerio de Educación',
                'decree' => 'Decreto Supremo de Educación N.º 2960/2012',
                'edition' => 'Segunda edición 2018',
                'publication_year' => 2018,
                'estimated_weeks' => 38,
                'estimated_pedagogical_hours' => 114,
                'publish_now' => true,
                'source_file' => $source,
                'source_page_count' => 184,
                'objective_ids' => [...$oaIds, ...$skillIds],
                'axes' => [
                    ['name' => 'Ciencias de la Vida', 'objective_ids' => array_slice($oaIds, 0, 7)],
                    ['name' => 'Ciencias Físicas y Químicas', 'objective_ids' => array_slice($oaIds, 7, 3)],
                    ['name' => 'Ciencias de la Tierra y el Universo', 'objective_ids' => array_slice($oaIds, 10, 2)],
                ],
                'units' => [
                    [
                        'unit_code' => 'U1', 'official_title' => 'Unidad 1', 'semester' => 1,
                        'estimated_pedagogical_hours' => 30, 'objective_ids' => array_slice($oaIds, 0, 3),
                        'skills' => ['Explorar y observar'], 'attitudes' => ['Demostrar curiosidad e interés'],
                        'knowledge' => ['Hábitos de vida saludable y seres vivos'], 'keywords' => ['sentidos', 'salud'],
                    ],
                    [
                        'unit_code' => 'U2', 'official_title' => 'Unidad 2', 'semester' => 1,
                        'estimated_pedagogical_hours' => 30, 'objective_ids' => array_slice($oaIds, 3, 4),
                    ],
                    [
                        'unit_code' => 'U3', 'official_title' => 'Unidad 3', 'semester' => 2,
                        'estimated_pedagogical_hours' => 30, 'objective_ids' => array_slice($oaIds, 7, 3),
                    ],
                    [
                        'unit_code' => 'U4', 'official_title' => 'Unidad 4', 'semester' => 2,
                        'estimated_pedagogical_hours' => 24, 'objective_ids' => array_slice($oaIds, 10, 2),
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.grade_code', '1B')
            ->assertJsonCount(4, 'data.units')
            ->assertJsonCount(3, 'data.axes')
            ->assertJsonCount(16, 'data.objectives');

        $programId = $response->json('data.id');
        $document = CurriculumDocument::query()->firstOrFail();
        $this->assertTrue(Storage::disk('local')->exists($document->storage_path));
        $this->assertNotSame('%PDF-', substr(Storage::disk('local')->get($document->storage_path), 0, 5));
        $this->assertDatabaseHas('lcd_curriculum_programs', ['public_id' => $programId, 'status' => 'published']);
        $this->assertDatabaseCount('lcd_curriculum_units', 4);
        $this->assertDatabaseCount('lcd_curriculum_program_objectives', 16);
        $this->assertDatabaseHas('lcd_curriculum_program_objectives', [
            'learning_objective_id' => $objectives->first()->id,
            'source_page' => null,
        ]);
        $this->assertDatabaseHas('lcd_audit_events', ['event' => 'curriculum.program.manual_created']);

        $exported = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'curriculum-program-manual-export-001')
            ->postJson('/api/libro-digital/v1/curriculum/program-catalog/programs/'.$programId.'/export-pdf', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
            ])
            ->assertAccepted()
            ->assertJsonPath('data.status', 'completed');
        $report = ReportExport::query()->where('public_id', $exported->json('data.id'))->firstOrFail();
        $pdf = Storage::disk('local')->get($report->private_path);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('Programa curricular', $pdf);
        $this->assertStringContainsString('CONOCIMIENTOS', $pdf);
        $this->assertStringContainsString('PDF ministerial respaldado', $pdf);
        $this->assertStringNotContainsString('BORRADOR - PROGRAMA CURRICULAR', $pdf);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_php_rejected_upload_returns_an_actionable_spanish_validation_error(): void
    {
        [$user, $school, $year] = $this->context();
        $rejectedUpload = new UploadedFile(
            '/archivo-rechazado-por-php.pdf',
            'programa-mineduc.pdf',
            'application/pdf',
            UPLOAD_ERR_INI_SIZE,
            true,
        );

        $response = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'curriculum-pdf-rejected-upload-001')
            ->post('/api/libro-digital/v1/curriculum/program-catalog/imports', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'files' => [$rejectedUpload],
            ])
            ->assertUnprocessable();

        $response->assertJsonValidationErrors('files.0');

        $message = (string) ($response->json('errors')['files.0'][0] ?? '');
        $this->assertStringContainsString('El servidor no recibió el PDF completo.', $message);
        $this->assertStringContainsString('upload_max_filesize', $message);
        $this->assertStringContainsString('40 MB', $message);
    }

    public function test_real_pdf_import_review_publication_and_retry_are_traceable_and_idempotent(): void
    {
        $fixture = '/Users/angeloespinozarodriguez/Desktop/articles-20714_programa.pdf';
        if (! is_file($fixture)) {
            $this->markTestSkipped('El fixture ministerial local no está disponible.');
        }

        Storage::fake('local');
        config([
            'libro_digital.enabled' => true,
            'libro_digital.storage.disk' => 'local',
            'libro_digital.curriculum_import.queue' => 'curriculum-imports',
        ]);
        [$user, $school, $year, $subject] = $this->context();

        $unauthorized = User::factory()->create(['active' => true]);
        $this->actingAs($unauthorized)
            ->getJson('/api/libro-digital/v1/curriculum/program-catalog/matrix?school_id='.$school->id.'&academic_year_id='.$year->id)
            ->assertForbidden();

        $uploaded = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'curriculum-pdf-real-upload-001')
            ->post('/api/libro-digital/v1/curriculum/program-catalog/imports', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'files' => [$this->fixtureUpload($fixture)],
            ])
            ->assertAccepted()
            ->assertJsonCount(1, 'data.files');
        $fileId = $uploaded->json('data.files.0.id');
        $file = CurriculumImportFile::query()->where('public_id', $fileId)->firstOrFail();

        $this->assertSame('pending_review', $file->status);
        $this->assertSame(hash_file('sha256', $fixture), $file->sha256);
        $this->assertSame(184, $file->document()->value('page_count'));
        $this->assertSame('program_study', data_get($file->detected_metadata, 'classification.document_type'));
        $this->assertSame($subject->id, data_get($file->detected_metadata, 'classification.schedule_subject_id'));
        $this->assertSame('1B', data_get($file->detected_metadata, 'classification.grade_code'));
        $this->assertSame(12, $file->candidates()->where('entity_type', 'learning_objective')->count());
        $this->assertSame(4, $file->candidates()->where('entity_type', 'skill_objective')->count());
        $this->assertSame(4, $file->candidates()->where('entity_type', 'unit')->count());
        $this->assertSame(3, $file->candidates()->where('entity_type', 'axis')->count());
        $this->assertSame(16, $file->candidates()->whereNotNull('suggested_existing_id')->count());
        $reviewChildIds = $file->candidates()->whereNotNull('parent_candidate_id')->where('suggested_action', 'review')->pluck('id');
        $this->assertNotEmpty($reviewChildIds, 'Los extractos secundarios deben pasar por revisión humana explícita.');
        $this->assertContains('unit_elements_require_human_review', (array) $file->warnings);

        $critical = CurriculumImportConflict::query()->create([
            'import_file_id' => $file->id,
            'conflict_type' => 'fixture_integrity_check',
            'severity' => 'critical',
            'status' => 'open',
            'title' => 'Control crítico de prueba',
            'description' => 'La publicación debe bloquearse hasta registrar una resolución trazable.',
            'context' => ['fixture' => true],
        ]);
        $this->withHeader('Idempotency-Key', 'curriculum-pdf-validate-blocked-001')
            ->postJson('/api/libro-digital/v1/curriculum/program-catalog/imports/'.$fileId.'/validate', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
            ])->assertConflict()->assertJsonPath('code', 'LCD_CURRICULUM_CRITICAL_CONFLICTS_OPEN');

        $this->withHeader('Idempotency-Key', 'curriculum-pdf-resolve-001')
            ->postJson('/api/libro-digital/v1/curriculum/program-catalog/conflicts/'.$critical->public_id.'/resolve', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'resolution' => 'Se verificó contra las páginas 55 a 119 del PDF oficial.',
            ])->assertOk()->assertJsonPath('data.status', 'resolved');

        $this->withHeader('Idempotency-Key', 'curriculum-pdf-validate-001')
            ->postJson('/api/libro-digital/v1/curriculum/program-catalog/imports/'.$fileId.'/validate', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'note' => 'Estructura y conciliación revisadas con el documento oficial.',
            ])->assertOk()->assertJsonPath('data.status', 'validated');
        $this->assertSame(
            $reviewChildIds->count(),
            $file->candidates()->whereIn('id', $reviewChildIds)->where('review_status', 'skipped')->count(),
            'Los extractos marcados para revisión no deben publicarse por omisión.',
        );

        $published = $this->withHeader('Idempotency-Key', 'curriculum-pdf-publish-001')
            ->postJson('/api/libro-digital/v1/curriculum/program-catalog/imports/'.$fileId.'/publish', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'note' => 'Publicación curricular aprobada por UTP.',
            ])->assertCreated()
            ->assertJsonPath('data.grade_code', '1B')
            ->assertJsonPath('data.weeks', 38)
            ->assertJsonPath('data.hours', 114)
            ->assertJsonCount(4, 'data.units')
            ->assertJsonCount(3, 'data.axes')
            ->assertJsonCount(16, 'data.objectives');
        $programId = $published->json('data.id');

        $this->assertDatabaseCount('lcd_learning_objectives', 16);
        $this->assertDatabaseCount('lcd_curriculum_programs', 1);
        $this->assertDatabaseCount('lcd_curriculum_units', 4);
        $this->assertDatabaseCount('lcd_curriculum_program_objectives', 16);
        $this->assertDatabaseHas('lcd_audit_events', ['event' => 'curriculum.program.published']);

        $this->withHeader('Idempotency-Key', 'curriculum-pdf-publish-retry-002')
            ->postJson('/api/libro-digital/v1/curriculum/program-catalog/imports/'.$fileId.'/publish', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'note' => 'Reintento seguro de publicación.',
            ])->assertCreated()->assertJsonPath('data.id', $programId);
        $this->assertDatabaseCount('lcd_curriculum_programs', 1);
        $this->assertDatabaseCount('lcd_curriculum_units', 4);
        $this->assertDatabaseCount('lcd_learning_objectives', 16);

        $exported = $this->withHeader('Idempotency-Key', 'curriculum-program-pdf-export-001')
            ->postJson('/api/libro-digital/v1/curriculum/program-catalog/programs/'.$programId.'/export-pdf', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
            ])->assertAccepted()->assertJsonPath('data.status', 'completed');
        $report = ReportExport::query()->where('public_id', $exported->json('data.id'))->firstOrFail();
        $this->assertSame('application/pdf', $report->mime_type);
        $this->assertTrue(Storage::disk('local')->exists($report->private_path));
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($report->private_path));
        $this->get('/api/libro-digital/v1/reports/'.$report->public_id.'/download')
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->withHeader('Idempotency-Key', 'curriculum-pdf-duplicate-upload-002')
            ->post('/api/libro-digital/v1/curriculum/program-catalog/imports', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'files' => [$this->fixtureUpload($fixture)],
            ])->assertOk()->assertJsonCount(0, 'data.files')->assertJsonCount(1, 'data.duplicates');
        $this->assertDatabaseCount('lcd_curriculum_import_files', 1);

        $this->getJson('/api/libro-digital/v1/curriculum/program-catalog/matrix?school_id='.$school->id.'&academic_year_id='.$year->id)
            ->assertOk()->assertJsonFragment(['status' => 'published']);
        $this->getJson('/api/libro-digital/v1/curriculum/program-catalog/programs/'.$programId.'?school_id='.$school->id.'&academic_year_id='.$year->id)
            ->assertOk()
            ->assertJsonPath('data.documents.0.page_count', 184)
            ->assertJsonPath(
                'data.objectives.0.text',
                'Reconocer y observar, por medio de la exploración, que los seres vivos crecen, responden a estímulos del medio, se reproducen y necesitan agua, alimento y aire para vivir, comparándolos con las cosas no vivas.',
            )
            ->assertJsonPath('data.objectives.0.source_page', 50);
        $this->getJson('/api/libro-digital/v1/curriculum/program-catalog/search?school_id='.$school->id.'&academic_year_id='.$year->id.'&query='.urlencode('seres vivos crecen'))
            ->assertOk()
            ->assertJsonFragment(['type' => 'OA'])
            ->assertJsonFragment(['source_page' => 50]);
        $this->assertSame(1, CurriculumProgram::query()->count());
    }

    /** @return array{User,School,AcademicYear,ScheduleSubject} */
    private function context(): array
    {
        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin', 'active' => true]);
        $user->roles()->syncWithoutDetaching([$role->id]);
        $school = School::query()->create([
            'rbd' => '6830-0', 'name' => 'Colegio Curricular', 'timezone' => 'America/Santiago', 'active' => true,
        ]);
        $year = AcademicYear::factory()->create([
            'year' => 2035, 'name' => '2035', 'starts_at' => '2035-03-01', 'ends_at' => '2035-12-20',
            'is_active' => true, 'is_closed' => false,
        ]);
        $profile = RegulatoryProfile::query()->create([
            'code' => 'CL-CURRICULUM-PDF', 'name' => 'Perfil curricular PDF', 'version' => '1',
            'effective_from' => '2030-01-01', 'retention_years' => 6, 'rules_snapshot' => [], 'active' => true,
        ]);
        $school->academicYears()->attach($year->id, [
            'regulatory_profile_id' => $profile->id, 'rbd_snapshot' => $school->rbd,
            'year_snapshot' => $year->year, 'timezone_snapshot' => $school->timezone, 'active' => true,
        ]);
        EducationLevel::query()->firstOrCreate(
            ['name' => '1° básico'],
            ['order' => 101, 'type' => 'basica'],
        );
        $subject = ScheduleSubject::query()->create([
            'name' => 'Ciencias Naturales', 'code' => 'CN', 'area' => 'Ciencias', 'color' => '#0ab39c', 'active' => true,
        ]);
        FeatureFlag::query()->create([
            'school_id' => $school->id, 'scope_key' => 'school:'.$school->id,
            'code' => 'lcd_enabled', 'enabled' => true,
        ]);
        $catalog = CurriculumCatalog::query()->create([
            'code' => 'MINEDUC-2012-CN-1B', 'name' => 'Ciencias Naturales 1° básico',
            'version' => '2018', 'authority' => 'Ministerio de Educación', 'active' => true,
        ]);
        foreach ([...range(1, 12), 'a', 'b', 'c', 'd'] as $code) {
            $skill = is_string($code);
            $officialCode = $skill ? 'CN01 OAH '.$code : 'CN01 OA '.str_pad((string) $code, 2, '0', STR_PAD_LEFT);
            $type = $skill ? 'OAH' : 'OA';
            LearningObjective::query()->create([
                'curriculum_catalog_id' => $catalog->id,
                'schedule_subject_id' => $subject->id,
                'level_code' => 'BASICA',
                'grade_code' => '1B',
                'curriculum_track' => 'GENERAL',
                'axis_code' => $skill ? 'HABILIDADES' : 'CIENCIAS NATURALES',
                'objective_type' => $type,
                'code' => $officialCode,
                'objective_key' => CurriculumObjectiveIdentity::key([
                    'code' => $officialCode, 'objective_type' => $type, 'subject_code' => $subject->code,
                    'level_code' => 'BASICA', 'grade_code' => '1B', 'curriculum_track' => 'GENERAL',
                    'axis_code' => $skill ? 'HABILIDADES' : 'CIENCIAS NATURALES',
                ]),
                'description' => ($skill ? 'Habilidad científica ' : 'Objetivo de aprendizaje ').$code,
                'active' => true,
            ]);
        }

        return [$user, $school, $year, $subject];
    }

    private function fixtureUpload(string $path): UploadedFile
    {
        return new UploadedFile($path, 'articles-20714_programa.pdf', 'application/pdf', null, true);
    }
}
