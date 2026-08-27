<?php

namespace Tests\Feature\PedagogicalManagement;

use App\Jobs\PedagogicalManagement\AnalyzePedagogicalInstrumentJob;
use App\Models\AcademicYear;
use App\Models\CentroApuntes\CentroApuntesAsignatura;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\LibroDigital\School;
use App\Models\PedagogicalManagement\PedagogicalInstrument;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Schedule\ScheduleSubject;
use App\Models\User;
use App\Services\PedagogicalManagement\PedagogicalInstrumentAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class PedagogicalInstrumentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_minimal_pdf_is_reviewed_with_deterministic_rules_without_external_requests(): void
    {
        Queue::fake();
        Storage::fake('local');
        [$user, $school, $year, $course, $subject] = $this->context();

        $response = $this->actingAs($user)->post('/api/pedagogical-management/instruments', [
            ...$this->payload($user, $school, $course, $subject),
            'pdf' => UploadedFile::fake()->createWithContent('control-geometria.pdf', $this->syntheticPdf()),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.instrument.title', 'control geometria')
            ->assertJsonPath('data.instrument.workflow_status', 'submitted')
            ->assertJsonPath('data.instrument.owner.id', $user->id)
            ->assertJsonPath('data.instrument.subject.id', $subject->id)
            ->assertJsonPath('data.instrument.courses.0.id', $course->id);

        $instrument = PedagogicalInstrument::query()->firstOrFail();
        $file = $instrument->files()->firstOrFail();
        $this->assertSame($year->id, $instrument->academic_year_id);
        $this->assertSame('other', $instrument->instrument_type->value);
        $this->assertSame('other', $instrument->evaluation_purpose->value);
        $this->assertSame('local', $file->storage_disk);
        $this->assertStringStartsWith('private/pedagogical-management/instruments/', $file->storage_path);
        Storage::disk('local')->assertExists($file->storage_path);
        Queue::assertNotPushed(AnalyzePedagogicalInstrumentJob::class);
        $this->assertDatabaseCount('pedagogical_instrument_analysis_runs', 0);

        $this->actingAs($user)
            ->postJson('/api/pedagogical-management/instruments/'.$instrument->uuid.'/analyses')
            ->assertAccepted();
        Queue::assertPushed(AnalyzePedagogicalInstrumentJob::class);

        $run = $instrument->analysisRuns()->firstOrFail();
        app(PedagogicalInstrumentAnalysisService::class)->process($run);

        $this->assertDatabaseHas('pedagogical_instrument_validation_results', [
            'analysis_run_id' => $run->id,
            'category' => 'suggestion',
            'code' => 'OA_NOT_SELECTED',
            'outcome' => 'warning',
            'reliability' => 'exact',
        ]);
        $this->assertSame('validated_with_warnings', $instrument->fresh()->status->value);
        $this->assertSame('laravel-deterministic-rules', $run->fresh()->extractor);
        $this->assertStringContainsString('smalot/pdfparser', $run->fresh()->extractor_version);
        $this->assertStringNotContainsString('openai', mb_strtolower(json_encode($run->fresh()->extracted_data)));

        $this->actingAs($user)->getJson('/api/pedagogical-management/instruments/'.$instrument->uuid)
            ->assertOk()
            ->assertJsonPath('data.latest_analysis.summary.errors', 0)
            ->assertJsonPath('data.latest_analysis.extractor', 'laravel-deterministic-rules');

        $this->actingAs($user)->post('/api/pedagogical-management/instruments', [
            ...$this->payload($user, $school, $course, $subject),
            'pdf' => UploadedFile::fake()->createWithContent('copia.pdf', $this->syntheticPdf()),
        ])->assertConflict()->assertJsonPath('code', 'DUPLICATE_FILE_HASH');
        $this->assertDatabaseCount('pedagogical_instruments', 1);
    }

    public function test_only_four_registration_fields_are_required_and_engine_is_always_local(): void
    {
        Queue::fake();
        Storage::fake('local');
        [$user, $school, $year, $course, $subject] = $this->context();

        $this->actingAs($user)->getJson('/api/pedagogical-management/catalogs?school_id='.$school->id)
            ->assertOk()
            ->assertJsonPath('data.analysis_configured', true)
            ->assertJsonPath('data.analysis_engine', 'Reglas determinísticas Laravel · smalot/pdfparser')
            ->assertJsonPath('data.academic_year.id', $year->id)
            ->assertJsonCount(1, 'data.courses');

        $this->actingAs($user)->post('/api/pedagogical-management/instruments', [
            'school_id' => $school->id,
            'pdf' => UploadedFile::fake()->createWithContent('instrumento.pdf', $this->syntheticPdf()),
        ])->assertUnprocessable()->assertJsonValidationErrors(['owner_user_id', 'subject_id', 'course_id']);
    }

    public function test_catalog_resolves_the_configured_institution_without_a_visible_school_selector(): void
    {
        [$user, $school] = $this->context();
        config()->set('libro_digital.default_school.rbd', $school->rbd);
        School::query()->create([
            'rbd' => '77777-7',
            'name' => 'Otro establecimiento activo',
            'timezone' => 'America/Santiago',
            'active' => true,
        ]);

        $this->actingAs($user)
            ->getJson('/api/pedagogical-management/catalogs')
            ->assertOk()
            ->assertJsonPath('data.selected_school_id', $school->id)
            ->assertJsonPath('data.academic_year.year', 2035)
            ->assertJsonCount(1, 'data.courses');
    }

    public function test_institutional_catalog_bridge_only_inserts_missing_references_and_is_idempotent(): void
    {
        config()->set('libro_digital.default_school.rbd', '54321-0');
        config()->set('libro_digital.default_school.name', 'Colegio de prueba institucional');
        $year = AcademicYear::factory()->create([
            'year' => 2042,
            'name' => '2042',
            'is_active' => true,
            'is_closed' => false,
        ]);
        CentroApuntesAsignatura::query()->create([
            'name' => 'Lengua y Literatura institucional',
            'code' => 'LENG-INST',
            'area' => 'Lenguaje',
            'status' => 'activa',
        ]);
        ScheduleSubject::query()->create([
            'name' => 'Nombre canónico preservado',
            'code' => 'PRESERVAR',
            'area' => 'Artes',
            'color' => '#123456',
            'active' => true,
        ]);
        CentroApuntesAsignatura::query()->create([
            'name' => 'Nombre que no debe sobrescribir',
            'code' => 'PRESERVAR',
            'area' => 'Música',
            'status' => 'activa',
        ]);

        $migration = require database_path('migrations/2026_08_27_010000_bridge_institutional_pedagogical_catalogs.php');
        $migration->up();
        $migration->up();

        $school = School::query()->where('rbd', '54321-0')->firstOrFail();
        $this->assertDatabaseHas('lcd_school_academic_years', [
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'year_snapshot' => 2042,
            'active' => true,
        ]);
        $this->assertDatabaseHas('schedule_subjects', [
            'name' => 'Lengua y Literatura institucional',
            'code' => 'LENG-INST',
            'active' => true,
        ]);
        $this->assertDatabaseHas('schedule_subjects', [
            'name' => 'Nombre canónico preservado',
            'code' => 'PRESERVAR',
            'area' => 'Artes',
        ]);
        $this->assertSame(1, School::query()->where('rbd', '54321-0')->count());
        $this->assertSame(1, ScheduleSubject::query()->where('code', 'LENG-INST')->count());
    }

    public function test_authorized_teacher_uses_the_configured_institution_without_a_libro_digital_membership(): void
    {
        Queue::fake();
        Storage::fake('local');
        [$user, $school, $year, $course, $subject] = $this->context(false);
        config()->set('libro_digital.default_school.rbd', $school->rbd);
        $school->users()->detach($user->id);

        $this->actingAs($user)
            ->getJson('/api/pedagogical-management/catalogs?scope=mine&school_id='.$school->id)
            ->assertOk()
            ->assertJsonPath('data.selected_school_id', $school->id)
            ->assertJsonPath('data.academic_year.id', $year->id)
            ->assertJsonCount(1, 'data.subjects')
            ->assertJsonCount(1, 'data.courses')
            ->assertJsonPath('data.owners.0.id', $user->id);

        $this->actingAs($user)->post('/api/pedagogical-management/instruments', [
            ...$this->payload($user, $school, $course, $subject),
            'file' => UploadedFile::fake()->createWithContent('instrumento-institucional.pdf', $this->syntheticPdf()),
        ])->assertCreated();

        $this->actingAs($user)
            ->getJson('/api/pedagogical-management/instruments?scope=mine&school_id='.$school->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_php_rejected_upload_returns_one_actionable_spanish_error(): void
    {
        [$user, $school, $year, $course, $subject] = $this->context();
        $rejectedUpload = new UploadedFile(
            '/archivo-pdf-rechazado-por-php.pdf',
            'evaluacion.pdf',
            'application/pdf',
            UPLOAD_ERR_INI_SIZE,
            true,
        );

        $response = $this->actingAs($user)->post('/api/pedagogical-management/instruments', [
            ...$this->payload($user, $school, $course, $subject),
            'file' => $rejectedUpload,
        ])->assertUnprocessable()->assertJsonValidationErrors('file');

        $errors = (array) $response->json('errors');
        $this->assertArrayNotHasKey('pdf', $errors);
        $this->assertCount(1, (array) ($errors['file'] ?? []));
        $message = (string) ($errors['file'][0] ?? '');
        $this->assertStringContainsString('El servidor no recibió el archivo completo.', $message);
        $this->assertStringContainsString('upload_max_filesize', $message);
        $this->assertStringContainsString('20 MB', $message);
    }

    public function test_word_is_accepted_and_owner_and_school_privacy_are_preserved(): void
    {
        Queue::fake();
        Storage::fake('local');
        [$user, $school, $year, $course, $subject] = $this->context(false);

        $wordResponse = $this->actingAs($user)->post('/api/pedagogical-management/instruments', [
            ...$this->payload($user, $school, $course, $subject),
            'file' => UploadedFile::fake()->createWithContent('instrumento.docx', $this->syntheticDocx('INSTRUMENTO WORD CON INSTRUCCIONES Y PREGUNTAS EVALUABLES')),
        ])->assertCreated()
            ->assertJsonPath('data.instrument.latest_file.original_filename', 'instrumento.docx');
        $wordInstrument = PedagogicalInstrument::query()->where('uuid', $wordResponse->json('data.instrument.id'))->firstOrFail();
        $wordFile = $wordInstrument->files()->firstOrFail();
        $this->assertSame('application/vnd.openxmlformats-officedocument.wordprocessingml.document', $wordFile->mime_type);
        $this->assertSame('docx', $wordFile->technical_metadata['document_type']);
        $this->assertTrue($wordFile->technical_metadata['signature_valid']);
        $this->actingAs($user)->post('/api/pedagogical-management/instruments', [
            ...$this->payload($user, $school, $course, $subject),
            'file' => UploadedFile::fake()->createWithContent('archivo-falso.docx', 'PK contenido que no es OOXML'),
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'DOCX_INVALID_MIME');

        $otherTeacher = User::factory()->create(['active' => true]);
        $school->users()->attach($otherTeacher->id, ['active' => true, 'role_snapshot' => 'Docente']);
        $this->actingAs($user)->getJson('/api/pedagogical-management/catalogs?school_id='.$school->id)
            ->assertOk()->assertJsonCount(1, 'data.owners')->assertJsonPath('data.owners.0.id', $user->id);

        $this->actingAs($user)->post('/api/pedagogical-management/instruments', [
            ...$this->payload($otherTeacher, $school, $course, $subject),
            'pdf' => UploadedFile::fake()->createWithContent('instrumento-propio.pdf', $this->syntheticPdf('VERSION PRIVADA DIFERENTE')),
        ])->assertCreated()->assertJsonPath('data.instrument.owner.id', $user->id);

        $foreign = School::query()->create(['rbd' => '99999-9', 'name' => 'Establecimiento ajeno', 'timezone' => 'America/Santiago', 'active' => true]);
        $instrument = PedagogicalInstrument::query()->create([
            'school_id' => $foreign->id,
            'academic_year_id' => $year->id,
            'owner_user_id' => $user->id,
            'subject_id' => $subject->id,
            'title' => 'Instrumento privado de otro establecimiento',
            'instrument_type' => 'other',
            'evaluation_purpose' => 'other',
            'work_modality' => 'individual',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->getJson('/api/pedagogical-management/instruments/'.$instrument->uuid)->assertForbidden();
    }

    public function test_mine_scope_keeps_the_personal_view_private_even_for_a_user_with_global_access(): void
    {
        [$user, $school, $year, $course, $subject] = $this->context();
        $otherTeacher = User::factory()->create(['active' => true]);
        $school->users()->attach($otherTeacher->id, ['active' => true, 'role_snapshot' => 'Docente']);

        $own = $this->createInstrument($user, $school, $year, $course, $subject, 'Instrumento propio');
        $this->createInstrument($otherTeacher, $school, $year, $course, $subject, 'Instrumento de otro docente');

        $this->actingAs($user)
            ->getJson('/api/pedagogical-management/instruments?scope=mine&school_id='.$school->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $own->uuid)
            ->assertJsonPath('data.0.owner.id', $user->id)
            ->assertJsonMissing(['title' => 'Instrumento de otro docente']);

        $this->actingAs($user)
            ->getJson('/api/pedagogical-management/catalogs?scope=mine&school_id='.$school->id)
            ->assertOk()
            ->assertJsonCount(1, 'data.owners')
            ->assertJsonPath('data.owners.0.id', $user->id);
    }

    public function test_teacher_cannot_list_or_open_another_teacher_instrument_from_the_same_school(): void
    {
        [$user, $school, $year, $course, $subject] = $this->context(false);
        $otherTeacher = User::factory()->create(['active' => true]);
        $school->users()->attach($otherTeacher->id, ['active' => true, 'role_snapshot' => 'Docente']);

        $own = $this->createInstrument($user, $school, $year, $course, $subject, 'Instrumento del docente autenticado');
        $foreign = $this->createInstrument($otherTeacher, $school, $year, $course, $subject, 'Instrumento privado de otro docente');

        $this->actingAs($user)
            ->getJson('/api/pedagogical-management/instruments?school_id='.$school->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $own->uuid)
            ->assertJsonMissing(['id' => $foreign->uuid]);

        $this->actingAs($user)
            ->getJson('/api/pedagogical-management/instruments/'.$foreign->uuid)
            ->assertForbidden();
    }

    private function createInstrument(
        User $owner,
        School $school,
        AcademicYear $year,
        CourseSection $course,
        ScheduleSubject $subject,
        string $title,
    ): PedagogicalInstrument {
        $instrument = PedagogicalInstrument::query()->create([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'owner_user_id' => $owner->id,
            'subject_id' => $subject->id,
            'title' => $title,
            'grade_label' => $course->display_name,
            'instrument_type' => 'other',
            'evaluation_purpose' => 'other',
            'work_modality' => 'individual',
            'status' => 'uploaded',
            'workflow_status' => 'submitted',
            'submitted_at' => now(),
            'created_by' => $owner->id,
        ]);
        $instrument->courses()->attach($course->id);

        return $instrument;
    }

    /** @return array{User,School,AcademicYear,CourseSection,ScheduleSubject} */
    private function context(bool $superAdmin = true): array
    {
        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->firstOrCreate(
            ['slug' => $superAdmin ? 'super_admin' : 'pedagogical_reviewer_test'],
            ['name' => $superAdmin ? 'Super Admin' : 'Revisor pedagógico', 'active' => true],
        );
        if (! $superAdmin) {
            $permissionIds = collect([
                'pedagogical-instruments.view',
                'pedagogical-instruments.create',
                'pedagogical-instruments.update',
                'pedagogical-instruments.download',
                'pedagogical-instruments.analyze',
            ])->map(fn (string $slug): int => Permission::query()->firstOrCreate(['slug' => $slug], ['name' => $slug, 'active' => true])->id);
            $role->permissions()->sync($permissionIds);
        }
        $user->roles()->sync([$role->id]);
        $school = School::query()->create(['rbd' => '12345-6', 'name' => 'Escuela sintética', 'timezone' => 'America/Santiago', 'active' => true]);
        $school->users()->attach($user->id, ['active' => true, 'role_snapshot' => 'Docente']);
        $year = AcademicYear::factory()->create(['year' => 2035, 'name' => '2035', 'starts_at' => '2035-03-01', 'ends_at' => '2035-12-20', 'is_active' => true]);
        $school->academicYears()->attach($year->id, ['rbd_snapshot' => $school->rbd, 'year_snapshot' => 2035, 'timezone_snapshot' => $school->timezone, 'active' => true]);
        $level = EducationLevel::factory()->create(['name' => 'Octavo básico sintético', 'order' => 808, 'type' => 'basica']);
        $course = CourseSection::factory()->create(['academic_year_id' => $year->id, 'education_level_id' => $level->id, 'display_name' => '8° Básico A', 'section_name' => 'A', 'active' => true]);
        $subject = ScheduleSubject::query()->create(['name' => 'Matemática', 'code' => 'MAT-08', 'area' => 'Matemática', 'color' => '#176b76', 'active' => true]);

        return [$user, $school, $year, $course, $subject];
    }

    /** @return array<string,mixed> */
    private function payload(User $owner, School $school, CourseSection $course, ScheduleSubject $subject): array
    {
        return [
            'school_id' => $school->id,
            'owner_user_id' => $owner->id,
            'subject_id' => $subject->id,
            'course_id' => $course->id,
        ];
    }

    private function syntheticPdf(string $streamText = 'CONTROL DE GEOMETRIA PUNTAJE TOTAL 20 PUNTOS TEXTO SINTETICO PRIVADO'): string
    {
        $stream = 'BT /F1 12 Tf 45 720 Td ('.$streamText.') Tj ET';
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            '<< /Length '.strlen($stream).">>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        for ($index = 1; $index <= 5; $index++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$index])."\n";
        }

        return $pdf."trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }

    private function syntheticDocx(string $documentText): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pedagogical-docx-');
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>'.htmlspecialchars($documentText, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();
        $content = file_get_contents($path);
        @unlink($path);

        return is_string($content) ? $content : '';
    }

}
