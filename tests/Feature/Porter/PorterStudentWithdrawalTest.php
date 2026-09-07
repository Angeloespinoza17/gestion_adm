<?php

namespace Tests\Feature\Porter;

use App\Models\AcademicYear;
use App\Models\Cargo;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\Inspectoria\InspectoriaCourseAssignment;
use App\Models\Inspectoria\InspectoriaPickupRestriction;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PorterStudentWithdrawalTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_registers_a_regular_withdrawal_for_an_authorized_guardian(): void
    {
        $user = $this->createUserWithPermissions(['ver_porteria', 'registrar_retiro_porteria']);
        [$student, $course, $year, $inspector] = $this->createActiveStudent();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/porter/withdrawals', [
            'student_profile_id' => $student->id,
            'inspector_staff_id' => $inspector->id,
            'person_name' => 'María Guardia',
            'person_rut' => '12.345.678-5',
            'person_relationship' => 'apoderado',
            'person_phone' => '999999999',
            'reason' => 'medico',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'registrado')
            ->assertJsonPath('data.withdrawal_code', 'RET-2026-000001')
            ->assertJsonPath('data.requires_special_authorization', false);

        $this->assertDatabaseHas('porter_student_withdrawals', [
            'student_profile_id' => $student->id,
            'academic_year_id' => $year->id,
            'course_section_id' => $course->id,
            'inspector_staff_id' => $inspector->id,
            'inspector_name_snapshot' => $inspector->full_name,
            'status' => 'registrado',
            'person_authorized' => 1,
        ]);

        $this->assertDatabaseCount('porter_authorization_requests', 0);
    }

    public function test_it_creates_an_authorization_request_when_the_person_is_not_authorized(): void
    {
        $user = $this->createUserWithPermissions(['ver_porteria', 'registrar_retiro_porteria']);
        [$student, , , $inspector] = $this->createActiveStudent();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/porter/withdrawals', [
            'student_profile_id' => $student->id,
            'inspector_staff_id' => $inspector->id,
            'person_name' => 'Persona No Autorizada',
            'person_relationship' => 'otro',
            'person_phone' => '999999999',
            'reason' => 'otro',
            'observations' => 'Se solicita revisión.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'observado')
            ->assertJsonPath('data.requires_special_authorization', true);

        $this->assertDatabaseHas('porter_student_withdrawals', [
            'student_profile_id' => $student->id,
            'status' => 'observado',
            'person_authorized' => 0,
        ]);

        $this->assertDatabaseHas('porter_authorization_requests', [
            'status' => 'pendiente',
            'required_permission_slug' => 'autorizar_retiros_porteria',
        ]);
    }

    public function test_it_requires_confirmation_for_recent_duplicate_withdrawals(): void
    {
        $user = $this->createUserWithPermissions(['ver_porteria', 'registrar_retiro_porteria']);
        [$student, , , $inspector] = $this->createActiveStudent();

        Sanctum::actingAs($user);

        $payload = [
            'student_profile_id' => $student->id,
            'inspector_staff_id' => $inspector->id,
            'person_name' => 'María Guardia',
            'person_rut' => '12.345.678-5',
            'person_relationship' => 'apoderado',
            'reason' => 'medico',
        ];

        $this->postJson('/api/porter/withdrawals', $payload)->assertCreated();

        $response = $this->postJson('/api/porter/withdrawals', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['force_duplicate_confirmation']);
    }

    public function test_it_exposes_and_enforces_an_active_person_specific_pickup_restriction(): void
    {
        $user = $this->createUserWithPermissions(['ver_porteria', 'registrar_retiro_porteria']);
        [$student, $course, , $inspector] = $this->createActiveStudent();
        InspectoriaPickupRestriction::query()->create([
            'student_profile_id' => $student->id,
            'course_section_id' => $course->id,
            'restricted_person_name' => 'María Guardia',
            'restricted_person_rut' => '12345678-5',
            'restricted_person_relationship' => 'Apoderada titular',
            'restriction_type' => 'orden_alejamiento',
            'reason' => 'Orden de alejamiento vigente. No autorizar el retiro.',
            'legal_reference' => 'Tribunal de Familia · RIT C-123-2026',
            'starts_on' => now()->subDay()->toDateString(),
            'ends_on' => now()->addMonth()->toDateString(),
            'active' => true,
        ]);

        Sanctum::actingAs($user);

        $studentResponse = $this->getJson("/api/porter/students/{$student->id}")
            ->assertOk()
            ->assertJsonPath('data.pickup_restrictions.0.restricted_person_name', 'María Guardia')
            ->assertJsonPath('data.pickup_restrictions.0.restriction_type_label', 'Orden de alejamiento');
        $this->assertContains('pickup_restriction_person', collect($studentResponse->json('data.alerts'))->pluck('type')->all());

        $this->postJson('/api/porter/withdrawals', [
            'student_profile_id' => $student->id,
            'inspector_staff_id' => $inspector->id,
            'person_name' => 'María Guardia',
            'person_rut' => '12.345.678-5',
            'person_relationship' => 'apoderado',
            'person_phone' => '999999999',
            'reason' => 'medico',
        ])->assertCreated()
            ->assertJsonPath('data.person_authorized', true)
            ->assertJsonPath('data.requires_special_authorization', true)
            ->assertJsonPath('data.status', 'observado');

        $this->assertDatabaseHas('porter_authorization_requests', [
            'status' => 'pendiente',
            'reason' => 'Orden de alejamiento: Orden de alejamiento vigente. No autorizar el retiro.',
        ]);
    }

    public function test_porter_student_file_does_not_expose_medical_information(): void
    {
        $user = $this->createUserWithPermissions(['ver_porteria']);
        [$student] = $this->createActiveStudent();
        $student->update([
            'health_insurance' => 'Sistema confidencial',
            'has_chronic_illness' => true,
            'chronic_illness_details' => 'Diagnóstico reservado',
            'has_medication_allergies' => true,
            'medication_allergies_details' => 'Alergia reservada',
            'has_physical_restrictions' => true,
            'physical_restrictions_details' => 'Restricción reservada',
            'pickup_restriction' => true,
            'pickup_restriction_notes' => 'Validar identidad antes de autorizar el retiro.',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/porter/students/{$student->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.health_insurance')
            ->assertJsonMissingPath('data.has_chronic_illness')
            ->assertJsonMissingPath('data.chronic_illness_details')
            ->assertJsonMissingPath('data.has_medication_allergies')
            ->assertJsonMissingPath('data.medication_allergies_details')
            ->assertJsonMissingPath('data.has_physical_restrictions')
            ->assertJsonMissingPath('data.physical_restrictions_details');

        $alertTypes = collect($response->json('data.alerts'))->pluck('type')->all();
        $this->assertNotContains('medical', $alertTypes);
        $this->assertContains('pickup_restriction', $alertTypes);
    }

    public function test_it_preloads_the_inspector_assigned_to_the_students_course(): void
    {
        $user = $this->createUserWithPermissions(['ver_porteria', 'registrar_retiro_porteria']);
        [$student, , , $assignedInspector] = $this->createActiveStudent();
        $inspectorUser = User::factory()->create([
            'name' => $assignedInspector->full_name,
            'staff_id' => $assignedInspector->id,
            'active' => true,
        ]);
        $otherInspector = Staff::query()->create([
            'full_name' => 'Inspectora alternativa',
            'rut' => '16.111.111-1',
            'status' => 'activo',
            'active' => true,
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/porter/students/{$student->id}")
            ->assertOk()
            ->assertJsonPath('data.assigned_inspector.id', $assignedInspector->id)
            ->assertJsonPath('data.assigned_inspector.full_name', $assignedInspector->full_name);

        $this->postJson('/api/porter/withdrawals', [
            'student_profile_id' => $student->id,
            'inspector_staff_id' => $otherInspector->id,
            'person_name' => 'María Guardia',
            'person_rut' => '12.345.678-5',
            'person_relationship' => 'apoderado',
            'reason' => 'medico',
        ])->assertCreated()
            ->assertJsonPath('data.inspector.id', $assignedInspector->id);

        $this->assertDatabaseHas('porter_student_withdrawals', [
            'student_profile_id' => $student->id,
            'inspector_staff_id' => $assignedInspector->id,
            'inspector_name_snapshot' => $assignedInspector->full_name,
        ]);

        $notification = $inspectorUser->notifications()->firstOrFail();
        $this->assertSame('Nuevo retiro de estudiante', $notification->data['title']);
        $this->assertSame('/inspectoria/retiros', $notification->data['action_url']);
        $this->assertSame($student->id, $notification->data['student_profile_id']);

        $this->putJson("/api/internal-notifications/{$notification->id}/read")
            ->assertNotFound();

        Sanctum::actingAs($inspectorUser);
        $this->getJson('/api/internal-notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('data.0.id', $notification->id)
            ->assertJsonPath('data.0.title', 'Nuevo retiro de estudiante')
            ->assertJsonPath('data.0.action_url', '/inspectoria/retiros');

        $this->putJson("/api/internal-notifications/{$notification->id}/read")
            ->assertOk();
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_it_requires_a_manual_inspector_when_the_course_has_no_assignment(): void
    {
        $user = $this->createUserWithPermissions(['ver_porteria', 'registrar_retiro_porteria']);
        [$student, , , $inspector] = $this->createActiveStudent(false);

        Sanctum::actingAs($user);

        $this->getJson("/api/porter/students/{$student->id}")
            ->assertOk()
            ->assertJsonPath('data.assigned_inspector', null);

        $this->getJson('/api/porter/catalogs')
            ->assertOk()
            ->assertJsonPath('inspectors.0.id', $inspector->id);

        $payload = [
            'student_profile_id' => $student->id,
            'person_name' => 'María Guardia',
            'person_rut' => '12.345.678-5',
            'person_relationship' => 'apoderado',
            'reason' => 'medico',
        ];

        $this->postJson('/api/porter/withdrawals', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['inspector_staff_id']);

        $this->postJson('/api/porter/withdrawals', [
            ...$payload,
            'inspector_staff_id' => $inspector->id,
        ])->assertCreated()
            ->assertJsonPath('data.inspector.id', $inspector->id);
    }

    public function test_observed_withdrawal_notifies_inspector_and_authorizers_and_reports_resolution(): void
    {
        $porter = $this->createUserWithPermissions(['ver_porteria', 'registrar_retiro_porteria']);
        $authorizer = $this->createUserWithPermissions(['autorizar_retiros_porteria']);
        [$student, , , $inspector] = $this->createActiveStudent();
        $inspectorUser = User::factory()->create([
            'name' => $inspector->full_name,
            'staff_id' => $inspector->id,
            'active' => true,
        ]);

        Sanctum::actingAs($porter);
        $withdrawalId = $this->postJson('/api/porter/withdrawals', [
            'student_profile_id' => $student->id,
            'inspector_staff_id' => $inspector->id,
            'person_name' => 'Persona sin autorización',
            'person_relationship' => 'otro',
            'reason' => 'otro',
        ])->assertCreated()->assertJsonPath('data.status', 'observado')->json('data.id');

        foreach ([$inspectorUser, $authorizer] as $recipient) {
            $notification = $recipient->notifications()->firstOrFail();
            $this->assertSame('withdrawal.created', $notification->data['event_type']);
            $this->assertSame('porter', $notification->data['module']);
            $this->assertSame('cnsc.operational-notification.v1', $notification->data['event']['schema']);
            $this->assertSame($withdrawalId, $notification->data['event']['resource']['id']);
            $this->assertSame('alta', $notification->data['priority']);
        }

        Sanctum::actingAs($authorizer);
        $this->postJson("/api/porter/withdrawals/{$withdrawalId}/resolve", [
            'decision' => 'autorizado',
            'reason' => 'Identidad verificada y autorización confirmada.',
        ])->assertOk();

        $statusNotification = $inspectorUser->notifications()
            ->get()
            ->first(fn ($notification) => ($notification->data['event_type'] ?? null) === 'withdrawal.status_changed');
        $this->assertNotNull($statusNotification);
        $this->assertSame('autorizado', $statusNotification->data['event']['context']['status']);
    }

    private function createUserWithPermissions(array $permissionSlugs): User
    {
        $user = User::factory()->create([
            'active' => true,
            'name' => 'Portería Test',
        ]);

        $permissions = collect($permissionSlugs)->map(function ($slug) {
            return Permission::query()->create([
                'slug' => $slug,
                'name' => ucfirst(str_replace('_', ' ', $slug)),
                'active' => true,
            ]);
        });

        $role = Role::query()->create([
            'name' => 'Portería Test',
            'slug' => 'porteria_test_'.uniqid(),
            'active' => true,
        ]);

        $role->permissions()->sync($permissions->pluck('id')->all());
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function createActiveStudent(bool $withAssignment = true): array
    {
        $year = AcademicYear::query()->create([
            'name' => '2026',
            'year' => 2026,
            'starts_at' => '2026-03-01',
            'ends_at' => '2026-12-31',
            'is_active' => true,
            'is_closed' => false,
        ]);

        $level = EducationLevel::query()->firstOrCreate(
            ['name' => '7° básico'],
            ['order' => ((int) EducationLevel::query()->max('order')) + 1, 'type' => 'basica'],
        );

        $course = CourseSection::query()->create([
            'academic_year_id' => $year->id,
            'education_level_id' => $level->id,
            'section_name' => 'A',
            'display_name' => '7° básico A',
            'capacity' => 30,
            'active' => true,
        ]);

        $student = StudentProfile::query()->create([
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'rut' => '22333444-5',
            'general_status' => 'activo',
            'guardian_name' => 'María Guardia',
            'guardian_rut' => '12345678-5',
            'guardian_phone' => '987654321',
            'authorized_pickup_people' => [
                [
                    'name' => 'Tía Rosa',
                    'rut' => '76086428-5',
                    'relationship' => 'familiar',
                    'phone' => '911111111',
                ],
            ],
        ]);

        StudentEnrollment::query()->create([
            'student_profile_id' => $student->id,
            'academic_year_id' => $year->id,
            'course_section_id' => $course->id,
            'enrollment_status' => 'regular',
            'enrolled_at' => '2026-03-01',
            'snapshot_year_name' => $year->name,
            'snapshot_level_name' => $level->name,
            'snapshot_section_name' => 'A',
            'snapshot_course_display_name' => $course->display_name,
        ]);

        $inspectorCargo = Cargo::query()->firstOrCreate(
            ['slug' => 'inspectoria'],
            ['name' => 'Inspectoría', 'description' => 'Inspectoras', 'active' => true],
        );
        $inspector = Staff::query()->create([
            'full_name' => 'Inspectora del curso',
            'rut' => '15.555.555-5',
            'cargo_id' => $inspectorCargo->id,
            'status' => 'activo',
            'active' => true,
        ]);

        if ($withAssignment) {
            InspectoriaCourseAssignment::query()->create([
                'academic_year_id' => $year->id,
                'course_section_id' => $course->id,
                'inspector_staff_id' => $inspector->id,
                'starts_on' => now()->subDay()->toDateString(),
                'active' => true,
            ]);
        }

        return [$student, $course, $year, $inspector];
    }
}
