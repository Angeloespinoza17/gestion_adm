<?php

namespace Tests\Feature\Inspectoria;

use App\Models\AcademicYear;
use App\Models\Cargo;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\Inspectoria\InspectoriaAttention;
use App\Models\Inspectoria\InspectoriaCourseAssignment;
use App\Models\Inspectoria\InspectoriaDailyLog;
use App\Models\Inspectoria\InspectoriaPass;
use App\Models\Inspectoria\InspectoriaPickupRestriction;
use App\Models\Library\BibliotecaPase;
use App\Models\Permission;
use App\Models\PorterStudentWithdrawal;
use App\Models\Role;
use App\Models\SocialWork\Referral;
use App\Models\Staff;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InspectoriaModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Staff $inspector;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-10 09:00:00');

        $this->inspector = Staff::query()->create([
            'full_name' => 'Inspectora de prueba',
            'rut' => '12.345.678-5',
            'status' => 'activo',
            'active' => true,
        ]);
        $this->user = User::factory()->create(['active' => true, 'staff_id' => $this->inspector->id]);
        $role = Role::query()->firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super administrador', 'active' => true],
        );
        $this->user->roles()->attach($role);
        Sanctum::actingAs($this->user);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_quick_attention_stores_checklists_and_student_snapshot(): void
    {
        [, , $student] = $this->academicContext();

        $this->postJson('/api/inspectoria/attentions', [
            'student_profile_id' => $student->id,
            'attended_at' => '2026-08-10 09:05:00',
            'request_types' => ['atraso', 'contacto_apoderado'],
            'actions_taken' => ['resuelta', 'apoderado_contactado'],
            'priority' => 'normal',
            'brief_note' => 'Apoderada justificó el atraso por control médico.',
            'guardian_notified' => true,
            'requires_follow_up' => false,
        ])->assertCreated()
            ->assertJsonPath('data.student_name_snapshot', 'María Pérez')
            ->assertJsonPath('data.request_types.0', 'atraso');

        $attention = InspectoriaAttention::query()->firstOrFail();
        $this->assertMatchesRegularExpression('/^INS-ATE-2026-\d{4}$/', $attention->attention_code);
        $this->assertSame($this->inspector->id, $attention->inspector_staff_id);
        $this->assertTrue($attention->guardian_notified);
    }

    public function test_quick_attention_psychosocial_referral_requires_and_stores_an_eligible_professional(): void
    {
        [, , $student] = $this->academicContext();
        $cargo = Cargo::query()->create([
            'name' => 'TRABAJADORA SOCIAL',
            'slug' => 'trabajadora-social',
            'active' => true,
        ]);
        $professionalStaff = Staff::query()->create([
            'full_name' => 'Daniela Profesional Social',
            'rut' => '15.555.555-5',
            'cargo_id' => $cargo->id,
            'status' => 'activo',
            'active' => true,
        ]);
        $professional = User::factory()->create([
            'name' => 'Daniela Profesional Social',
            'staff_id' => $professionalStaff->id,
            'active' => true,
        ]);

        $payload = [
            'student_profile_id' => $student->id,
            'attended_at' => '2026-08-10 09:10:00',
            'request_types' => ['orientacion'],
            'actions_taken' => ['derivacion_psicosocial'],
            'priority' => 'normal',
            'brief_note' => 'Se solicita evaluación del equipo psicosocial.',
            'guardian_notified' => false,
            'requires_follow_up' => false,
        ];

        $this->postJson('/api/inspectoria/attentions', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('psychosocial_referral_user_id');

        $this->postJson('/api/inspectoria/attentions', [
            ...$payload,
            'psychosocial_referral_user_id' => $professional->id,
        ])->assertCreated()
            ->assertJsonPath('data.psychosocial_referral_user_id', $professional->id)
            ->assertJsonPath('data.psychosocial_referral_name_snapshot', 'Daniela Profesional Social')
            ->assertJsonPath('data.psychosocial_referral_role_snapshot', 'Trabajadora social')
            ->assertJsonPath('data.requires_follow_up', true)
            ->assertJsonPath('data.social_work_referral.assigned_user.id', $professional->id)
            ->assertJsonPath('data.social_work_referral.status', 'enviada');

        $attention = InspectoriaAttention::query()->firstOrFail();
        $this->assertSame($professional->id, $attention->psychosocial_referral_user_id);
        $this->assertNotNull($attention->psychosocial_referred_at);

        $referral = Referral::query()->firstOrFail();
        $this->assertSame($attention->id, $referral->inspectoria_attention_id);
        $this->assertSame($student->id, $referral->student_profile_id);
        $this->assertSame($professional->id, $referral->assigned_user_id);
        $this->assertSame('Inspectoría', $referral->source_unit);
        $this->assertSame('enviada', $referral->status);
        $this->assertSame('Se solicita evaluación del equipo psicosocial.', $referral->description);

        $this->getJson('/api/inspectoria/catalogs')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $professional->id,
                'name' => 'Daniela Profesional Social',
                'profession' => 'trabajo_social',
                'profession_label' => 'Trabajadora social',
            ]);
    }

    public function test_course_assignment_prevents_duplicates_for_the_same_inspector_but_allows_shared_courses(): void
    {
        [$year, $course] = $this->academicContext();

        $payload = [
            'academic_year_id' => $year->id,
            'course_section_id' => $course->id,
            'inspector_staff_id' => $this->inspector->id,
            'physical_location' => 'Pabellón norte · segundo piso',
            'starts_on' => '2026-03-01',
            'ends_on' => null,
            'active' => true,
            'notes' => 'Curso trasladado desde el pabellón central.',
        ];

        $this->postJson('/api/inspectoria/course-assignments', $payload)
            ->assertCreated()
            ->assertJsonPath('data.physical_location', 'Pabellón norte · segundo piso');

        $this->postJson('/api/inspectoria/course-assignments', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('course_section_id');

        $otherInspector = Staff::query()->create([
            'full_name' => 'Segunda inspectora',
            'rut' => '16.666.666-6',
            'status' => 'activo',
            'active' => true,
        ]);

        $this->postJson('/api/inspectoria/course-assignments', [
            ...$payload,
            'inspector_staff_id' => $otherInspector->id,
        ])->assertCreated();

        $this->assertSame(2, InspectoriaCourseAssignment::query()->count());
    }

    public function test_daily_log_catalog_only_exposes_enabled_courses_from_the_current_academic_year(): void
    {
        [$currentYear, $currentCourse] = $this->academicContext();
        $level = EducationLevel::query()->findOrFail($currentCourse->education_level_id);
        $currentYear->update(['is_active' => false]);
        $historicalYear = AcademicYear::query()->create([
            'name' => 'Año escolar 2025', 'year' => 2025,
            'starts_at' => '2025-03-01', 'ends_at' => '2025-12-31',
            'is_active' => true, 'is_closed' => true,
        ]);
        $historicalCourse = CourseSection::query()->create([
            'academic_year_id' => $historicalYear->id, 'education_level_id' => $level->id,
            'section_name' => 'A', 'display_name' => $currentCourse->display_name, 'active' => true,
        ]);
        $disabledCurrentCourse = CourseSection::query()->create([
            'academic_year_id' => $currentYear->id, 'education_level_id' => $level->id,
            'section_name' => 'B', 'display_name' => '2° medio B', 'active' => false,
        ]);

        $this->getJson('/api/inspectoria/catalogs')
            ->assertOk()
            ->assertJsonPath('active_academic_year_id', $historicalYear->id)
            ->assertJsonPath('current_academic_year_id', $currentYear->id)
            ->assertJsonCount(1, 'current_courses')
            ->assertJsonPath('current_courses.0.id', $currentCourse->id)
            ->assertJsonMissingPath('current_courses.1')
            ->assertJsonFragment(['id' => $historicalCourse->id])
            ->assertJsonMissing(['id' => $disabledCurrentCourse->id]);
    }

    public function test_bulk_assignment_skips_courses_with_current_assignments_and_creates_the_available_ones(): void
    {
        [$year, $course] = $this->academicContext();
        $level = EducationLevel::query()->findOrFail($course->education_level_id);
        $secondCourse = CourseSection::query()->create([
            'academic_year_id' => $year->id,
            'education_level_id' => $level->id,
            'section_name' => 'B',
            'display_name' => '2° medio B',
            'active' => true,
        ]);

        $payload = [
            'academic_year_id' => $year->id,
            'course_section_ids' => [$course->id, $secondCourse->id],
            'inspector_staff_id' => $this->inspector->id,
            'physical_location' => 'Pabellón norte',
            'starts_on' => '2026-03-01',
            'ends_on' => null,
            'active' => true,
            'notes' => 'Cobertura masiva de cursos.',
        ];

        $this->postJson('/api/inspectoria/course-assignments/bulk', $payload)
            ->assertCreated()
            ->assertJsonPath('created_count', 2)
            ->assertJsonCount(2, 'data');

        $this->assertDatabaseHas('inspectoria_course_assignments', [
            'course_section_id' => $course->id,
            'inspector_staff_id' => $this->inspector->id,
            'physical_location' => 'Pabellón norte',
        ]);
        $this->assertDatabaseHas('inspectoria_course_assignments', [
            'course_section_id' => $secondCourse->id,
            'inspector_staff_id' => $this->inspector->id,
        ]);

        $this->postJson('/api/inspectoria/course-assignments/bulk', $payload)
            ->assertOk()
            ->assertJsonPath('created_count', 0)
            ->assertJsonPath('skipped_count', 2)
            ->assertJsonCount(2, 'skipped_courses');

        $this->assertSame(2, InspectoriaCourseAssignment::query()->count());

        $otherInspector = Staff::query()->create([
            'full_name' => 'Inspectora con cobertura compartida',
            'rut' => '17.777.777-7',
            'status' => 'activo',
            'active' => true,
        ]);

        $this->postJson('/api/inspectoria/course-assignments/bulk', [
            ...$payload,
            'inspector_staff_id' => $otherInspector->id,
        ])->assertCreated()
            ->assertJsonPath('created_count', 2)
            ->assertJsonPath('skipped_count', 0);

        $this->assertSame(4, InspectoriaCourseAssignment::query()->count());

        $thirdCourse = CourseSection::query()->create([
            'academic_year_id' => $year->id,
            'education_level_id' => $level->id,
            'section_name' => 'C',
            'display_name' => '2° medio C',
            'active' => true,
        ]);

        $this->postJson('/api/inspectoria/course-assignments/bulk', [
            ...$payload,
            'course_section_ids' => [$course->id, $thirdCourse->id],
        ])->assertCreated()
            ->assertJsonPath('created_count', 1)
            ->assertJsonPath('skipped_count', 1)
            ->assertJsonPath('skipped_courses.0.course_section_id', $course->id);

        $this->assertSame(5, InspectoriaCourseAssignment::query()->count());
    }

    public function test_inspectoria_pass_supersedes_library_and_blocks_new_lower_priority_passes(): void
    {
        [, , $student] = $this->academicContext();
        $professor = Staff::query()->create(['full_name' => 'Profesora Responsable', 'rut' => '9.999.999-9', 'status' => 'activo', 'active' => true]);

        $this->postJson('/api/biblioteca/pases', [
            'student_profile_id' => $student->id,
            'professor_staff_id' => $professor->id,
            'valid_from' => '2026-08-10 10:00:00',
            'valid_until' => '2026-08-10 10:45:00',
            'reason' => 'Trabajo en Biblioteca.',
        ])->assertCreated();

        $response = $this->postJson('/api/inspectoria/passes', [
            'student_profile_id' => $student->id,
            'destination' => 'direccion',
            'valid_from' => '2026-08-10 10:15:00',
            'valid_until' => '2026-08-10 10:35:00',
            'reason' => 'Entrevista solicitada por Dirección.',
            'signature_name' => 'María Pérez',
            'signature_rut' => '22.222.222-2',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.priority', 100)
            ->assertJsonPath('data.status', 'emitido');

        $priorityPass = InspectoriaPass::query()->firstOrFail();
        $libraryPass = BibliotecaPase::query()->firstOrFail();
        $this->assertSame('anulado', $libraryPass->status);
        $this->assertSame($priorityPass->id, $libraryPass->superseded_by_inspectoria_pass_id);
        $this->assertStringContainsString($priorityPass->pass_code, (string) $libraryPass->notes);

        $this->postJson('/api/biblioteca/pases', [
            'student_profile_id' => $student->id,
            'professor_staff_id' => $professor->id,
            'valid_from' => '2026-08-10 10:20:00',
            'valid_until' => '2026-08-10 10:30:00',
            'reason' => 'Segundo intento de pase.',
        ])->assertUnprocessable()->assertJsonValidationErrors('valid_from');
    }

    public function test_daily_log_and_student_file_expose_inspectoria_history(): void
    {
        [, $course, $student] = $this->academicContext();

        $this->postJson('/api/inspectoria/daily-log', [
            'student_profile_id' => $student->id,
            'course_section_id' => $course->id,
            'happened_at' => '2026-08-10 11:20:00',
            'category' => 'observacion_positiva',
            'priority' => 'baja',
            'title' => 'Apoyo a compañera nueva',
            'detail' => 'La alumna acompañó y orientó voluntariamente a una compañera nueva.',
            'requires_follow_up' => false,
        ])->assertCreated();

        $this->assertSame(1, InspectoriaDailyLog::query()->count());
        $this->getJson("/api/inspectoria/students/{$student->id}")
            ->assertOk()
            ->assertJsonPath('data.student.registered_name_resolved', 'María Pérez')
            ->assertJsonPath('data.daily_logs.0.title', 'Apoyo a compañera nueva');
    }

    public function test_authorized_user_can_update_contact_data_with_a_minimal_audit_trail(): void
    {
        [, , $student] = $this->academicContext();
        $originalVersion = $student->fresh()->updated_at->toJSON();

        Carbon::setTestNow('2026-08-10 09:01:00');

        $this->putJson("/api/inspectoria/students/{$student->id}/profile", [
            'profile_updated_at' => $originalVersion,
            'registered_name' => 'María José Pérez',
            'phone' => '+56 9 5555 0101',
            'address' => 'Los Robles 125',
            'commune' => 'Valdivia',
            'guardian_name' => 'Ana Pérez Soto',
            'guardian_relationship' => 'Madre',
            'guardian_phone' => '+56 9 4444 0202',
            'guardian_email' => 'ANA.PEREZ@EXAMPLE.CL',
            'guardian_address' => 'Los Robles 125',
            'guardian_commune' => 'Valdivia',
            'guardian_backup_name' => 'Carlos Pérez',
            'guardian_backup_phone' => '+56 9 3333 0303',
            'has_judicial_process' => true,
        ])->assertOk()
            ->assertJsonPath('message', 'Datos de la ficha actualizados correctamente.')
            ->assertJsonPath('data.student.address', 'Los Robles 125')
            ->assertJsonPath('data.student.guardian_email', 'ana.perez@example.cl')
            ->assertJsonPath('data.student.updated_by.id', $this->user->id);

        $student->refresh();
        $this->assertSame('María José Pérez', $student->registered_name);
        $this->assertSame('+56 9 5555 0101', $student->phone);
        $this->assertNull($student->has_judicial_process, 'Inspectoría no debe modificar antecedentes judiciales desde esta ficha.');
        $this->assertSame($this->user->id, $student->updated_by);

        $audit = DB::table('inspectoria_student_profile_change_logs')->sole();
        $this->assertSame($student->id, $audit->student_profile_id);
        $this->assertSame($this->user->id, $audit->actor_user_id);
        $this->assertContains('address', json_decode($audit->changed_fields, true, 512, JSON_THROW_ON_ERROR));
        $this->assertNotNull($audit->ip_address_hash);
        $this->assertNull(data_get((array) $audit, 'old_values'));
        $this->assertNull(data_get((array) $audit, 'new_values'));
    }

    public function test_profile_update_rejects_stale_versions_and_requires_the_edit_permission(): void
    {
        [, , $student] = $this->academicContext();
        $staleVersion = $student->fresh()->updated_at->toJSON();

        Carbon::setTestNow('2026-08-10 09:02:00');
        $student->update(['phone' => '+56 9 1111 1111']);

        $this->putJson("/api/inspectoria/students/{$student->id}/profile", [
            'profile_updated_at' => $staleVersion,
            'phone' => '+56 9 2222 2222',
        ])->assertConflict()
            ->assertJsonPath('message', 'La ficha fue actualizada por otra persona. Vuelve a abrirla antes de guardar tus cambios.');

        $viewer = User::factory()->create(['active' => true]);
        $viewerRole = Role::query()->create(['slug' => 'lector_fichas_inspectoria', 'name' => 'Lector fichas', 'active' => true]);
        $viewerRole->permissions()->sync(Permission::query()->whereIn('slug', [
            'ver_modulo_inspectoria',
            'ver_fichas_inspectoria',
        ])->pluck('id'));
        $viewer->roles()->sync([$viewerRole->id]);
        Sanctum::actingAs($viewer);

        $this->getJson("/api/inspectoria/students/{$student->id}")->assertOk();
        $this->putJson("/api/inspectoria/students/{$student->id}/profile", [
            'profile_updated_at' => $student->fresh()->updated_at->toJSON(),
            'address' => 'Intento sin permiso',
        ])->assertForbidden();
    }

    public function test_pickup_restrictions_are_managed_from_social_work_routes(): void
    {
        [, $course, $student] = $this->academicContext();
        $student->update([
            'guardian_name' => 'Ana Pérez',
            'guardian_rut' => '11.111.111-1',
            'guardian_backup_name' => 'Carlos Pérez',
            'guardian_backup_rut' => '12.222.222-2',
        ]);

        $this->postJson('/api/inspectoria/pickup-restrictions', [])->assertNotFound();

        $response = $this->postJson('/api/social-work/pickup-restrictions', [
            'student_profile_id' => $student->id,
            'restricted_person_name' => 'Carlos Pérez',
            'restricted_person_rut' => '12.222.222-2',
            'restricted_person_relationship' => 'Apoderado suplente',
            'restriction_type' => 'orden_alejamiento',
            'reason' => 'Orden de alejamiento vigente. No autorizar retiro.',
            'legal_reference' => 'Tribunal de Familia · causa RIT C-123-2026',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-12-31',
            'active' => true,
        ])->assertCreated()
            ->assertJsonPath('data.course_section_id', $course->id)
            ->assertJsonPath('data.restriction_type_label', 'Orden de alejamiento')
            ->assertJsonPath('data.restricted_person_name', 'Carlos Pérez');

        $restriction = InspectoriaPickupRestriction::query()->firstOrFail();
        $this->assertSame('12222222-2', $restriction->restricted_person_rut);

        $this->getJson('/api/social-work/pickup-restrictions?active=1')
            ->assertOk()->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.student_profile_id', $student->id);

        $this->deleteJson("/api/social-work/pickup-restrictions/{$restriction->id}")
            ->assertOk()->assertJsonPath('data.active', false);
    }

    public function test_staff_lateness_can_be_linked_to_multiple_courses_and_is_aggregated_in_statistics(): void
    {
        [$year, $course] = $this->academicContext();
        $level = EducationLevel::query()->findOrFail($course->education_level_id);
        $secondCourse = CourseSection::query()->create([
            'academic_year_id' => $year->id,
            'education_level_id' => $level->id,
            'section_name' => 'B',
            'display_name' => '2° medio B',
            'active' => true,
        ]);
        $lateStaff = Staff::query()->create([
            'full_name' => 'Docente con atraso',
            'rut' => '14.444.444-4',
            'status' => 'activo',
            'active' => true,
        ]);

        $response = $this->postJson('/api/inspectoria/daily-log', [
            'happened_at' => '2026-08-10 08:20:00',
            'category' => 'asistencia',
            'is_staff_lateness' => true,
            'late_staff_id' => $lateStaff->id,
            'associated_course_ids' => [$course->id, $secondCourse->id],
            'lateness_minutes' => 15,
            'priority' => 'media',
            'status' => 'registrado',
            'title' => 'Atraso de docente',
            'detail' => 'La funcionaria ingresa después del inicio de la primera hora.',
            'requires_follow_up' => false,
        ])->assertCreated()
            ->assertJsonPath('data.is_staff_lateness', true)
            ->assertJsonPath('data.late_staff.id', $lateStaff->id)
            ->assertJsonPath('data.lateness_minutes', 15)
            ->assertJsonCount(2, 'data.associated_courses');

        $dailyLogId = $response->json('data.id');
        $this->assertDatabaseHas('inspectoria_daily_logs', [
            'id' => $dailyLogId,
            'student_profile_id' => null,
            'course_section_id' => null,
            'late_staff_id' => $lateStaff->id,
            'late_staff_name_snapshot' => 'Docente con atraso',
            'is_staff_lateness' => true,
            'lateness_minutes' => 15,
        ]);
        $this->assertDatabaseCount('inspectoria_daily_log_courses', 2);

        $this->getJson('/api/inspectoria/statistics/staff-lateness?date_from=2026-08-01&date_to=2026-08-31')
            ->assertOk()
            ->assertJsonPath('summary.events_total', 1)
            ->assertJsonPath('summary.minutes_total', 15)
            ->assertJsonPath('summary.staff_total', 1)
            ->assertJsonPath('summary.courses_total', 2)
            ->assertJsonCount(2, 'course_totals')
            ->assertJsonCount(2, 'staff_course_rows')
            ->assertJsonFragment(['staff_name' => 'Docente con atraso', 'course_name' => $course->display_name, 'total' => 1, 'minutes_total' => 15])
            ->assertJsonFragment(['staff_name' => 'Docente con atraso', 'course_name' => $secondCourse->display_name, 'total' => 1, 'minutes_total' => 15]);

        $this->getJson("/api/inspectoria/statistics/staff-lateness?date_from=2026-08-01&date_to=2026-08-31&course_section_id={$course->id}")
            ->assertOk()
            ->assertJsonPath('summary.events_total', 1)
            ->assertJsonPath('summary.courses_total', 1);
    }

    public function test_staff_lateness_requires_an_active_staff_member(): void
    {
        $this->postJson('/api/inspectoria/daily-log', [
            'happened_at' => '2026-08-10 08:20:00',
            'category' => 'asistencia',
            'is_staff_lateness' => true,
            'late_staff_id' => null,
            'associated_course_ids' => [],
            'lateness_minutes' => 10,
            'priority' => 'media',
            'status' => 'registrado',
            'title' => 'Atraso de funcionario',
            'detail' => 'Ingreso posterior al inicio de la jornada.',
            'requires_follow_up' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors('late_staff_id');
    }

    public function test_staff_lateness_requires_one_of_the_available_durations(): void
    {
        $lateStaff = Staff::query()->create([
            'full_name' => 'Funcionario puntualidad',
            'rut' => '15.555.555-5',
            'status' => 'activo',
            'active' => true,
        ]);

        $payload = [
            'happened_at' => '2026-08-10 08:20:00',
            'category' => 'asistencia',
            'is_staff_lateness' => true,
            'late_staff_id' => $lateStaff->id,
            'associated_course_ids' => [],
            'priority' => 'media',
            'status' => 'registrado',
            'title' => 'Atraso de funcionario',
            'detail' => 'Ingreso posterior al inicio de la jornada.',
            'requires_follow_up' => false,
        ];

        $this->postJson('/api/inspectoria/daily-log', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('lateness_minutes');

        $this->postJson('/api/inspectoria/daily-log', [...$payload, 'lateness_minutes' => 25])
            ->assertUnprocessable()->assertJsonValidationErrors('lateness_minutes');
    }

    public function test_inspectoria_roles_have_the_expected_permissions_and_modules(): void
    {
        $inspector = Role::query()->where('slug', 'inspectoria')->firstOrFail();
        $coordinator = Role::query()->where('slug', 'coordinador_inspectoria')->firstOrFail();
        $inspectoriaPermissions = array_keys([
            'ver_modulo_inspectoria' => true,
            'registrar_atenciones_inspectoria' => true,
            'asignar_cursos_inspectoria' => true,
            'gestionar_pases_inspectoria' => true,
            'ver_fichas_inspectoria' => true,
            'editar_fichas_inspectoria' => true,
            'ver_retiros_inspectoria' => true,
            'ver_bitacora_inspectoria' => true,
            'registrar_bitacora_inspectoria' => true,
            'ver_estadisticas_inspectoria' => true,
        ]);
        $operationalPermissions = array_values(array_diff(
            $inspectoriaPermissions,
            ['asignar_cursos_inspectoria'],
        ));
        $moduleSlugs = [
            'inspectoria',
            'inspectoria_atenciones',
            'inspectoria_asignaciones',
            'inspectoria_pases',
            'inspectoria_alumnas',
            'inspectoria_retiros',
            'inspectoria_bitacora',
            'inspectoria_estadisticas',
        ];

        $this->assertSame('Inspector/a', $inspector->name);
        $this->assertSame('Coordinador/a de Inspectoría', $coordinator->name);
        $this->assertEqualsCanonicalizing(
            $operationalPermissions,
            $inspector->permissions()->whereIn('permissions.slug', $inspectoriaPermissions)->pluck('permissions.slug')->all(),
        );
        $this->assertEqualsCanonicalizing(
            $inspectoriaPermissions,
            $coordinator->permissions()->whereIn('permissions.slug', $inspectoriaPermissions)->pluck('permissions.slug')->all(),
        );
        $this->assertFalse($inspector->permissions()->where('permissions.slug', 'asignar_cursos_inspectoria')->exists());
        $this->assertTrue($coordinator->permissions()->where('permissions.slug', 'asignar_cursos_inspectoria')->exists());
        $this->assertFalse($inspector->permissions()->where('permissions.slug', 'social_work.pickup_restrictions.manage')->exists());
        $this->assertFalse($coordinator->permissions()->where('permissions.slug', 'social_work.pickup_restrictions.manage')->exists());
        $this->assertEmpty(array_diff($moduleSlugs, $inspector->modules()->pluck('system_modules.slug')->all()));
        $this->assertEmpty(array_diff($moduleSlugs, $coordinator->modules()->pluck('system_modules.slug')->all()));
        $this->assertDatabaseHas('permission_group_permission', [
            'permission_group_id' => DB::table('permission_groups')->where('slug', 'inspectoria')->value('id'),
            'permission_id' => Permission::query()->where('slug', 'ver_estadisticas_inspectoria')->value('id'),
        ]);
    }

    public function test_inspector_is_restricted_to_students_and_records_from_assigned_courses(): void
    {
        [$year, $assignedCourse, $assignedStudent] = $this->academicContext();
        $level = EducationLevel::query()->findOrFail($assignedCourse->education_level_id);
        $otherCourse = CourseSection::query()->create([
            'academic_year_id' => $year->id, 'education_level_id' => $level->id,
            'section_name' => 'B', 'display_name' => '2° medio B', 'active' => true,
        ]);
        $otherStudent = StudentProfile::query()->create([
            'first_name' => 'Josefina', 'last_name' => 'Soto', 'registered_name' => 'Josefina Soto',
            'rut' => '23.333.333-3', 'general_status' => 'activo', 'guardian_name' => 'Laura Soto',
        ]);
        StudentEnrollment::query()->create([
            'student_profile_id' => $otherStudent->id, 'academic_year_id' => $year->id,
            'course_section_id' => $otherCourse->id, 'enrollment_status' => 'regular',
            'enrolled_at' => '2026-03-01', 'snapshot_year_name' => $year->name,
            'snapshot_level_name' => $level->name, 'snapshot_section_name' => 'B',
            'snapshot_course_display_name' => $otherCourse->display_name,
        ]);

        $otherInspector = Staff::query()->create([
            'full_name' => 'Inspectora de otro curso', 'rut' => '13.456.789-6',
            'status' => 'activo', 'active' => true,
        ]);
        foreach ([[$assignedCourse, $this->inspector], [$otherCourse, $otherInspector]] as [$course, $inspector]) {
            InspectoriaCourseAssignment::query()->create([
                'academic_year_id' => $year->id, 'course_section_id' => $course->id,
                'inspector_staff_id' => $inspector->id, 'physical_location' => $course->display_name,
                'starts_on' => '2026-03-01', 'active' => true,
            ]);
        }

        $withdrawals = [];
        foreach ([[$assignedCourse, $assignedStudent, $this->inspector, 'A'], [$otherCourse, $otherStudent, $otherInspector, 'B']] as [$course, $student, $inspector, $suffix]) {
            InspectoriaAttention::query()->create([
                'attention_code' => "INS-ATE-2026-90{$suffix}", 'student_profile_id' => $student->id,
                'course_section_id' => $course->id, 'inspector_staff_id' => $inspector->id,
                'attended_at' => '2026-08-10 09:15:00', 'request_types' => ['orientacion'],
                'actions_taken' => ['resuelta'], 'priority' => 'normal', 'status' => 'registrada',
                'student_name_snapshot' => $student->registered_name_resolved,
                'course_name_snapshot' => $course->display_name,
            ]);
            InspectoriaPass::query()->create([
                'pass_code' => "INS-PAS-2026-90{$suffix}", 'student_profile_id' => $student->id,
                'course_section_id' => $course->id, 'inspector_staff_id' => $inspector->id,
                'student_name_snapshot' => $student->registered_name_resolved,
                'student_rut_snapshot' => $student->rut, 'inspector_name_snapshot' => $inspector->full_name,
                'destination' => 'direccion', 'issued_at' => '2026-08-10 09:20:00',
                'valid_from' => '2026-08-10 10:00:00', 'valid_until' => '2026-08-10 10:30:00',
                'status' => 'emitido', 'priority' => 100, 'regulation_version' => 'vigente',
                'reason' => "Pase curso {$suffix}",
            ]);
            InspectoriaDailyLog::query()->create([
                'student_profile_id' => $student->id, 'course_section_id' => $course->id,
                'inspector_staff_id' => $inspector->id, 'happened_at' => '2026-08-10 11:00:00',
                'category' => 'novedad', 'priority' => 'media', 'status' => 'registrado',
                'title' => "Bitácora curso {$suffix}", 'detail' => "Detalle del curso {$suffix}",
                'requires_follow_up' => false,
            ]);
            $withdrawals[$suffix] = PorterStudentWithdrawal::query()->create([
                'student_profile_id' => $student->id, 'academic_year_id' => $year->id,
                'course_section_id' => $course->id, 'registered_by' => $this->user->id,
                'status' => 'registrado', 'withdrawn_at' => '2026-08-10 11:30:00',
                'student_full_name_snapshot' => $student->registered_name_resolved,
                'student_rut_snapshot' => $student->rut, 'academic_year_name_snapshot' => $year->name,
                'course_name_snapshot' => $course->display_name, 'person_name' => "Apoderado {$suffix}",
                'person_relationship' => 'apoderado', 'reason' => 'familiar',
                'person_authorized' => true, 'requires_special_authorization' => false,
            ]);
        }

        PorterStudentWithdrawal::query()->create([
            'student_profile_id' => $assignedStudent->id, 'academic_year_id' => $year->id,
            'course_section_id' => $assignedCourse->id, 'registered_by' => $this->user->id,
            'status' => 'registrado', 'withdrawn_at' => '2026-08-09 12:00:00',
            'student_full_name_snapshot' => $assignedStudent->registered_name_resolved,
            'student_rut_snapshot' => $assignedStudent->rut, 'academic_year_name_snapshot' => $year->name,
            'course_name_snapshot' => $assignedCourse->display_name, 'person_name' => 'Apoderada histórica',
            'person_relationship' => 'madre', 'reason' => 'tramite',
            'person_authorized' => true, 'requires_special_authorization' => false,
        ]);

        $inspectorRole = Role::query()->where('slug', 'inspectoria')->firstOrFail();
        $inspectorRole->permissions()->sync(Permission::query()->whereIn('slug', [
            'ver_modulo_inspectoria', 'registrar_atenciones_inspectoria', 'asignar_cursos_inspectoria',
            'gestionar_pases_inspectoria', 'ver_fichas_inspectoria', 'editar_fichas_inspectoria', 'registrar_bitacora_inspectoria',
            'ver_retiros_inspectoria', 'ver_bitacora_inspectoria',
        ])->pluck('id'));
        $this->user->roles()->sync([$inspectorRole->id]);
        $this->user->unsetRelation('roles');
        Sanctum::actingAs($this->user);

        $this->getJson('/api/inspectoria/catalogs')->assertOk()
            ->assertJsonCount(1, 'courses')->assertJsonPath('courses.0.id', $assignedCourse->id)
            ->assertJsonCount(1, 'students')->assertJsonPath('students.0.id', $assignedStudent->id)
            ->assertJsonPath('capabilities.manage_assignments', false);
        $this->getJson('/api/inspectoria/course-assignments')->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('data.0.course_section_id', $assignedCourse->id);
        $this->getJson('/api/inspectoria/students')->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $assignedStudent->id);
        $this->getJson("/api/inspectoria/students/{$assignedStudent->id}")->assertOk()
            ->assertJsonCount(1, 'data.attentions')->assertJsonCount(1, 'data.passes')->assertJsonCount(1, 'data.daily_logs');
        $this->getJson("/api/inspectoria/students/{$otherStudent->id}")->assertForbidden();
        $this->putJson("/api/inspectoria/students/{$assignedStudent->id}/profile", [
            'profile_updated_at' => $assignedStudent->fresh()->updated_at->toJSON(),
            'address' => 'Dirección dentro del curso asignado',
        ])->assertOk()->assertJsonPath('data.student.address', 'Dirección dentro del curso asignado');
        $this->putJson("/api/inspectoria/students/{$otherStudent->id}/profile", [
            'profile_updated_at' => $otherStudent->fresh()->updated_at->toJSON(),
            'address' => 'Intento fuera del curso asignado',
        ])->assertForbidden();
        $this->getJson('/api/inspectoria/attentions')->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('data.0.student_profile_id', $assignedStudent->id);
        $this->getJson('/api/inspectoria/passes')->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('data.0.student_profile_id', $assignedStudent->id);
        $this->getJson('/api/inspectoria/daily-log')->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('data.0.student_profile_id', $assignedStudent->id);
        $this->getJson('/api/inspectoria/withdrawals?scope=today')->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('data.0.student_profile_id', $assignedStudent->id)
            ->assertJsonPath('data.0.withdrawal_code', $withdrawals['A']->withdrawal_code);
        $this->getJson('/api/inspectoria/withdrawals?scope=history')->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('data.0.student_profile_id', $assignedStudent->id);
        $this->getJson("/api/inspectoria/withdrawals/{$withdrawals['B']->id}")->assertForbidden();
        $this->postJson('/api/inspectoria/pickup-restrictions', [
            'student_profile_id' => $otherStudent->id, 'restricted_person_name' => 'Apoderado externo',
            'restriction_type' => 'persona_no_autorizada', 'reason' => 'Intento fuera de alcance.',
            'starts_on' => '2026-08-10', 'active' => true,
        ])->assertNotFound();

        $this->postJson('/api/inspectoria/attentions', [
            'student_profile_id' => $otherStudent->id, 'attended_at' => '2026-08-10 12:00:00',
            'request_types' => ['orientacion'], 'actions_taken' => ['resuelta'],
            'priority' => 'normal', 'guardian_notified' => false, 'requires_follow_up' => false,
        ])->assertForbidden();
        $this->postJson('/api/inspectoria/passes', [
            'student_profile_id' => $otherStudent->id, 'destination' => 'direccion',
            'valid_from' => '2026-08-10 12:00:00', 'valid_until' => '2026-08-10 12:30:00',
            'reason' => 'Intento fuera de alcance.',
        ])->assertForbidden();
        $this->postJson('/api/inspectoria/daily-log', [
            'student_profile_id' => $otherStudent->id, 'course_section_id' => $otherCourse->id,
            'happened_at' => '2026-08-10 12:00:00', 'category' => 'novedad', 'priority' => 'media',
            'title' => 'Intento fuera de alcance', 'detail' => 'No debe registrarse.',
            'requires_follow_up' => false,
        ])->assertForbidden();
        $this->postJson('/api/inspectoria/course-assignments', [
            'academic_year_id' => $year->id, 'course_section_id' => $otherCourse->id,
            'inspector_staff_id' => $this->inspector->id, 'starts_on' => '2026-08-10', 'active' => true,
        ])->assertForbidden();
    }

    public function test_inspectors_with_the_same_course_only_see_their_own_logs_attentions_and_referrals(): void
    {
        [$year, $course, $student] = $this->academicContext();
        $otherInspector = Staff::query()->create([
            'full_name' => 'Inspectora del mismo curso',
            'rut' => '18.888.888-8',
            'status' => 'activo',
            'active' => true,
        ]);
        $otherUser = User::factory()->create([
            'name' => 'Inspectora del mismo curso',
            'active' => true,
            'staff_id' => $otherInspector->id,
        ]);

        foreach ([$this->inspector, $otherInspector] as $inspector) {
            InspectoriaCourseAssignment::query()->create([
                'academic_year_id' => $year->id,
                'course_section_id' => $course->id,
                'inspector_staff_id' => $inspector->id,
                'physical_location' => 'Pabellón compartido',
                'starts_on' => '2026-03-01',
                'active' => true,
            ]);
        }

        $ownAttention = InspectoriaAttention::query()->create([
            'attention_code' => 'INS-ATE-2026-OWN1',
            'student_profile_id' => $student->id,
            'course_section_id' => $course->id,
            'inspector_staff_id' => $this->inspector->id,
            'attended_by_user_id' => $this->user->id,
            'attended_at' => '2026-08-10 09:10:00',
            'request_types' => ['orientacion'],
            'actions_taken' => ['derivacion_psicosocial'],
            'priority' => 'normal',
            'status' => 'registrada',
            'student_name_snapshot' => $student->registered_name_resolved,
            'course_name_snapshot' => $course->display_name,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);
        $otherAttention = InspectoriaAttention::query()->create([
            'attention_code' => 'INS-ATE-2026-OWN2',
            'student_profile_id' => $student->id,
            'course_section_id' => $course->id,
            'inspector_staff_id' => $otherInspector->id,
            'attended_by_user_id' => $otherUser->id,
            'attended_at' => '2026-08-10 09:20:00',
            'request_types' => ['orientacion'],
            'actions_taken' => ['derivacion_psicosocial'],
            'priority' => 'normal',
            'status' => 'registrada',
            'student_name_snapshot' => $student->registered_name_resolved,
            'course_name_snapshot' => $course->display_name,
            'created_by' => $otherUser->id,
            'updated_by' => $otherUser->id,
        ]);
        $ownDailyLog = InspectoriaDailyLog::query()->create([
            'student_profile_id' => $student->id,
            'course_section_id' => $course->id,
            'inspector_staff_id' => $this->inspector->id,
            'registered_by_user_id' => $this->user->id,
            'happened_at' => '2026-08-10 10:10:00',
            'category' => 'novedad',
            'priority' => 'media',
            'status' => 'registrado',
            'title' => 'Registro de la primera inspectora',
            'detail' => 'Antecedente visible solo para quien lo registró.',
            'requires_follow_up' => false,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);
        $otherDailyLog = InspectoriaDailyLog::query()->create([
            'student_profile_id' => $student->id,
            'course_section_id' => $course->id,
            'inspector_staff_id' => $otherInspector->id,
            'registered_by_user_id' => $otherUser->id,
            'happened_at' => '2026-08-10 10:20:00',
            'category' => 'novedad',
            'priority' => 'media',
            'status' => 'registrado',
            'title' => 'Registro de la segunda inspectora',
            'detail' => 'Antecedente privado de la segunda inspectora.',
            'requires_follow_up' => false,
            'created_by' => $otherUser->id,
            'updated_by' => $otherUser->id,
        ]);
        $ownReferral = Referral::query()->create([
            'inspectoria_attention_id' => $ownAttention->id,
            'student_profile_id' => $student->id,
            'course_section_id' => $course->id,
            'referral_date' => '2026-08-10',
            'source_unit' => 'Inspectoría',
            'reason' => 'Derivación de la primera inspectora',
            'urgency' => 'normal',
            'status' => 'enviada',
            'confidentiality' => 'restringido',
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);
        Referral::query()->create([
            'inspectoria_attention_id' => $otherAttention->id,
            'student_profile_id' => $student->id,
            'course_section_id' => $course->id,
            'referral_date' => '2026-08-10',
            'source_unit' => 'Inspectoría',
            'reason' => 'Derivación de la segunda inspectora',
            'urgency' => 'normal',
            'status' => 'enviada',
            'confidentiality' => 'restringido',
            'assigned_user_id' => $this->user->id,
            'created_by' => $otherUser->id,
            'updated_by' => $otherUser->id,
        ]);

        $inspectorRole = Role::query()->where('slug', 'inspectoria')->firstOrFail();
        $inspectorRole->permissions()->syncWithoutDetaching(Permission::query()->whereIn('slug', [
            'ver_modulo_inspectoria',
            'registrar_atenciones_inspectoria',
            'ver_fichas_inspectoria',
            'ver_bitacora_inspectoria',
            'registrar_bitacora_inspectoria',
            'social_work.referrals.submit',
        ])->pluck('id'));
        $this->user->roles()->sync([$inspectorRole->id]);
        $this->user->unsetRelation('roles');
        $otherUser->roles()->sync([$inspectorRole->id]);
        Sanctum::actingAs($this->user);

        $this->getJson('/api/inspectoria/attentions')->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $ownAttention->id);
        $this->getJson('/api/inspectoria/daily-log')->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $ownDailyLog->id);
        $this->getJson("/api/inspectoria/students/{$student->id}")->assertOk()
            ->assertJsonCount(1, 'data.attentions')
            ->assertJsonPath('data.attentions.0.id', $ownAttention->id)
            ->assertJsonCount(1, 'data.daily_logs')
            ->assertJsonPath('data.daily_logs.0.id', $ownDailyLog->id);
        $this->getJson('/api/social-work/referrals')->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $ownReferral->id);
        $this->putJson("/api/inspectoria/daily-log/{$otherDailyLog->id}", [
            'student_profile_id' => $student->id,
            'course_section_id' => $course->id,
            'happened_at' => '2026-08-10 10:25:00',
            'category' => 'novedad',
            'is_staff_lateness' => false,
            'priority' => 'media',
            'status' => 'registrado',
            'title' => 'Intento de edición cruzada',
            'detail' => 'Este cambio no debe persistirse.',
            'requires_follow_up' => false,
        ])->assertForbidden();
        $this->assertSame('Registro de la segunda inspectora', $otherDailyLog->fresh()->title);

        Sanctum::actingAs($otherUser);
        $this->getJson('/api/inspectoria/attentions')->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $otherAttention->id);
        $this->getJson('/api/inspectoria/daily-log')->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $otherDailyLog->id);

        $coordinator = User::factory()->create(['active' => true]);
        $coordinator->roles()->sync([
            Role::query()->where('slug', 'coordinador_inspectoria')->firstOrFail()->id,
        ]);
        Sanctum::actingAs($coordinator);

        $this->getJson('/api/inspectoria/attentions')->assertOk()
            ->assertJsonPath('total', 2);
        $this->getJson('/api/inspectoria/daily-log')->assertOk()
            ->assertJsonPath('total', 2);
    }

    /** @return array{AcademicYear, CourseSection, StudentProfile} */
    private function academicContext(): array
    {
        $year = AcademicYear::query()->create([
            'name' => 'Año escolar 2026', 'year' => 2026,
            'starts_at' => '2026-03-01', 'ends_at' => '2026-12-31',
            'is_active' => true, 'is_closed' => false,
        ]);
        $level = EducationLevel::query()->firstOrCreate(
            ['name' => '2° medio'],
            ['type' => 'media', 'order' => 99, 'active' => true],
        );
        $course = CourseSection::query()->create([
            'academic_year_id' => $year->id, 'education_level_id' => $level->id,
            'section_name' => 'A', 'display_name' => '2° medio A', 'active' => true,
        ]);
        $student = StudentProfile::query()->create([
            'first_name' => 'María', 'last_name' => 'Pérez', 'registered_name' => 'María Pérez',
            'rut' => '22.222.222-2', 'general_status' => 'activo', 'guardian_name' => 'Ana Pérez',
        ]);
        StudentEnrollment::query()->create([
            'student_profile_id' => $student->id, 'academic_year_id' => $year->id,
            'course_section_id' => $course->id, 'enrollment_status' => 'regular',
            'enrolled_at' => '2026-03-01', 'snapshot_year_name' => $year->name,
            'snapshot_level_name' => $level->name, 'snapshot_section_name' => 'A',
            'snapshot_course_display_name' => $course->display_name,
        ]);

        return [$year, $course, $student];
    }
}
