<?php

namespace Tests\Feature\Infirmary;

use App\Models\Inspectoria\InspectoriaPickupRestriction;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InfirmaryStudentContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_context_includes_primary_and_backup_guardian_contacts(): void
    {
        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super administrador', 'active' => true],
        );
        $user->roles()->attach($role);

        $student = StudentProfile::query()->create([
            'first_name' => 'Amalia Josefa',
            'last_name' => 'Mardones López',
            'rut' => '27018009-4',
            'guardian_name' => 'Carolina López',
            'guardian_phone' => '+56 9 1111 2222',
            'guardian_backup_name' => 'José Mardones',
            'guardian_backup_phone' => '+56 9 3333 4444',
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/infirmary/students/{$student->id}/context")
            ->assertOk()
            ->assertJsonPath('data.emergency_contacts.0.type', 'primary')
            ->assertJsonPath('data.emergency_contacts.0.label', 'Apoderado principal')
            ->assertJsonPath('data.emergency_contacts.0.name', 'Carolina López')
            ->assertJsonPath('data.emergency_contacts.0.phone', '+56 9 1111 2222')
            ->assertJsonPath('data.emergency_contacts.1.type', 'backup')
            ->assertJsonPath('data.emergency_contacts.1.label', 'Apoderado suplente')
            ->assertJsonPath('data.emergency_contacts.1.name', 'José Mardones')
            ->assertJsonPath('data.emergency_contacts.1.phone', '+56 9 3333 4444');

        $this->getJson('/api/infirmary/students?search=Amalia')
            ->assertOk()
            ->assertJsonPath('data.0.medical_context.emergency_contacts.0.name', 'Carolina López')
            ->assertJsonPath('data.0.medical_context.emergency_contacts.1.name', 'José Mardones');
    }

    public function test_student_search_matches_partial_name_tokens_in_any_order_and_ignores_accents(): void
    {
        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super administrador', 'active' => true],
        );
        $user->roles()->attach($role);

        $amalia = StudentProfile::query()->create([
            'first_name' => 'Amalia Josefa',
            'last_name' => 'Mardones López',
            'rut' => '27.018.009-4',
        ]);
        StudentProfile::query()->create([
            'first_name' => 'Amalia Fernanda',
            'last_name' => 'Zamora Díaz',
            'rut' => '21.111.222-3',
        ]);
        $jose = StudentProfile::query()->create([
            'first_name' => 'José Tomás',
            'last_name' => 'Núñez Pérez',
            'rut' => '22.222.333-4',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/infirmary/students?search=amalia%20mard')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $amalia->id);

        $this->getJson('/api/infirmary/students?search=lopez%20ama')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $amalia->id);

        $this->getJson('/api/infirmary/students?search=jose%20nunez')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $jose->id);

        $this->getJson('/api/infirmary/students?search=270180094')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $amalia->id);
    }

    public function test_context_marks_the_guardian_with_an_active_social_work_restriction(): void
    {
        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super administrador', 'active' => true],
        );
        $user->roles()->attach($role);

        $student = StudentProfile::query()->create([
            'first_name' => 'Josefina',
            'last_name' => 'Rojas',
            'rut' => '22.333.444-5',
            'guardian_name' => 'María de los Ángeles Rojas',
            'guardian_rut' => '11.111.111-1',
            'guardian_phone' => '+56 9 1111 1111',
            'guardian_backup_name' => 'Patricia Soto',
            'guardian_backup_rut' => '12.222.222-2',
            'guardian_backup_phone' => '+56 9 2222 2222',
        ]);

        InspectoriaPickupRestriction::query()->create([
            'student_profile_id' => $student->id,
            'restricted_person_name' => 'Maria de los Angeles Rojas',
            'restricted_person_rut' => '11.111.111-1',
            'restricted_person_relationship' => 'Madre',
            'restriction_type' => 'orden_alejamiento',
            'reason' => 'Medida vigente informada por tribunal.',
            'legal_reference' => 'RIT reservado C-123-2026',
            'starts_on' => today()->subWeek(),
            'ends_on' => null,
            'active' => true,
            'created_by_user_id' => $user->id,
            'updated_by_user_id' => $user->id,
        ]);

        InspectoriaPickupRestriction::query()->create([
            'student_profile_id' => $student->id,
            'restricted_person_name' => 'Patricia Soto',
            'restricted_person_rut' => '12.222.222-2',
            'restriction_type' => 'restriccion_familiar',
            'reason' => 'Medida ya finalizada.',
            'starts_on' => today()->subMonth(),
            'ends_on' => today()->subDay(),
            'active' => true,
            'created_by_user_id' => $user->id,
            'updated_by_user_id' => $user->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/infirmary/students/{$student->id}/context")
            ->assertOk()
            ->assertJsonCount(1, 'data.guardian_restrictions')
            ->assertJsonPath('data.guardian_restrictions.0.restriction_type_label', 'Orden de alejamiento')
            ->assertJsonPath('data.guardian_restrictions.0.source_label', 'Trabajo Social')
            ->assertJsonPath('data.emergency_contacts.0.has_active_restriction', true)
            ->assertJsonPath('data.emergency_contacts.0.restrictions.0.restriction_type', 'orden_alejamiento')
            ->assertJsonPath('data.emergency_contacts.0.contact_guidance', 'No contactar sin validar el protocolo y la medida vigente con Trabajo Social.')
            ->assertJsonPath('data.emergency_contacts.1.has_active_restriction', false)
            ->assertJsonCount(0, 'data.emergency_contacts.1.restrictions');

        $response->assertJsonMissingPath('data.guardian_restrictions.0.legal_reference');

        $this->getJson('/api/infirmary/students?search=Josefina')
            ->assertOk()
            ->assertJsonPath('data.0.medical_context.emergency_contacts.0.has_active_restriction', true)
            ->assertJsonPath('data.0.medical_context.emergency_contacts.1.has_active_restriction', false);
    }
}
