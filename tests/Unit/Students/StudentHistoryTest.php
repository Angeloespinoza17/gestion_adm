<?php

namespace Tests\Unit\Students;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use Tests\TestCase;

class StudentHistoryTest extends TestCase
{
    public function test_it_builds_historical_snapshot_fields_from_course_and_year(): void
    {
        $year = new AcademicYear(['name' => '2026', 'year' => 2026]);
        $level = new EducationLevel(['name' => '7° básico', 'order' => 9, 'type' => 'basica']);
        $course = new CourseSection(['section_name' => 'A', 'display_name' => '7° básico A']);
        $course->setRelation('educationLevel', $level);

        $snapshot = StudentEnrollment::snapshotPayload($year, $course);

        $this->assertSame('2026', $snapshot['snapshot_year_name']);
        $this->assertSame('7° básico', $snapshot['snapshot_level_name']);
        $this->assertSame('A', $snapshot['snapshot_section_name']);
        $this->assertSame('7° básico A', $snapshot['snapshot_course_display_name']);
    }

    public function test_it_prefers_active_year_enrollment_when_resolving_current_course(): void
    {
        $student = new StudentProfile(['first_name' => 'Maria', 'last_name' => 'Perez']);
        $activeYear = new AcademicYear(['id' => 2, 'name' => '2027', 'year' => 2027, 'is_active' => true]);
        $previousYear = new AcademicYear(['id' => 1, 'name' => '2026', 'year' => 2026]);

        $enrollment2026 = new StudentEnrollment(['id' => 10, 'academic_year_id' => 1, 'snapshot_course_display_name' => '7° básico A']);
        $enrollment2026->setRelation('academicYear', $previousYear);

        $enrollment2027 = new StudentEnrollment(['id' => 11, 'academic_year_id' => 2, 'snapshot_course_display_name' => '8° básico A']);
        $enrollment2027->setRelation('academicYear', $activeYear);

        $student->setRelation('enrollments', collect([$enrollment2026, $enrollment2027]));

        $resolved = $student->preferredEnrollment($activeYear);

        $this->assertSame('8° básico A', $resolved?->snapshot_course_display_name);
    }

    public function test_it_can_resolve_the_enrollment_that_matches_the_selected_year_and_level(): void
    {
        $student = new StudentProfile(['first_name' => 'Maria', 'last_name' => 'Perez']);

        $levelSix = new EducationLevel(['id' => 6, 'name' => '6° básico', 'order' => 8, 'type' => 'basica']);
        $levelSeven = new EducationLevel(['id' => 7, 'name' => '7° básico', 'order' => 9, 'type' => 'basica']);

        $courseSix = new CourseSection([
            'id' => 60,
            'academic_year_id' => 1,
            'education_level_id' => 6,
            'section_name' => 'A',
            'display_name' => '6° básico A',
        ]);
        $courseSix->setRelation('educationLevel', $levelSix);

        $courseSeven = new CourseSection([
            'id' => 70,
            'academic_year_id' => 2,
            'education_level_id' => 7,
            'section_name' => 'A',
            'display_name' => '7° básico A',
        ]);
        $courseSeven->setRelation('educationLevel', $levelSeven);

        $enrollment2026 = new StudentEnrollment([
            'id' => 10,
            'academic_year_id' => 1,
            'course_section_id' => 60,
            'snapshot_course_display_name' => '6° básico A',
        ]);
        $enrollment2026->setRelation('courseSection', $courseSix);

        $enrollment2027 = new StudentEnrollment([
            'id' => 11,
            'academic_year_id' => 2,
            'course_section_id' => 70,
            'snapshot_course_display_name' => '7° básico A',
        ]);
        $enrollment2027->setRelation('courseSection', $courseSeven);

        $student->setRelation('enrollments', collect([$enrollment2027, $enrollment2026]));

        $resolved = $student->matchingEnrollment(1, null, 6, null);

        $this->assertSame('6° básico A', $resolved?->snapshot_course_display_name);
    }
}
