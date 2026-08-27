<?php

namespace Tests\Feature\Infirmary;

use App\Models\Infirmary\InfirmaryAttention;
use App\Models\Infirmary\InfirmaryAttentionCall;
use App\Models\Infirmary\InfirmaryAttentionFollowUp;
use App\Models\Infirmary\InfirmaryAttentionTreatment;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InfirmaryDashboardStatisticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-25 12:00:00', config('app.timezone')));

        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super administrador', 'active' => true],
        );
        $user->roles()->syncWithoutDetaching([$role->id]);
        Sanctum::actingAs($user);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_calculates_clinical_resolution_continuity_and_record_quality_statistics(): void
    {
        $student = StudentProfile::query()->create([
            'first_name' => 'Josefa',
            'last_name' => 'Contreras',
            'rut' => '26111222-3',
            'general_status' => 'activo',
        ]);
        $professional = User::factory()->create(['active' => true, 'name' => 'Enfermera Escolar']);

        $first = $this->attention($student, $professional, [
            'attended_at' => now()->subDays(2)->setTime(10, 10),
            'occurred_at' => now()->subDays(2)->setTime(10, 0),
            'attention_duration_minutes' => 20,
            'priority' => 'alta',
            'status' => 'finalizada',
            'finalized_at' => now()->subDays(2)->setTime(10, 30),
            'logbook' => 'Se realiza control y apoyo emocional.',
        ]);
        $second = $this->attention($student, $professional, [
            'attended_at' => now()->subDay()->setTime(11, 5),
            'occurred_at' => now()->subDay()->setTime(11, 0),
            'attention_duration_minutes' => 10,
            'priority' => 'media',
            'status' => 'abierta',
        ]);

        InfirmaryAttentionTreatment::query()->create([
            'attention_id' => $first->id,
            'treatment_categories' => ['fisico', 'emocional', 'derivacion'],
            'treatment_types' => ['reposo'],
            'derivation_type' => 'sala',
            'derivation_support_teams' => ['psicosocial'],
            'temperature' => 38.2,
            'oxygen_saturation' => 93,
            'emotional_support_required' => true,
            'emotional_duration_minutes' => 10,
        ]);
        InfirmaryAttentionCall::query()->create([
            'student_profile_id' => $student->id,
            'attention_id' => $first->id,
            'called_at' => now()->subDays(2)->setTime(10, 15),
            'person_contacted' => 'Apoderada',
            'relationship' => 'Madre',
            'call_status' => 'contesto',
            'duration_minutes' => 4,
        ]);
        InfirmaryAttentionCall::query()->create([
            'student_profile_id' => $student->id,
            'attention_id' => $second->id,
            'called_at' => now()->subDay()->setTime(11, 10),
            'person_contacted' => 'Apoderada',
            'relationship' => 'Madre',
            'call_status' => 'no_contesto',
            'duration_minutes' => 2,
        ]);
        InfirmaryAttentionFollowUp::query()->create([
            'attention_id' => $first->id,
            'followed_at' => now()->subDay()->setTime(9, 0),
            'comment' => 'Sin nuevos síntomas.',
            'status' => 'cerrado',
            'completed_at' => now()->subDay()->setTime(9, 5),
        ]);

        $response = $this->getJson('/api/infirmary/dashboard?period=mensual')->assertOk();

        $response
            ->assertJsonPath('metrics.attentions_total', 2)
            ->assertJsonPath('metrics.unique_students', 1)
            ->assertJsonPath('metrics.repeat_attentions_total', 1)
            ->assertJsonPath('metrics.recurrence_rate', 50)
            ->assertJsonPath('metrics.average_attentions_per_student', 2)
            ->assertJsonPath('metrics.high_priority_total', 1)
            ->assertJsonPath('metrics.high_priority_rate', 50)
            ->assertJsonPath('metrics.finalized_total', 1)
            ->assertJsonPath('metrics.completion_rate', 50)
            ->assertJsonPath('metrics.average_attention_minutes', 15)
            ->assertJsonPath('metrics.average_response_minutes', 7.5)
            ->assertJsonPath('metrics.treatment_coverage', 50)
            ->assertJsonPath('metrics.vital_signs_coverage', 50)
            ->assertJsonPath('metrics.fever_records_total', 1)
            ->assertJsonPath('metrics.low_oxygen_records_total', 1)
            ->assertJsonPath('metrics.emotional_support_attentions', 1)
            ->assertJsonPath('metrics.emotional_support_minutes', 10)
            ->assertJsonPath('metrics.call_effectiveness', 50)
            ->assertJsonPath('metrics.follow_up_resolution_rate', 100)
            ->assertJsonPath('record_quality.0.key', 'duration')
            ->assertJsonPath('record_quality.0.percentage', 100);

        $charts = $response->json('charts');
        $this->assertSame(2, collect($charts['priority_distribution'])->sum('total'));
        $this->assertSame(2, collect($charts['attention_statuses'])->sum('total'));
        $this->assertSame(1, collect($charts['student_recurrence'])->firstWhere('label', '2 atenciones')['total']);
        $this->assertSame(1, collect($charts['call_outcomes'])->firstWhere('label', 'contesto')['total']);
        $this->assertSame(1, collect($charts['follow_up_statuses'])->firstWhere('label', 'cerrado')['total']);
        $this->assertSame(2, collect($charts['professionals'])->firstWhere('label', 'Enfermera Escolar')['total']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function attention(StudentProfile $student, User $professional, array $overrides): InfirmaryAttention
    {
        return InfirmaryAttention::query()->create(array_merge([
            'subject_type' => InfirmaryAttention::SUBJECT_STUDENT,
            'student_profile_id' => $student->id,
            'attended_by_user_id' => $professional->id,
            'attention_category' => 'dolor_cabeza',
            'student_full_name_snapshot' => $student->first_name.' '.$student->last_name,
            'student_rut_snapshot' => $student->rut,
            'age_snapshot' => 12,
            'accompanied_by_type' => 'apoderado',
            'consultation_reason' => 'Malestar general',
            'priority' => 'media',
            'status' => 'abierta',
        ], $overrides));
    }
}
