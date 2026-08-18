<?php

namespace Database\Factories\Psychology;

use App\Models\Psychology\PsychologyCase;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PsychologyCaseFactory extends Factory
{
    protected $model = PsychologyCase::class;

    public function definition(): array
    {
        return ['code' => 'PSI-'.now()->format('Y').'-'.$this->faker->unique()->numerify('######'), 'student_profile_id' => StudentProfile::factory(), 'responsible_user_id' => User::factory(), 'status' => 'open', 'priority' => 'medium', 'confidentiality' => 'psychology_team', 'general_reason' => 'Acompañamiento psicoeducativo', 'opened_at' => now(), 'last_activity_at' => now()];
    }
}
