<?php

namespace Database\Factories\Psychology;

use App\Models\Psychology\PsychologyReferral;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PsychologyReferralFactory extends Factory
{
    protected $model = PsychologyReferral::class;

    public function definition(): array
    {
        return ['student_profile_id' => StudentProfile::factory(), 'referred_by_user_id' => User::factory(), 'origin_area' => 'inspectoria', 'status' => 'draft', 'suggested_urgency' => 'medium', 'primary_reason' => 'Bienestar emocional', 'observed_facts' => $this->faker->sentence(14), 'immediate_response_needed' => false, 'guardian_informed' => false, 'guardian_contact_status' => 'not_contacted', 'purpose_declaration_accepted' => true];
    }
}
