<?php

namespace Tests\Feature\Students;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Services\Students\LirmiStudentPdfParser;
use App\Services\Students\StudentAccountService;
use App\Services\Students\StudentEnrollmentLifecycleService;
use App\Services\Students\StudentPdfImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\TestCase;

class StudentPdfImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_and_then_updates_a_student_without_duplicating_enrollment(): void
    {
        EducationLevel::query()->create([
            'name' => 'NT1',
            'order' => 1,
            'type' => 'parvularia',
        ]);

        $record = [
            'page' => 1,
            'profile' => [
                'first_name' => 'Isidora Ignacia',
                'last_name' => 'Aburto Espinoza',
                'registered_name' => 'Isidora Ignacia Aburto Espinoza',
                'rut' => '27558017-1',
                'birthdate' => '2021-06-16',
                'phone' => '988961590',
                'guardian_name' => 'Cristian Marcelo Aburto Vaez',
                'guardian_relationship' => 'Padre',
                'guardian_rut' => '19248134-1',
                'guardian_education_level' => 'PROFESIONAL INCOMPLETA',
                'guardian_occupation' => 'Trabajador Dependiente',
                'is_pie_participant' => true,
                'pie_diagnosis' => 'Trastorno del Lenguaje (TL)',
            ],
            'enrollment' => [
                'year' => 2026,
                'course_name' => 'Primer Nivel Transición A',
                'source_status' => 'Matriculado',
                'registration_number' => '1',
                'enrolled_at' => '2026-03-04',
            ],
        ];
        $updatedRecord = $record;
        $updatedRecord['profile']['phone'] = '999999999';

        $parser = Mockery::mock(LirmiStudentPdfParser::class);
        $parser->shouldReceive('parseFile')->twice()->andReturn([$record], [$updatedRecord]);

        $service = new StudentPdfImportService(
            $parser,
            app(StudentAccountService::class),
            app(StudentEnrollmentLifecycleService::class),
        );
        $file = UploadedFile::fake()->create('ficha.pdf', 10, 'application/pdf');

        $created = $service->import($file);
        $updated = $service->import($file);

        $this->assertSame(1, $created['created']);
        $this->assertSame(1, $created['enrollments']);
        $this->assertSame(1, $updated['updated']);
        $this->assertDatabaseCount('student_profiles', 1);
        $this->assertDatabaseCount('student_enrollments', 1);
        $this->assertDatabaseCount('student_enrollment_movements', 1);

        $student = StudentProfile::query()->firstOrFail();
        $enrollment = StudentEnrollment::query()->firstOrFail();
        $this->assertSame('999999999', $student->phone);
        $this->assertSame('Cristian Marcelo Aburto Vaez', $student->father_name);
        $this->assertSame('NT1 A', $enrollment->snapshot_course_display_name);
        $this->assertSame('1', $enrollment->registration_number);
    }

    public function test_it_matches_an_existing_lowercase_nt1_course(): void
    {
        $year = AcademicYear::query()->create([
            'name' => '2026',
            'year' => 2026,
            'starts_at' => '2026-03-01',
            'ends_at' => '2026-12-31',
            'is_active' => true,
            'is_closed' => false,
        ]);
        $level = EducationLevel::query()->create([
            'name' => 'nt1',
            'order' => 1,
            'type' => 'parvularia',
        ]);
        $course = CourseSection::query()->create([
            'academic_year_id' => $year->id,
            'education_level_id' => $level->id,
            'section_name' => 'A',
            'display_name' => 'nt1 A',
            'active' => true,
        ]);

        $record = [
            'page' => 1,
            'profile' => [
                'first_name' => 'Isidora Ignacia',
                'last_name' => 'Aburto Espinoza',
                'registered_name' => 'Isidora Ignacia Aburto Espinoza',
                'rut' => '27558017-1',
            ],
            'enrollment' => [
                'year' => 2026,
                'course_name' => 'Primer Nivel Transición A',
                'source_status' => 'Matriculado',
            ],
        ];

        $parser = Mockery::mock(LirmiStudentPdfParser::class);
        $parser->shouldReceive('parseFile')->once()->andReturn([$record]);

        $service = new StudentPdfImportService(
            $parser,
            app(StudentAccountService::class),
            app(StudentEnrollmentLifecycleService::class),
        );
        $result = $service->import(UploadedFile::fake()->create('ficha.pdf', 10, 'application/pdf'));

        $this->assertSame(1, $result['enrollments']);
        $this->assertDatabaseCount('course_sections', 1);
        $this->assertDatabaseHas('student_enrollments', [
            'course_section_id' => $course->id,
        ]);
    }

    public function test_it_creates_and_matches_a_known_nt2_course_when_the_catalog_is_missing(): void
    {
        EducationLevel::query()->create([
            'name' => 'NT1',
            'order' => 1,
            'type' => 'parvularia',
        ]);

        $record = [
            'page' => 1,
            'profile' => [
                'first_name' => 'Isidora Ignacia',
                'last_name' => 'Aburto Espinoza',
                'registered_name' => 'Isidora Ignacia Aburto Espinoza',
                'rut' => '27558017-1',
            ],
            'enrollment' => [
                'year' => 2026,
                'course_name' => 'Segundo Nivel Transición A',
                'source_status' => 'Matriculado',
            ],
        ];

        $parser = Mockery::mock(LirmiStudentPdfParser::class);
        $parser->shouldReceive('parseFile')->once()->andReturn([$record]);

        $service = new StudentPdfImportService(
            $parser,
            app(StudentAccountService::class),
            app(StudentEnrollmentLifecycleService::class),
        );
        $result = $service->import(UploadedFile::fake()->create('ficha.pdf', 10, 'application/pdf'));

        $this->assertSame(1, $result['enrollments']);
        $this->assertDatabaseHas('education_levels', [
            'name' => 'NT2',
            'order' => 2,
            'type' => 'parvularia',
        ]);
        $this->assertDatabaseHas('course_sections', [
            'section_name' => 'A',
            'display_name' => 'NT2 A',
        ]);
        $this->assertDatabaseHas('student_enrollments', [
            'snapshot_course_display_name' => 'NT2 A',
        ]);
    }

    public function test_it_uses_the_selected_existing_course_instead_of_the_course_from_the_pdf(): void
    {
        $year = AcademicYear::query()->create([
            'name' => '2026',
            'year' => 2026,
            'starts_at' => '2026-03-01',
            'ends_at' => '2026-12-31',
            'is_active' => true,
            'is_closed' => false,
        ]);
        $level = EducationLevel::query()->create([
            'name' => 'NT2',
            'order' => 2,
            'type' => 'parvularia',
        ]);
        $selectedCourse = CourseSection::query()->create([
            'academic_year_id' => $year->id,
            'education_level_id' => $level->id,
            'section_name' => 'B',
            'display_name' => 'NT2 B',
            'active' => true,
        ]);
        $record = [
            'page' => 1,
            'profile' => [
                'first_name' => 'Isidora Ignacia',
                'last_name' => 'Aburto Espinoza',
                'registered_name' => 'Isidora Ignacia Aburto Espinoza',
                'rut' => '27558017-1',
            ],
            'enrollment' => [
                'year' => 2026,
                'course_name' => 'Primer Nivel Transición A',
                'source_status' => 'Matriculado',
            ],
        ];

        $parser = Mockery::mock(LirmiStudentPdfParser::class);
        $parser->shouldReceive('parseFile')->once()->andReturn([$record]);

        $service = new StudentPdfImportService(
            $parser,
            app(StudentAccountService::class),
            app(StudentEnrollmentLifecycleService::class),
        );
        $result = $service->import(
            UploadedFile::fake()->create('ficha.pdf', 10, 'application/pdf'),
            courseSectionId: $selectedCourse->id,
        );

        $this->assertSame(1, $result['enrollments']);
        $this->assertDatabaseHas('student_enrollments', [
            'course_section_id' => $selectedCourse->id,
            'snapshot_course_display_name' => 'NT2 B',
        ]);
        $this->assertDatabaseCount('course_sections', 1);
    }
}
