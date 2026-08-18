<?php

namespace Database\Seeders;

use App\Models\Psychology\PsychologyCase;
use App\Models\Psychology\PsychologyReferral;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\Support\PreventsProductionSeeding;
use Illuminate\Database\Seeder;

class PsychologyDemoSeeder extends Seeder
{
    use PreventsProductionSeeding;

    public function run(): void
    {
        $this->preventProductionSeeding();
        $psychologist = User::query()->whereHas('roles', fn ($q) => $q->where('slug', 'psicologo'))->first();
        $inspector = User::query()->whereHas('roles', fn ($q) => $q->where('slug', 'inspectoria'))->first();
        $student = StudentProfile::query()->where('general_status', 'activo')->first();
        if (! $psychologist || ! $inspector || ! $student) {
            $this->command?->warn('Se requieren usuarios de Psicología, Inspectoría y una estudiante activa.');

            return;
        }
        $referral = PsychologyReferral::factory()->create(['student_profile_id' => $student->id, 'referred_by_user_id' => $inspector->id, 'assigned_user_id' => $psychologist->id, 'code' => 'PSI-D-'.now()->format('Y').'-DEMO01', 'status' => 'accepted', 'referred_at' => now()->subDay(), 'first_reviewed_at' => now()]);
        PsychologyCase::factory()->create(['student_profile_id' => $student->id, 'origin_referral_id' => $referral->id, 'responsible_user_id' => $psychologist->id, 'code' => 'PSI-'.now()->format('Y').'-DEMO01']);
    }
}
