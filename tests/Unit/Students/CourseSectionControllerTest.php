<?php

namespace Tests\Unit\Students;

use App\Http\Controllers\Students\CourseSectionController;
use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class CourseSectionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_a_course_without_associated_history(): void
    {
        [, $course] = $this->academicContext();

        $response = app(CourseSectionController::class)->destroy(Request::create('/', 'DELETE'), $course);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertDatabaseMissing('course_sections', ['id' => $course->id]);
    }

    public function test_it_blocks_deleting_a_course_with_enrollments(): void
    {
        [$year, $course] = $this->academicContext();
        $student = StudentProfile::query()->create([
            'first_name' => 'Ana',
            'last_name' => 'Prueba',
            'general_status' => 'activo',
        ]);
        StudentEnrollment::query()->create([
            'student_profile_id' => $student->id,
            'academic_year_id' => $year->id,
            'course_section_id' => $course->id,
            'enrollment_status' => 'regular',
            'snapshot_year_name' => '2026',
            'snapshot_level_name' => '1° Básico',
            'snapshot_section_name' => 'A',
            'snapshot_course_display_name' => '1° Básico A',
        ]);

        $response = app(CourseSectionController::class)->destroy(Request::create('/', 'DELETE'), $course);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertStringContainsString('1 matrículas', (string) $response->getData()->message);
        $this->assertDatabaseHas('course_sections', ['id' => $course->id]);
    }

    private function academicContext(): array
    {
        $year = AcademicYear::query()->create([
            'name' => '2026',
            'year' => 2026,
            'is_active' => true,
            'is_closed' => false,
        ]);
        $level = EducationLevel::query()->create([
            'name' => '1° Básico',
            'order' => 1,
            'type' => 'basica',
        ]);
        $course = CourseSection::query()->create([
            'academic_year_id' => $year->id,
            'education_level_id' => $level->id,
            'section_name' => 'A',
            'display_name' => '1° Básico A',
            'capacity' => 35,
            'active' => true,
        ]);

        return [$year, $course];
    }
}
