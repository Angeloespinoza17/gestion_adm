<?php

namespace Tests\Feature\Infirmary;

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
        $role = Role::query()->create([
            'name' => 'Super administrador',
            'slug' => 'super_admin',
            'active' => true,
        ]);
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
        $role = Role::query()->create([
            'name' => 'Super administrador',
            'slug' => 'super_admin',
            'active' => true,
        ]);
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
}
