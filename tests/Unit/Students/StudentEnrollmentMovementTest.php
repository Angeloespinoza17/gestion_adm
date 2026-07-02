<?php

namespace Tests\Unit\Students;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\StudentEnrollmentMovement;
use Tests\TestCase;

class StudentEnrollmentMovementTest extends TestCase
{
    public function test_it_builds_movement_snapshot_fields_for_course_changes(): void
    {
        $year = new AcademicYear(['name' => '2026', 'year' => 2026]);

        $level = new EducationLevel(['name' => '7° básico', 'order' => 9, 'type' => 'basica']);

        $fromCourse = new CourseSection(['section_name' => 'A', 'display_name' => '7° básico A']);
        $fromCourse->setRelation('educationLevel', $level);

        $toCourse = new CourseSection(['section_name' => 'B', 'display_name' => '7° básico B']);
        $toCourse->setRelation('educationLevel', $level);

        $snapshot = StudentEnrollmentMovement::snapshotPayload($year, $fromCourse, $toCourse);

        $this->assertSame('2026', $snapshot['snapshot_year_name']);
        $this->assertSame('7° básico A', $snapshot['snapshot_from_course_display_name']);
        $this->assertSame('7° básico B', $snapshot['snapshot_to_course_display_name']);
    }
}
