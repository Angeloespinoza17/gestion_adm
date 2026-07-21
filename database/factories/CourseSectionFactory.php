<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseSectionFactory extends Factory
{
    protected $model = CourseSection::class;

    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(), 'education_level_id' => EducationLevel::factory(),
            'section_name' => 'A', 'display_name' => 'Curso prueba '.$this->faker->unique()->numberBetween(100, 999),
            'capacity' => 40, 'active' => true,
        ];
    }
}
