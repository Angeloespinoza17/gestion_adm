<?php

namespace Tests\Feature\SocialWork;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\Permission;
use App\Models\Pme\PmeStudentSepClassification;
use App\Models\Role;
use App\Models\SocialWork\Alert;
use App\Models\SocialWork\Intervention;
use App\Models\SocialWork\ProgramType;
use App\Models\SocialWork\Referral;
use App\Models\SocialWork\SocialCase;
use App\Models\SocialWork\StudentProgram;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\SocialWork\AlertService;
use Database\Seeders\SocialWorkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SocialWorkModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $socialWorker;

    private StudentProfile $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->socialWorker = User::factory()->create(['active' => true, 'user_type' => 'staff']);
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super administrador', 'active' => true]);
        $this->socialWorker->roles()->attach($role);
        $this->student = StudentProfile::factory()->create(['first_name' => 'Elena', 'last_name' => 'Soto']);
        Sanctum::actingAs($this->socialWorker);
    }

    public function test_case_creation_uses_master_student_and_creates_traceability(): void
    {
        $this->postJson('/api/social-work/cases', $this->casePayload())->assertCreated()->assertJsonPath('data.primary_student_id', $this->student->id);
        $case = SocialCase::firstOrFail();
        $this->assertMatchesRegularExpression('/^TS-\d{4}-\d{5}$/', $case->code);
        $this->assertTrue($case->students()->whereKey($this->student->id)->exists());
        $this->assertSame('borrador', $case->statusHistory()->first()->to_status);
    }

    public function test_invalid_transition_is_rejected(): void
    {
        $case = $this->createCase();
        $this->postJson("/api/social-work/cases/{$case->id}/change-status", ['status' => 'cerrado', 'reason' => 'Intento inválido'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_closure_requires_conclusion_and_reopening_preserves_it(): void
    {
        $case = $this->createCase(['status' => 'pendiente_cierre']);
        $this->postJson("/api/social-work/cases/{$case->id}/close", ['closure_result' => 'finalizado', 'closure_reason' => 'Objetivos cumplidos', 'final_risk_level' => 'bajo'])
            ->assertUnprocessable()->assertJsonValidationErrors('closure_conclusion');
        $this->postJson("/api/social-work/cases/{$case->id}/close", ['closure_conclusion' => 'Se completa intervención y se mantiene seguimiento preventivo.', 'closure_result' => 'finalizado', 'closure_reason' => 'Objetivos cumplidos', 'final_risk_level' => 'bajo'])->assertOk();
        $this->postJson("/api/social-work/cases/{$case->id}/reopen", ['reason' => 'Nuevos antecedentes relevantes', 'risk_level' => 'alto', 'priority' => 'alta', 'assigned_user_id' => $this->socialWorker->id, 'next_action' => 'Nueva entrevista'])->assertOk();
        $case->refresh();
        $this->assertSame('reabierto', $case->status);
        $this->assertSame('Se completa intervención y se mantiene seguimiento preventivo.', $case->closure_conclusion);
        $this->assertSame($case->closure_conclusion, $case->reopenings()->first()->previous_conclusion);
    }

    public function test_closed_case_rejects_ordinary_intervention(): void
    {
        $case = $this->createCase(['status' => 'cerrado', 'closure_conclusion' => 'Cerrado']);
        $this->postJson("/api/social-work/cases/{$case->id}/interventions", ['kind' => 'llamado', 'activity_date' => today()->toDateString(), 'objective' => 'Seguimiento', 'status' => 'finalizada', 'confidentiality' => 'restringido'])
            ->assertUnprocessable()->assertJsonValidationErrors('case_id');
    }

    public function test_attention_records_guardian_staff_participants_and_multiple_support_staff(): void
    {
        $this->student->update(['guardian_name' => 'María Soto']);
        $teacher = User::factory()->create(['active' => true, 'user_type' => 'staff', 'name' => 'Profesor Carlos']);
        $supportOne = User::factory()->create(['active' => true, 'user_type' => 'staff', 'name' => 'Orientadora Ana']);
        $supportTwo = User::factory()->create(['active' => true, 'user_type' => 'staff', 'name' => 'Inspectora Paula']);
        $case = $this->createCase();

        $this->postJson("/api/social-work/cases/{$case->id}/interventions", [
            'kind' => 'entrevista',
            'activity_date' => today()->toDateString(),
            'objective' => 'Coordinar apoyos para la estudiante',
            'status' => 'finalizada',
            'confidentiality' => 'restringido',
            'participant_types' => ['guardian', 'staff'],
            'participant_staff_ids' => [$teacher->id],
            'support_staff_ids' => [$supportOne->id, $supportTwo->id],
        ])->assertCreated();

        $intervention = Intervention::query()->latest('id')->firstOrFail();
        $participants = collect($intervention->participants);

        $this->assertSame('María Soto', $participants->firstWhere('type', 'guardian')['name']);
        $this->assertSame('Profesor Carlos', $participants->firstWhere('user_id', $teacher->id)['name']);
        $this->assertSame(
            [$supportOne->id, $supportTwo->id],
            $participants->where('role', 'support')->pluck('user_id')->values()->all(),
        );
        $this->assertSame(['guardian', 'staff'], $intervention->structured_data['participant_types']);
        $this->assertSame([$supportOne->id, $supportTwo->id], $intervention->structured_data['support_staff_ids']);

        $this->getJson("/api/social-work/cases/{$case->id}")
            ->assertOk()
            ->assertJsonPath('data.interventions.0.participants.0.type', 'guardian')
            ->assertJsonPath('data.interventions.0.participants.1.name', 'Profesor Carlos')
            ->assertJsonPath('data.interventions.0.participants.2.role', 'support');

        $this->postJson("/api/social-work/cases/{$case->id}/interventions", [
            'kind' => 'entrevista',
            'activity_date' => today()->toDateString(),
            'objective' => 'Registro inválido sin funcionario seleccionado',
            'status' => 'finalizada',
            'confidentiality' => 'restringido',
            'participant_types' => ['staff'],
        ])->assertUnprocessable()->assertJsonValidationErrors('participant_staff_ids');
    }

    public function test_social_worker_can_correct_finalized_interview_with_encrypted_revision_history(): void
    {
        $case = $this->createCase();
        $created = $this->postJson("/api/social-work/cases/{$case->id}/interventions", [
            'kind' => 'entrevista',
            'activity_date' => today()->toDateString(),
            'objective' => 'Entrevista inicial',
            'description' => 'Contenido original del acta social.',
            'status' => 'finalizada',
            'confidentiality' => 'restringido',
            'participant_types' => ['student'],
        ])->assertCreated();
        $interventionId = $created->json('data.id');
        $intervention = Intervention::query()->findOrFail($interventionId);

        $this->patchJson("/api/social-work/interventions/{$interventionId}", [
            'kind' => 'entrevista',
            'activity_date' => today()->toDateString(),
            'objective' => 'Entrevista inicial corregida',
            'description' => 'Contenido corregido del acta social.',
            'status' => 'finalizada',
            'confidentiality' => 'restringido',
            'participant_types' => ['student'],
            'change_reason' => 'Corrección acordada tras revisar el acta.',
            'record_updated_at' => $intervention->updated_at->toIso8601String(),
        ])->assertOk()
            ->assertJsonPath('data.description', 'Contenido corregido del acta social.')
            ->assertJsonPath('data.status', 'finalizada');

        $revision = DB::table('interview_record_revisions')->where([
            'module' => 'social_work',
            'record_id' => $interventionId,
        ])->first();
        $this->assertNotNull($revision);
        $before = json_decode(Crypt::decryptString($revision->before_payload), true);
        $this->assertSame('Contenido original del acta social.', $before['description']);
        $this->assertDatabaseHas('social_work_audit_events', [
            'action' => 'intervention.updated',
            'auditable_id' => $interventionId,
            'reason' => 'Corrección acordada tras revisar el acta.',
        ]);
    }

    public function test_referring_teacher_does_not_gain_case_access(): void
    {
        $case = $this->createCase(['confidentiality' => 'restringido']);
        $teacher = User::factory()->create(['active' => true, 'user_type' => 'staff']);
        Sanctum::actingAs($teacher);
        $this->getJson("/api/social-work/cases/{$case->id}")->assertForbidden();
    }

    public function test_alert_deduplication_key_prevents_duplicates(): void
    {
        $case = $this->createCase();
        $payload = ['deduplication_key' => 'test-risk-'.$case->id, 'type' => 'riesgo_asistencia', 'student_profile_id' => $this->student->id, 'case_id' => $case->id, 'severity' => 'alto', 'reason' => 'Revisión profesional requerida'];
        app(AlertService::class)->raise($payload);
        app(AlertService::class)->raise($payload);
        $this->assertSame(1, Alert::where('deduplication_key', $payload['deduplication_key'])->count());
    }

    public function test_seeder_assigns_operational_permissions_to_social_worker_and_full_access_to_super_admin(): void
    {
        $this->seed(SocialWorkSeeder::class);

        $allSocialWorkPermissionIds = Permission::query()
            ->where('slug', 'like', 'social_work.%')
            ->pluck('id')
            ->sort()
            ->values();

        $socialWorkerRole = Role::query()->where('slug', 'trabajador_social')->firstOrFail();
        $superAdminRole = Role::query()->where('slug', 'super_admin')->firstOrFail();

        $this->assertCount(31, $allSocialWorkPermissionIds);
        $this->assertCount(25, $socialWorkerRole->permissions()->where('slug', 'like', 'social_work.%')->get());
        $this->assertSame(
            $allSocialWorkPermissionIds->all(),
            $superAdminRole->permissions()->where('slug', 'like', 'social_work.%')->pluck('permissions.id')->sort()->values()->all(),
        );
        $this->assertTrue($socialWorkerRole->modules()->where('slug', 'social_work')->exists());
        $this->assertTrue($socialWorkerRole->modules()->where('slug', 'social_work_pickup_restrictions')->exists());
        $this->assertTrue($socialWorkerRole->permissions()->where('slug', 'social_work.pickup_restrictions.manage')->exists());
        $this->assertTrue($superAdminRole->modules()->where('slug', 'social_work')->exists());
    }

    public function test_support_matrix_consolidates_master_health_programs_sep_and_medical_alerts(): void
    {
        $this->seed(SocialWorkSeeder::class);
        $year = AcademicYear::query()->create([
            'name' => 'Año escolar 2026', 'year' => 2026, 'starts_at' => '2026-03-01',
            'ends_at' => '2026-12-31', 'is_active' => true, 'is_closed' => false,
        ]);
        $level = EducationLevel::query()->create(['name' => '5° básico prueba social', 'type' => 'basica', 'order' => 995, 'active' => true]);
        $course = CourseSection::query()->create([
            'academic_year_id' => $year->id, 'education_level_id' => $level->id,
            'section_name' => 'A', 'display_name' => '5° básico A', 'active' => true,
        ]);
        StudentEnrollment::query()->create([
            'student_profile_id' => $this->student->id, 'academic_year_id' => $year->id,
            'course_section_id' => $course->id, 'enrollment_status' => 'regular',
            'enrolled_at' => '2026-03-01', 'snapshot_year_name' => $year->name,
            'snapshot_level_name' => $level->name, 'snapshot_section_name' => 'A',
            'snapshot_course_display_name' => $course->display_name,
        ]);
        $this->student->update([
            'health_insurance' => 'FONASA B', 'has_chronic_illness' => true,
            'chronic_illness_details' => 'Asma', 'health_observations' => 'Control periódico.',
            'is_pie_participant' => true,
        ]);
        $proRetention = ProgramType::query()->where('code', 'pro_retencion')->firstOrFail();
        StudentProgram::query()->create([
            'student_profile_id' => $this->student->id, 'program_type_id' => $proRetention->id,
            'school_year' => 2026, 'status' => 'vigente', 'confidentiality' => 'interno',
            'created_by' => $this->socialWorker->id, 'updated_by' => $this->socialWorker->id,
        ]);
        PmeStudentSepClassification::query()->create([
            'student_profile_id' => $this->student->id, 'course_section_id' => $course->id,
            'academic_year_id' => $year->id, 'classification' => 'prioritaria', 'state' => 'vigente',
        ]);

        $this->getJson('/api/social-work/support-matrix?search=Elena')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.current_enrollment.course_section.display_name', '5° básico A')
            ->assertJsonPath('data.0.health.insurance', 'FONASA B')
            ->assertJsonPath('data.0.health.chronic_illness_details', 'Asma')
            ->assertJsonPath('data.0.program_flags.pro_retencion', true)
            ->assertJsonPath('data.0.sep_classification', 'prioritaria')
            ->assertJsonPath('data.0.protection_summary.pie', true)
            ->assertJsonPath('data.0.medical_alert', true);

        $this->putJson("/api/social-work/support-matrix/{$this->student->id}", [
            'is_pie_participant' => true,
            'pie_permanence_type' => 'permanente',
            'pie_diagnosis' => 'Antecedente institucional vigente',
            'sep_classification' => 'preferente',
            'junaeb' => true,
            'pro_retencion' => false,
            'external_program' => true,
            'external_program_name' => 'Programa municipal de apoyo',
        ])->assertOk()->assertJsonPath('message', 'Situación social actualizada con trazabilidad.');

        $this->assertSame('preferente', PmeStudentSepClassification::query()
            ->where('student_profile_id', $this->student->id)->where('academic_year_id', $year->id)->value('classification'));
        $this->assertTrue(StudentProgram::query()->where('student_profile_id', $this->student->id)
            ->whereHas('programType', fn ($query) => $query->where('code', 'junaeb'))->where('status', 'vigente')->exists());
        $this->assertTrue(StudentProgram::query()->where('student_profile_id', $this->student->id)
            ->whereHas('programType', fn ($query) => $query->where('code', 'otro_externo'))->where('notes', 'Programa municipal de apoyo')->exists());
        $this->assertFalse(StudentProgram::query()->where('student_profile_id', $this->student->id)
            ->whereHas('programType', fn ($query) => $query->where('code', 'pro_retencion'))->where('status', 'vigente')->exists());
        $this->assertDatabaseHas('social_work_audit_events', ['action' => 'student_support.updated', 'auditable_id' => $this->student->id]);
    }

    public function test_junaeb_student_options_use_current_enrollments_without_requiring_general_student_permission(): void
    {
        $year = AcademicYear::query()->create([
            'name' => 'Año escolar 2026', 'year' => 2026, 'starts_at' => '2026-03-01',
            'ends_at' => '2026-12-31', 'is_active' => true, 'is_closed' => false,
        ]);
        $eligibleLevel = EducationLevel::query()->create([
            'name' => '5° básico catálogo JUNAEB', 'type' => 'basica', 'order' => 996,
        ]);
        $ineligibleLevel = EducationLevel::query()->create([
            'name' => '4° básico catálogo JUNAEB', 'type' => 'basica', 'order' => 997,
        ]);
        $eligibleCourse = CourseSection::query()->create([
            'academic_year_id' => $year->id, 'education_level_id' => $eligibleLevel->id,
            'section_name' => 'A', 'display_name' => '5° básico A', 'active' => true,
        ]);
        $ineligibleCourse = CourseSection::query()->create([
            'academic_year_id' => $year->id, 'education_level_id' => $ineligibleLevel->id,
            'section_name' => 'B', 'display_name' => '4° básico B', 'active' => true,
        ]);

        $this->student->update(['general_status' => 'activo', 'guardian_phone' => '+56 9 1111 2222']);
        $otherStudent = StudentProfile::factory()->create([
            'first_name' => 'Amanda', 'last_name' => 'Rojas', 'general_status' => 'activo',
        ]);
        foreach ([[$this->student, $eligibleCourse, 'regular'], [$otherStudent, $ineligibleCourse, 'matriculada']] as [$student, $course, $status]) {
            StudentEnrollment::query()->create([
                'student_profile_id' => $student->id, 'academic_year_id' => $year->id,
                'course_section_id' => $course->id, 'enrollment_status' => $status,
                'enrolled_at' => '2026-03-01', 'snapshot_year_name' => $year->name,
                'snapshot_level_name' => $course->educationLevel->name,
                'snapshot_section_name' => $course->section_name,
                'snapshot_course_display_name' => $course->display_name,
            ]);
        }

        $junaebPermission = Permission::query()->where('slug', 'social_work.junaeb.manage')->firstOrFail();
        $limitedRole = Role::query()->create(['slug' => 'junaeb_options_test', 'name' => 'JUNAEB prueba', 'active' => true]);
        $limitedRole->permissions()->attach($junaebPermission->id);
        $limitedUser = User::factory()->create(['active' => true, 'user_type' => 'staff']);
        $limitedUser->roles()->attach($limitedRole);
        Sanctum::actingAs($limitedUser);

        $this->getJson('/api/social-work/junaeb/student-options')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('academic_year.year', 2026)
            ->assertJsonPath('data.0.id', $otherStudent->id)
            ->assertJsonPath('data.0.transport_pass_eligible', false)
            ->assertJsonPath('data.1.id', $this->student->id)
            ->assertJsonPath('data.1.current_enrollment.course_section.display_name', '5° básico A')
            ->assertJsonPath('data.1.transport_pass_eligible', true)
            ->assertJsonMissingPath('data.1.guardian_phone');

        $this->getJson('/api/social-work/students')->assertForbidden();
    }

    public function test_inspectoria_coordination_can_submit_referrals_without_accessing_other_senders_records(): void
    {
        Referral::query()->create([
            'student_profile_id' => $this->student->id, 'referral_date' => '2026-08-16',
            'source_unit' => 'Dirección', 'reason' => 'Antecedente reservado previo',
            'urgency' => 'normal', 'status' => 'enviada', 'confidentiality' => 'restringido',
            'created_by' => $this->socialWorker->id, 'updated_by' => $this->socialWorker->id,
        ]);
        $coordinator = User::factory()->create(['active' => true, 'user_type' => 'staff']);
        $role = Role::query()->where('slug', 'coordinador_inspectoria')->firstOrFail();
        $coordinator->roles()->attach($role);
        Sanctum::actingAs($coordinator);

        $this->getJson('/api/social-work/referral-students')->assertOk()->assertJsonPath('data.0.id', $this->student->id);
        $response = $this->postJson('/api/social-work/referrals', [
            'student_profile_id' => $this->student->id, 'referral_date' => '2026-08-16',
            'source_unit' => 'Inspectoría', 'source_person' => 'Coordinación de Inspectoría',
            'reason' => 'Solicitud de acompañamiento', 'description' => 'Antecedentes observables.',
            'urgency' => 'alta', 'immediate_risk' => false, 'status' => 'enviada',
            'confidentiality' => 'restringido',
        ])->assertCreated()->assertJsonPath('data.creator.name', $coordinator->name);

        $this->getJson('/api/social-work/referrals')->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.creator.name', $coordinator->name);
        $this->getJson('/api/social-work/cases')->assertForbidden();

        $notification = $this->socialWorker->notifications()
            ->get()
            ->first(fn ($item) => ($item->data['event_type'] ?? null) === 'social_work.referral.created');
        $this->assertNotNull($notification);
        $this->assertSame($response->json('data.id'), $notification->data['event']['resource']['id']);
        $this->assertSame('/social-work/referrals', $notification->data['action_url']);
        $this->assertStringNotContainsString('Antecedentes observables', $notification->data['message']);
    }

    public function test_assigned_professional_can_see_a_referral_created_by_another_user(): void
    {
        $submitPermission = Permission::query()->where('slug', 'social_work.referrals.submit')->firstOrFail();
        $professionalRole = Role::query()->firstOrCreate(
            ['slug' => 'trabajador_social'],
            ['name' => 'Trabajador/a Social', 'active' => true],
        );
        $professionalRole->permissions()->syncWithoutDetaching([$submitPermission->id]);
        $professional = User::factory()->create(['active' => true, 'user_type' => 'staff']);
        $professional->roles()->attach($professionalRole);

        $sender = User::factory()->create(['active' => true, 'user_type' => 'staff']);
        $assigned = Referral::query()->create([
            'student_profile_id' => $this->student->id,
            'referral_date' => '2026-08-16',
            'source_unit' => 'Inspectoría',
            'reason' => 'Derivación psicosocial',
            'urgency' => 'normal',
            'status' => 'enviada',
            'confidentiality' => 'restringido',
            'assigned_user_id' => $professional->id,
            'created_by' => $sender->id,
            'updated_by' => $sender->id,
        ]);
        Referral::query()->create([
            'student_profile_id' => $this->student->id,
            'referral_date' => '2026-08-16',
            'source_unit' => 'Dirección',
            'reason' => 'Derivación de otra profesional',
            'urgency' => 'normal',
            'status' => 'enviada',
            'confidentiality' => 'restringido',
            'assigned_user_id' => $sender->id,
            'created_by' => $sender->id,
            'updated_by' => $sender->id,
        ]);

        Sanctum::actingAs($professional);

        $this->getJson('/api/social-work/referrals')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $assigned->id)
            ->assertJsonPath('data.0.assigned_user.id', $professional->id);
    }

    public function test_historical_intervention_can_predate_case_opening_and_complete_export_is_audited(): void
    {
        $case = $this->createCase(['opened_on' => today()->toDateString()]);
        $historicalDate = today()->subYears(3)->toDateString();

        $this->postJson("/api/social-work/cases/{$case->id}/interventions", [
            'kind' => 'accion',
            'activity_date' => $historicalDate,
            'objective' => 'Incorporar antecedente histórico del acompañamiento.',
            'description' => 'Actuación anterior a la apertura administrativa del caso.',
            'status' => 'finalizada',
            'confidentiality' => 'restringido',
            'participant_types' => ['student'],
        ])->assertCreated()
            ->assertJsonPath('data.activity_date', $historicalDate);

        $this->postJson("/api/social-work/cases/{$case->id}/export")
            ->assertOk()
            ->assertJsonPath('data.id', $case->id)
            ->assertJsonPath('data.interventions.0.activity_date', $historicalDate)
            ->assertJsonPath('data.interventions.0.description', 'Actuación anterior a la apertura administrativa del caso.')
            ->assertJsonStructure(['data' => ['status_history', 'reopenings', 'interventions', 'alerts', 'referrals', 'protocols', 'reports', 'documents', 'commitments', 'requested_information', 'risk_assessments']]);

        $this->assertDatabaseHas('social_work_audit_events', [
            'action' => 'case.pdf_exported',
            'auditable_id' => $case->id,
            'user_id' => $this->socialWorker->id,
        ]);
    }

    private function createCase(array $overrides = []): SocialCase
    {
        $apiOverrides = array_intersect_key($overrides, array_flip(['title', 'reason', 'priority', 'risk_level', 'confidentiality', 'responsible_user_id']));
        $this->postJson('/api/social-work/cases', array_merge($this->casePayload(), $apiOverrides))->assertCreated();
        $case = SocialCase::latest('id')->firstOrFail();
        if ($overrides !== []) {
            $case->update($overrides);
        }

        return $case->fresh();
    }

    private function casePayload(): array
    {
        return ['primary_student_id' => $this->student->id, 'title' => 'Apoyo familiar', 'reason' => 'Solicitud de acompañamiento', 'priority' => 'media', 'risk_level' => 'sin_evaluar', 'confidentiality' => 'restringido', 'status' => 'borrador', 'responsible_user_id' => $this->socialWorker->id];
    }
}
