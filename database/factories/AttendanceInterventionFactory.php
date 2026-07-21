<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Attendance\AttendanceIntervention;
use App\Models\CourseSection;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceInterventionFactory extends Factory
{
    protected $model = AttendanceIntervention::class;

    public function definition(): array
    {
        return [
            'folio' => 'ASI-TEST-'.$this->faker->unique()->numerify('######'),
            'academic_year_id' => AcademicYear::factory(),
            'course_section_id' => CourseSection::factory(),
            'student_profile_id' => StudentProfile::factory(),
            'status' => 'new',
            'description' => 'Intervención de asistencia para pruebas.',
            'opened_at' => now(),
            'due_on' => now()->addDays(5),
        ];
    }
}
