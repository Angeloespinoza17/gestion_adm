<?php

namespace Tests\Feature\LibroDigital;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\LibroDigital\CurriculumCatalog;
use App\Models\LibroDigital\CurriculumCatalogActivation;
use App\Models\LibroDigital\CurriculumImportBatch;
use App\Models\LibroDigital\LearningObjective;
use App\Models\LibroDigital\RegulatoryProfile;
use App\Models\LibroDigital\School;
use App\Models\Role;
use App\Models\Schedule\ScheduleEvent;
use App\Models\Schedule\ScheduleSubject;
use App\Models\Schedule\TeacherScheduleLayer;
use App\Models\Staff;
use App\Models\User;
use App\Services\LibroDigital\CompliancePreflightService;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class OfficialCurriculumBookSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_activates_official_offer_prepares_books_and_opens_only_unambiguous_teacher_assignments(): void
    {
        [$actor, $school, $year, $course, $mathematics, $language] = $this->context();
        $arguments = [
            '--school' => (string) $school->id,
            '--year' => (string) $year->year,
            '--actor' => (string) $actor->id,
            '--approval-reference' => 'ACTA-CURRICULUM-2035-01',
            '--json' => true,
        ];

        $this->assertSame(Command::SUCCESS, Artisan::call('lcd:curriculum:sync-official-books', $arguments));
        $preview = Artisan::output();
        $this->assertStringContainsString('"official_subjects": 2', $preview);
        $this->assertStringContainsString('"missing_expected_books": 2', $preview);
        $this->assertDatabaseCount('lcd_books', 0);
        $this->assertDatabaseHas('schedule_subjects', ['id' => $mathematics->id, 'active' => false]);

        $this->mock(CompliancePreflightService::class, function ($mock): void {
            $mock->shouldReceive('run')->once()->andReturn([
                'ready' => true,
                'core_ready' => true,
                'module_enabled' => true,
                'capabilities' => [],
                'checks' => [],
                'blockers' => [],
            ]);
        });

        $this->assertSame(Command::FAILURE, Artisan::call('lcd:curriculum:sync-official-books', [
            ...$arguments,
            '--execute' => true,
        ]));
        $output = Artisan::output();
        $this->assertStringContainsString('"activated_subjects": 2', $output);
        $this->assertStringContainsString('"links_created": 2', $output);
        $this->assertStringContainsString('"books_created": 2', $output);
        $this->assertStringContainsString('"books_opened": 1', $output);
        $this->assertStringContainsString('"books_blocked": 1', $output);

        $this->assertDatabaseHas('schedule_subjects', ['id' => $mathematics->id, 'active' => true]);
        $this->assertDatabaseHas('schedule_subjects', ['id' => $language->id, 'active' => true]);
        $this->assertDatabaseCount('lcd_subject_catalog_profiles', 2);
        $this->assertDatabaseCount('lcd_subject_curriculum_links', 2);
        $this->assertDatabaseCount('lcd_books', 2);
        $this->assertDatabaseHas('lcd_books', ['course_section_id' => $course->id, 'status' => 'open']);
        $this->assertDatabaseHas('lcd_teacher_assignments', ['schedule_subject_id' => $mathematics->id, 'active' => true]);
        $this->assertDatabaseMissing('lcd_teacher_assignments', ['schedule_subject_id' => $language->id, 'active' => true]);
        $this->assertDatabaseHas('lcd_audit_events', ['event' => 'lcd.official_curriculum.offer_synced']);
        $this->assertDatabaseHas('lcd_audit_events', ['event' => 'lcd.book.opened_by_official_curriculum_sync']);

        $this->mock(CompliancePreflightService::class, function ($mock): void {
            $mock->shouldReceive('run')->once()->andReturn([
                'ready' => true,
                'core_ready' => true,
                'module_enabled' => true,
                'capabilities' => [],
                'checks' => [],
                'blockers' => [],
            ]);
        });
        $this->assertSame(Command::FAILURE, Artisan::call('lcd:curriculum:sync-official-books', [
            ...$arguments,
            '--execute' => true,
        ]));
        $this->assertDatabaseCount('lcd_subject_catalog_profiles', 2);
        $this->assertDatabaseCount('lcd_subject_curriculum_links', 2);
        $this->assertDatabaseCount('lcd_books', 2);
    }

    /** @return array{User, School, AcademicYear, CourseSection, ScheduleSubject, ScheduleSubject} */
    private function context(): array
    {
        $actor = User::factory()->create(['active' => true]);
        $role = Role::query()->firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin', 'active' => true]);
        $actor->roles()->sync([$role->id]);
        $school = School::query()->create([
            'rbd' => '12345-6',
            'name' => 'Escuela oficial de prueba',
            'timezone' => 'America/Santiago',
            'active' => true,
        ]);
        $year = AcademicYear::factory()->create([
            'year' => 2035,
            'name' => '2035',
            'starts_at' => '2035-03-01',
            'ends_at' => '2035-12-20',
            'is_active' => true,
        ]);
        $profile = RegulatoryProfile::query()->create([
            'code' => 'OFFICIAL-TEST',
            'name' => 'Perfil normativo oficial',
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
        $level = EducationLevel::query()->firstOrCreate(
            ['name' => '1° básico'],
            ['order' => 101, 'type' => 'basica'],
        );
        $course = CourseSection::factory()->create([
            'academic_year_id' => $year->id,
            'education_level_id' => $level->id,
            'display_name' => '1° Básico A',
            'section_name' => 'A',
            'active' => true,
        ]);
        $mathematics = ScheduleSubject::query()->create(['name' => 'Matemática', 'code' => 'MAT', 'active' => false]);
        $language = ScheduleSubject::query()->create(['name' => 'Lenguaje y Comunicación', 'code' => 'LEN', 'active' => false]);
        $catalog = CurriculumCatalog::query()->create([
            'code' => 'CN-OFFICIAL-TEST',
            'name' => 'Currículum oficial de prueba',
            'version' => '2035.1',
            'authority' => 'MINEDUC',
            'source_url' => 'https://www.curriculumnacional.cl/',
            'source_hash' => hash('sha256', 'catalog-test'),
            'active' => true,
        ]);
        foreach ([[$mathematics, 'OA-MAT-1'], [$language, 'OA-LEN-1']] as [$subject, $code]) {
            LearningObjective::query()->create([
                'curriculum_catalog_id' => $catalog->id,
                'schedule_subject_id' => $subject->id,
                'level_code' => 'BASICA',
                'grade_code' => '1B',
                'curriculum_track' => 'GENERAL',
                'objective_type' => 'OA',
                'code' => $code,
                'description' => 'Objetivo oficial de prueba',
                'active' => true,
            ]);
        }
        $batch = CurriculumImportBatch::query()->create([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'curriculum_catalog_id' => $catalog->id,
            'idempotency_key' => hash('sha256', 'official-sync-batch'),
            'status' => CurriculumImportBatch::STATUS_ACTIVATED,
            'catalog_code' => $catalog->code,
            'catalog_version' => $catalog->version,
            'original_name' => 'curriculum.xlsx',
            'private_path' => 'private/curriculum.xlsx.enc',
            'source_hash' => hash('sha256', 'official-sync-workbook'),
            'requested_at' => now('UTC'),
        ]);
        CurriculumCatalogActivation::query()->create([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'curriculum_catalog_id' => $catalog->id,
            'import_batch_id' => $batch->id,
            'activation_version' => 1,
            'idempotency_key' => hash('sha256', 'official-sync-activation'),
            'status' => CurriculumCatalogActivation::STATUS_ACTIVATED,
            'scope_snapshot' => ['complete_nt1_4m' => true],
            'requested_at' => now('UTC'),
            'activated_at' => now('UTC'),
        ]);

        $teacher = Staff::query()->create(['full_name' => 'Docente Matemática', 'rut' => '12.345.678-5', 'active' => true]);
        $layer = TeacherScheduleLayer::query()->create([
            'staff_id' => $teacher->id,
            'academic_year_id' => $year->id,
            'name' => 'Horario lectivo',
            'active' => true,
        ]);
        ScheduleEvent::query()->create([
            'academic_year_id' => $year->id,
            'staff_id' => $teacher->id,
            'teacher_schedule_layer_id' => $layer->id,
            'course_section_id' => $course->id,
            'education_level_id' => $level->id,
            'schedule_subject_id' => $mathematics->id,
            'day_of_week' => 1,
            'start_time' => '08:00',
            'end_time' => '08:45',
            'activity_type' => 'lective_class',
            'pedagogical_hours' => 1,
            'minutes' => 45,
            'status' => ScheduleEvent::STATUS_CONFIRMED,
            'source' => ScheduleEvent::SOURCE_MANUAL,
        ]);

        return [$actor, $school, $year, $course, $mathematics, $language];
    }
}
