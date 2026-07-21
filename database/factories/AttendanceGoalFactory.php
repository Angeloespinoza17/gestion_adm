<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Attendance\AttendanceGoal;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceGoalFactory extends Factory
{
    protected $model = AttendanceGoal::class;

    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(),
            'name' => 'Meta '.$this->faker->words(2, true),
            'scope_type' => 'institution',
            'starts_on' => now()->startOfYear(),
            'ends_on' => now()->endOfYear(),
            'target_rate' => $this->faker->randomFloat(2, 88, 96),
            'status' => 'active',
            'justification' => 'Meta institucional de demostración.',
        ];
    }
}
