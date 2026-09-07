<?php

namespace Tests\Feature\LibroDigital;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\LibroDigital\Book;
use App\Models\LibroDigital\FeatureFlag;
use App\Models\LibroDigital\RegulatoryProfile;
use App\Models\LibroDigital\School;
use App\Models\Role;
use App\Models\Schedule\ScheduleSubject;
use App\Models\Staff;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibroDigitalSupplementaryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_assessment_results_require_the_roster_and_close_as_an_immutable_snapshot(): void
    {
        [$user, , , , , , , $student, $book] = $this->openBookContext();

        $assessment = $this->actingAs($user)->withHeader('Idempotency-Key', 'lcd-assessment-create-0001')
            ->postJson('/api/libro-digital/v1/books/'.$book->id.'/assessments', [
                'name' => 'Prueba de unidad',
                'description' => 'Evaluación sumativa',
                'assessment_type' => 'summative',
                'scheduled_on' => '2035-04-10',
                'weighting' => 25,
                'maximum_score' => 40,
                'grading_scale' => '1_to_7',
                'curriculum_objective_ids' => [],
            ])->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.expected_results_count', 1);

        $assessmentId = $assessment->json('data.id');
        $this->getJson('/api/libro-digital/v1/books/'.$book->id.'/assessments')->assertOk()->assertJsonPath('data.0.id', $assessmentId);
        $this->getJson('/api/libro-digital/v1/assessments/'.$assessmentId)->assertOk()->assertJsonPath('data.name', 'Prueba de unidad');
        $updated = $this->withHeaders(['Idempotency-Key' => 'lcd-assessment-update-0001', 'If-Match' => '1'])
            ->patchJson('/api/libro-digital/v1/assessments/'.$assessmentId, [
                'name' => 'Prueba de unidad corregida',
                'lock_version' => 1,
            ])->assertOk()->assertJsonPath('data.lock_version', 2);

        $results = $this->withHeaders(['Idempotency-Key' => 'lcd-assessment-results-0001', 'If-Match' => '2'])
            ->putJson('/api/libro-digital/v1/assessments/'.$assessmentId.'/results', [
                'results' => [[
                    'student_profile_id' => $student->id,
                    'raw_score' => 36,
                    'numeric_value' => 6.2,
                    'observation' => 'Logro esperado',
                ]],
                'lock_version' => 2,
            ])->assertOk()->assertJsonPath('data.status', 'results_open')->assertJsonPath('data.results_count', 1)
            ->assertJsonPath('data.lock_version', 3);

        $this->withHeaders(['Idempotency-Key' => 'lcd-assessment-close-0001', 'If-Match' => '3'])
            ->postJson('/api/libro-digital/v1/assessments/'.$assessmentId.'/close', ['lock_version' => 3])
            ->assertOk()->assertJsonPath('data.status', 'closed')->assertJsonPath('data.results.0.numeric_value', 6.2);

        $this->assertDatabaseHas('lcd_grade_closures', ['book_id' => $book->id, 'scope' => 'assessment', 'status' => 'closed']);
        $this->assertDatabaseHas('lcd_record_revisions', ['revisable_type' => 'App\\Models\\LibroDigital\\StudentResult', 'revision' => 1]);
        $this->withHeaders(['Idempotency-Key' => 'lcd-assessment-closed-update-0001', 'If-Match' => '4'])
            ->patchJson('/api/libro-digital/v1/assessments/'.$assessmentId, ['name' => 'Mutación indebida', 'lock_version' => 4])
            ->assertStatus(409)->assertJsonPath('code', 'LCD_ASSESSMENT_IMMUTABLE');
        $this->assertSame(3, $results->json('data.lock_version'));
    }

    public function test_pie_and_coexistence_are_roster_scoped_encrypted_and_revisioned(): void
    {
        [$user, , , , , $teacher, , $student, $book] = $this->openBookContext();
        $outsider = StudentProfile::factory()->create(['registered_name' => 'Fuera de nómina']);
        $base = [
            'book_id' => $book->id,
            'occurred_on' => '2035-04-11',
            'title' => 'Seguimiento autorizado',
            'category' => 'collaborative_work',
            'description' => 'Antecedente sensible de apoyo',
            'actions' => 'Acuerdo profesional',
            'status' => 'open',
            'confidentiality_level' => 'restricted',
            'professional_staff_id' => $teacher->id,
        ];

        $this->actingAs($user)->withHeader('Idempotency-Key', 'lcd-pie-outsider-0001')
            ->postJson('/api/libro-digital/v1/books/'.$book->id.'/pie', [...$base, 'student_profile_id' => $outsider->id])
            ->assertUnprocessable()->assertJsonPath('code', 'LCD_PIE_STUDENT_OUTSIDE_ROSTER');

        $pie = $this->withHeader('Idempotency-Key', 'lcd-pie-create-0001')
            ->postJson('/api/libro-digital/v1/books/'.$book->id.'/pie', [...$base, 'student_profile_id' => $student->id])
            ->assertCreated()->assertJsonPath('data.description', 'Antecedente sensible de apoyo');
        $pieId = $pie->json('data.id');
        $this->assertNotSame('Antecedente sensible de apoyo', (string) \DB::table('lcd_pie_support_records')->where('id', $pieId)->value('details_encrypted'));
        $this->getJson('/api/libro-digital/v1/books/'.$book->id.'/pie')->assertOk()->assertJsonPath('data.0.student_profile_id', $student->id);
        $this->withHeaders(['Idempotency-Key' => 'lcd-pie-update-0001', 'If-Match' => '1'])
            ->patchJson('/api/libro-digital/v1/pie/'.$pieId, ['description' => 'Seguimiento actualizado', 'lock_version' => 1])
            ->assertOk()->assertJsonPath('data.description', 'Seguimiento actualizado')->assertJsonPath('data.revision', 2);

        $this->withHeader('Idempotency-Key', 'lcd-coexistence-outsider-0001')
            ->postJson('/api/libro-digital/v1/students/'.$outsider->id.'/coexistence', [...$base, 'student_profile_id' => $outsider->id])
            ->assertUnprocessable()->assertJsonPath('code', 'LCD_COEXISTENCE_STUDENT_OUTSIDE_ROSTER');
        $entry = $this->withHeader('Idempotency-Key', 'lcd-coexistence-create-0001')
            ->postJson('/api/libro-digital/v1/students/'.$student->id.'/coexistence', [...$base, 'student_profile_id' => $student->id])
            ->assertCreated()->assertJsonPath('data.student_profile_id', $student->id);
        $entryId = $entry->json('data.id');
        $this->getJson('/api/libro-digital/v1/students/'.$student->id.'/coexistence?academic_year_id='.$book->academic_year_id)
            ->assertOk()->assertJsonPath('data.0.description', 'Antecedente sensible de apoyo');
        $this->withHeaders(['Idempotency-Key' => 'lcd-coexistence-update-0001', 'If-Match' => '1'])
            ->patchJson('/api/libro-digital/v1/coexistence/'.$entryId, ['actions' => 'Medida actualizada', 'lock_version' => 1])
            ->assertOk()->assertJsonPath('data.actions', 'Medida actualizada')->assertJsonPath('data.revision', 2);
    }

    public function test_absence_case_actions_and_resolution_preserve_a_revisioned_history(): void
    {
        [$user, , , , , , , $student, $book] = $this->openBookContext();
        $case = $this->actingAs($user)->withHeader('Idempotency-Key', 'lcd-absence-create-0001')
            ->postJson('/api/libro-digital/v1/absence-cases', [
                'book_id' => $book->id,
                'academic_year_id' => $book->academic_year_id,
                'course_section_id' => $book->course_section_id,
                'student_profile_id' => $student->id,
                'occurred_on' => '2035-04-12',
                'title' => 'Ausencia prolongada',
                'category' => 'high',
                'description' => 'Tres jornadas consecutivas',
                'actions' => 'Contactar a apoderado',
                'status' => 'open',
            ])->assertCreated()->assertJsonPath('data.status', 'open')->assertJsonPath('data.lock_version', 1);
        $caseId = $case->json('data.id');

        $this->getJson('/api/libro-digital/v1/absence-cases?book_id='.$book->id)
            ->assertOk()->assertJsonPath('data.0.description', 'Tres jornadas consecutivas');
        $this->withHeaders(['Idempotency-Key' => 'lcd-absence-action-0001', 'If-Match' => '1'])
            ->postJson('/api/libro-digital/v1/absence-cases/'.$caseId.'/actions', [
                'description' => 'Contacto telefónico realizado',
                'occurred_at' => '2035-04-12T15:30:00.000-03:00',
                'lock_version' => 1,
            ])->assertOk()->assertJsonPath('data.status', 'contacted')->assertJsonPath('data.lock_version', 2)
            ->assertJsonPath('data.actions_count', 2);
        $this->withHeaders(['Idempotency-Key' => 'lcd-absence-resolve-0001', 'If-Match' => '2'])
            ->postJson('/api/libro-digital/v1/absence-cases/'.$caseId.'/resolve', [
                'resolution' => 'Trayectoria restablecida y seguimiento acordado.',
                'lock_version' => 2,
            ])->assertOk()->assertJsonPath('data.status', 'resolved')->assertJsonPath('data.lock_version', 3)
            ->assertJsonPath('data.resolution', 'Trayectoria restablecida y seguimiento acordado.');

        $this->assertDatabaseCount('lcd_absence_case_actions', 3);
        $this->assertDatabaseHas('lcd_record_revisions', ['revisable_type' => 'App\\Models\\LibroDigital\\AbsenceCase', 'revision' => 3]);
        $this->assertNotSame('Trayectoria restablecida y seguimiento acordado.', (string) \DB::table('lcd_absence_cases')->where('id', $caseId)->value('closure_reason'));
    }

    public function test_parvularia_endpoints_fail_closed_then_allow_profiled_planning_and_evaluation(): void
    {
        [$user, $school, , , , $teacher, , , $book] = $this->openBookContext(true);

        $this->actingAs($user)->getJson('/api/libro-digital/v1/parvularia/books/'.$book->id)
            ->assertStatus(503)->assertJsonPath('code', 'LCD_PARVULARIA_FEATURE_DISABLED');
        FeatureFlag::query()->create([
            'school_id' => $school->id,
            'scope_key' => 'school:'.$school->id,
            'code' => 'lcd_parvularia_enabled',
            'enabled' => true,
        ]);

        $this->getJson('/api/libro-digital/v1/parvularia/books/'.$book->id)
            ->assertOk()->assertJsonPath('data.education_type', 'parvularia');
        $payload = [
            'occurred_on' => '2035-04-13',
            'title' => 'Experiencia del entorno',
            'category' => 'learning_experience',
            'description' => 'Exploración guiada del entorno natural.',
            'actions' => 'Documentación pedagógica y retroalimentación.',
            'status' => 'open',
            'professional_staff_id' => $teacher->id,
        ];
        $this->withHeader('Idempotency-Key', 'lcd-parv-plan-0001')
            ->postJson('/api/libro-digital/v1/parvularia/books/'.$book->id.'/planning', $payload)
            ->assertCreated()->assertJsonPath('data.title', 'Experiencia del entorno');
        $this->getJson('/api/libro-digital/v1/parvularia/books/'.$book->id.'/planning')
            ->assertOk()->assertJsonPath('data.0.description', 'Exploración guiada del entorno natural.');
        $this->withHeader('Idempotency-Key', 'lcd-parv-evaluation-0001')
            ->postJson('/api/libro-digital/v1/parvularia/books/'.$book->id.'/evaluations', $payload)
            ->assertCreated()->assertJsonPath('data.scope', 'group')->assertJsonPath('data.description', 'Exploración guiada del entorno natural.');
        $this->getJson('/api/libro-digital/v1/parvularia/books/'.$book->id.'/evaluations')
            ->assertOk()->assertJsonPath('data.0.scope', 'group');
    }

    public function test_early_withdrawal_reuses_porter_authorization_and_keeps_identifiers_encrypted(): void
    {
        [$user, , , , , , , $student, $book] = $this->openBookContext();
        $student->forceFill([
            'pickup_restriction' => true,
            'pickup_restriction_notes' => 'Requiere autorización excepcional.',
            'authorized_pickup_people' => [],
        ])->save();

        $response = $this->actingAs($user)->withHeader('Idempotency-Key', 'lcd-withdrawal-create-0001')
            ->postJson('/api/libro-digital/v1/books/'.$book->id.'/early-withdrawals', [
                'student_profile_id' => $student->id,
                'person_name' => 'Persona Prueba',
                'person_rut' => '11.111.111-1',
                'person_relationship' => 'familiar',
                'reason' => 'familiar',
                'occurred_at' => '2035-04-14T11:30:00-04:00',
            ])->assertCreated()->assertJsonPath('data.status', 'observed')
            ->assertJsonPath('data.requires_special_authorization', true)
            ->assertJsonPath('data.lock_version', 1);
        $id = $response->json('data.id');
        $porterId = \DB::table('lcd_early_withdrawals')->where('id', $id)->value('porter_student_withdrawal_id');
        $this->assertDatabaseHas('porter_authorization_requests', [
            'authorizable_type' => 'App\\Models\\PorterStudentWithdrawal',
            'authorizable_id' => $porterId,
            'status' => 'pendiente',
        ]);
        $this->assertNull(\DB::table('porter_student_withdrawals')->where('id', $porterId)->value('person_rut'));
        $ciphertext = (string) \DB::table('lcd_early_withdrawals')->where('id', $id)->value('withdrawal_snapshot_encrypted');
        $this->assertStringNotContainsString('11.111.111-1', $ciphertext);
        $notification = $user->notifications()->firstOrFail();
        $this->assertSame('withdrawal.created', $notification->data['event_type']);
        $this->assertSame($porterId, $notification->data['event']['resource']['id']);
        $this->assertSame('cnsc.operational-notification.v1', $notification->data['event']['schema']);
        $this->assertArrayNotHasKey('person_rut', $notification->data['event']['context']);

        $this->withHeaders(['Idempotency-Key' => 'lcd-withdrawal-return-0001', 'If-Match' => '1'])
            ->postJson('/api/libro-digital/v1/early-withdrawals/'.$id.'/return', [
                'returned_at' => '2035-04-14T12:30:00-04:00',
                'reason' => 'Retorno a la jornada.',
                'lock_version' => 1,
            ])->assertOk()->assertJsonPath('data.status', 'returned')->assertJsonPath('data.lock_version', 2);
        $this->assertDatabaseHas('lcd_record_revisions', [
            'revisable_type' => 'App\\Models\\LibroDigital\\EarlyWithdrawal',
            'revision' => 2,
        ]);
    }

    public function test_parvularia_late_arrival_records_fact_without_inventing_regulatory_classification(): void
    {
        [$user, $school, , , , , , $student, $book] = $this->openBookContext(true);
        FeatureFlag::query()->create([
            'school_id' => $school->id,
            'scope_key' => 'school:'.$school->id,
            'code' => 'lcd_parvularia_enabled',
            'enabled' => true,
        ]);

        $created = $this->actingAs($user)->withHeader('Idempotency-Key', 'lcd-late-arrival-create-0001')
            ->postJson('/api/libro-digital/v1/parvularia/books/'.$book->id.'/late-arrivals', [
                'student_profile_id' => $student->id,
                'arrival_at' => '2035-04-15T09:35:00-04:00',
                'justification' => 'Antecedente pedagógico sintético.',
                'source' => 'manual',
            ])->assertCreated()->assertJsonPath('data.status', 'recorded')
            ->assertJsonPath('data.minutes_late', null);
        $id = $created->json('data.id');
        $ciphertext = (string) \DB::table('lcd_late_arrivals')->where('id', $id)->value('justification_encrypted');
        $this->assertStringNotContainsString('Antecedente pedagógico sintético.', $ciphertext);
        $this->getJson('/api/libro-digital/v1/parvularia/books/'.$book->id.'/late-arrivals')
            ->assertOk()->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('meta.policy_notice', 'La clasificación horaria regulatoria permanece deshabilitada hasta contar con una regla oficial verificada.');
    }

    /** @return array{User, School, AcademicYear, CourseSection, ScheduleSubject, Staff, RegulatoryProfile, StudentProfile, Book} */
    private function openBookContext(bool $parvularia = false): array
    {
        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin', 'active' => true]);
        $user->roles()->syncWithoutDetaching([$role->id]);
        $school = School::query()->create(['rbd' => '12345-6', 'name' => 'Escuela Prueba', 'timezone' => 'America/Santiago', 'active' => true]);
        $year = AcademicYear::factory()->create(['year' => 2035, 'name' => '2035', 'starts_at' => '2035-03-01', 'ends_at' => '2035-12-20', 'is_active' => true]);
        $level = EducationLevel::factory()->create([
            'name' => $parvularia ? 'Nivel transición de prueba' : 'Quinto básico de prueba',
            'order' => $parvularia ? 105 : 505,
            'type' => $parvularia ? 'parvularia' : 'basica',
        ]);
        $course = CourseSection::factory()->create([
            'academic_year_id' => $year->id,
            'education_level_id' => $level->id,
            'display_name' => $parvularia ? 'NT2 A' : '5° Básico A',
            'section_name' => 'A',
        ]);
        $subject = ScheduleSubject::query()->create(['name' => 'Lenguaje', 'code' => 'LEN-05', 'area' => 'Lenguaje', 'color' => '#405189', 'active' => true]);
        $teacher = Staff::query()->create(['full_name' => 'Docente Prueba', 'rut' => '12.345.678-5', 'active' => true]);
        $profile = RegulatoryProfile::query()->create([
            'code' => $parvularia ? 'CL-LCD-PARV' : 'CL-LCD',
            'name' => 'Perfil normativo',
            'version' => '1.0',
            'effective_from' => '2030-01-01',
            'retention_years' => 6,
            'rules_snapshot' => $parvularia ? ['education_types' => ['parvularia']] : ['education_types' => ['basica', 'media']],
            'active' => true,
        ]);
        $school->academicYears()->attach($year->id, [
            'regulatory_profile_id' => $profile->id,
            'rbd_snapshot' => $school->rbd,
            'year_snapshot' => $year->year,
            'timezone_snapshot' => $school->timezone,
            'active' => true,
        ]);
        FeatureFlag::query()->create(['school_id' => $school->id, 'scope_key' => 'school:'.$school->id, 'code' => 'lcd_enabled', 'enabled' => true]);
        $student = StudentProfile::factory()->create(['registered_name' => 'Estudiante Prueba']);
        StudentEnrollment::query()->create([
            'student_profile_id' => $student->id,
            'academic_year_id' => $year->id,
            'course_section_id' => $course->id,
            'enrollment_status' => 'matriculada',
            'enrolled_at' => $year->starts_at,
            'snapshot_year_name' => $year->name,
            'snapshot_level_name' => $level->name,
            'snapshot_section_name' => $course->section_name,
            'snapshot_course_display_name' => $course->display_name,
        ]);

        $this->actingAs($user)->withHeader('Idempotency-Key', 'lcd-context-book-'.($parvularia ? 'parv' : 'regular'))
            ->postJson('/api/libro-digital/v1/books', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'course_section_id' => $course->id,
                'schedule_subject_id' => $subject->id,
                'teacher_staff_id' => $teacher->id,
                'normative_profile_id' => $profile->id,
                'name' => 'Libro de prueba',
                'modality' => $parvularia ? 'parvularia' : 'regular',
            ])->assertCreated();
        $book = Book::query()->firstOrFail();
        $book->forceFill(['status' => 'open', 'opened_at' => now('UTC')])->save();

        return [$user, $school, $year, $course, $subject, $teacher, $profile, $student, $book->fresh()];
    }
}
