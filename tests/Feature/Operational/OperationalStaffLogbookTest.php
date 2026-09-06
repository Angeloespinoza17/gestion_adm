<?php

namespace Tests\Feature\Operational;

use App\Models\Operational\OperationalStaffLogEntry;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OperationalStaffLogbookTest extends TestCase
{
    use RefreshDatabase;

    private User $camila;

    private User $paula;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-05 15:30:00');

        $this->camila = $this->staffUser('Camila Funcionaria', '11111111-1');
        $this->paula = $this->staffUser('Paula Funcionaria', '22222222-2');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_each_staff_member_can_create_and_only_read_their_own_logbook(): void
    {
        Sanctum::actingAs($this->camila);
        $camilaEntry = $this->postJson('/api/operational/logbook', [
            'occurred_at' => '2026-09-05 09:15',
            'category' => 'meeting',
            'title' => 'Acuerdo de coordinación semanal',
            'details' => "Se definieron responsables.\nRevisar avances el lunes.",
            'owner_user_id' => $this->paula->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.owner.id', $this->camila->id)
            ->assertJsonPath('data.category_label', 'Reunión o acuerdo')
            ->assertJsonPath('data.can_edit', true)
            ->json('data');

        Sanctum::actingAs($this->paula);
        $paulaEntry = $this->postJson('/api/operational/logbook', [
            'occurred_at' => '2026-09-04 11:00',
            'category' => 'follow_up',
            'title' => 'Seguimiento de material pendiente',
            'details' => 'Proveedor confirmó la entrega para la próxima semana.',
        ])->assertCreated()->json('data');

        $this->getJson('/api/operational/logbook')
            ->assertOk()
            ->assertJsonPath('scope.mode', 'own')
            ->assertJsonPath('scope.is_superadmin', false)
            ->assertJsonPath('scope.privacy_note', 'Tu bitácora se mantiene en un espacio interno y protegido.')
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('data.0.id', $paulaEntry['id'])
            ->assertJsonMissing(['id' => $camilaEntry['id']]);

        $this->getJson('/api/operational/logbook?owner_user_id='.$this->camila->id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('owner_user_id');

        $this->putJson('/api/operational/logbook/'.$camilaEntry['id'], [
            'occurred_at' => '2026-09-05 09:15',
            'category' => 'meeting',
            'title' => 'Intento de cambio ajeno',
            'details' => 'No debe guardarse.',
        ])->assertForbidden();

        $this->assertDatabaseHas('operational_staff_log_entries', [
            'id' => $camilaEntry['id'],
            'owner_user_id' => $this->camila->id,
            'title' => 'Acuerdo de coordinación semanal',
        ]);
    }

    public function test_superadmin_consumes_every_staff_logbook_but_cannot_modify_another_owner_entry(): void
    {
        $first = $this->entryFor($this->camila, [
            'occurred_at' => '2026-09-05 08:00:00',
            'category' => 'incident',
            'title' => 'Incidencia de acceso',
            'details' => 'Se informó una dificultad operativa en el acceso norte.',
        ]);
        $this->entryFor($this->paula, [
            'occurred_at' => '2026-09-04 12:00:00',
            'category' => 'improvement',
            'title' => 'Propuesta para organizar materiales',
            'details' => 'Se propone identificar los estantes por color.',
        ]);
        $superAdmin = $this->superAdmin();

        Sanctum::actingAs($superAdmin);
        $response = $this->getJson('/api/operational/logbook')
            ->assertOk()
            ->assertJsonPath('scope.mode', 'all')
            ->assertJsonPath('scope.is_superadmin', true)
            ->assertJsonPath('summary.total', 2)
            ->assertJsonPath('summary.today', 1)
            ->assertJsonPath('summary.staff', 2)
            ->assertJsonCount(2, 'data')
            ->assertJsonCount(2, 'staff')
            ->assertJsonMissingPath('data.0.owner.email');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));

        $this->getJson('/api/operational/logbook?owner_user_id='.$this->camila->id.'&category=incident&search=acceso')
            ->assertOk()
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('data.0.owner.name', 'Camila Funcionaria')
            ->assertJsonPath('data.0.title', 'Incidencia de acceso');

        $this->putJson('/api/operational/logbook/'.$first->id, [
            'occurred_at' => '2026-09-05 08:00',
            'category' => 'incident',
            'title' => 'Cambio institucional indebido',
            'details' => 'Superadmin consulta, pero no altera registros ajenos.',
        ])->assertForbidden();
    }

    public function test_owner_can_correct_their_entry_and_custom_categories_are_validated(): void
    {
        Sanctum::actingAs($this->camila);

        $this->postJson('/api/operational/logbook', [
            'occurred_at' => '2026-09-05 09:00',
            'category' => 'other',
            'title' => 'Actividad especial',
            'details' => 'Antecedente con clasificación personalizada.',
        ])->assertUnprocessable()->assertJsonValidationErrors('custom_category');

        $this->postJson('/api/operational/logbook', [
            'occurred_at' => '2026-09-06 09:00',
            'category' => 'general',
            'title' => 'Fecha futura',
            'details' => 'No debe aceptarse.',
        ])->assertUnprocessable()->assertJsonValidationErrors('occurred_at');

        $entry = $this->entryFor($this->camila);
        $this->putJson('/api/operational/logbook/'.$entry->id, [
            'occurred_at' => '2026-09-03 10:30',
            'category' => 'other',
            'custom_category' => 'Coordinación de aniversario',
            'title' => 'Acuerdo corregido',
            'details' => 'Se ajustó el responsable de la actividad.',
        ])
            ->assertOk()
            ->assertJsonPath('data.category_label', 'Coordinación de aniversario')
            ->assertJsonPath('data.can_edit', true);

        $this->assertDatabaseHas('operational_staff_log_entries', [
            'id' => $entry->id,
            'owner_user_id' => $this->camila->id,
            'category' => 'other',
            'custom_category' => 'Coordinación de aniversario',
            'title' => 'Acuerdo corregido',
        ]);
    }

    public function test_students_guardians_inactive_and_home_only_accounts_are_excluded_even_with_assigned_permissions(): void
    {
        $role = Role::query()->create(['slug' => 'forced_logbook_access', 'name' => 'Acceso forzado', 'active' => true]);
        $role->permissions()->sync(Permission::query()->whereIn('slug', [
            'operational_logbook.view',
            'operational_logbook.create',
        ])->pluck('id'));

        $student = User::factory()->create([
            'name' => 'Estudiante',
            'user_type' => 'student',
            'student_id' => null,
            'active' => true,
        ]);
        $student->roles()->attach($role);
        Sanctum::actingAs($student);
        $this->getJson('/api/operational/logbook')->assertForbidden();
        $this->postJson('/api/operational/logbook', $this->payload())->assertForbidden();

        $guardian = User::factory()->create([
            'name' => 'Apoderado',
            'user_type' => 'guardian',
            'active' => true,
        ]);
        $guardian->roles()->attach($role);
        Sanctum::actingAs($guardian);
        $this->getJson('/api/operational/logbook')->assertForbidden();

        $inactiveStaff = $this->staffUser('Funcionario Inactivo', '33333333-3', false);
        Sanctum::actingAs($inactiveStaff);
        $this->getJson('/api/operational/logbook')->assertForbidden();

        $homeOnlyRole = Role::query()->create([
            'slug' => Role::TEMPORARY_HOME_ONLY_SLUG,
            'name' => 'Solo inicio',
            'active' => true,
        ]);
        $homeOnly = $this->staffUser('Funcionario Temporal', '44444444-4');
        $homeOnly->roles()->attach($homeOnlyRole);
        Sanctum::actingAs($homeOnly);
        $this->getJson('/api/operational/logbook')->assertForbidden();
    }

    public function test_navigation_and_permissions_expose_bitacora_only_to_staff_and_superadmin(): void
    {
        Sanctum::actingAs($this->camila);
        $permissions = $this->getJson('/api/me/permissions')->assertOk()->json('data');
        $modules = collect($this->getJson('/api/me/modules')->assertOk()->json('data'));

        $this->assertContains('operational_logbook.view', $permissions);
        $this->assertContains('operational_logbook.create', $permissions);
        $logbookModule = $modules->firstWhere('slug', 'operational_staff_logbook');
        $this->assertNotNull($logbookModule);
        $this->assertSame('/bitacora', $logbookModule['frontend_route']);
        $this->assertNull($logbookModule['parent_id']);

        $student = User::factory()->create(['user_type' => 'student', 'active' => true]);
        Sanctum::actingAs($student);
        $studentPermissions = $this->getJson('/api/me/permissions')->assertOk()->json('data');
        $studentModules = collect($this->getJson('/api/me/modules')->assertOk()->json('data'));

        $this->assertNotContains('operational_logbook.view', $studentPermissions);
        $this->assertNull($studentModules->firstWhere('slug', 'operational_staff_logbook'));

        $superAdmin = $this->superAdmin();
        Sanctum::actingAs($superAdmin);
        $superModules = collect($this->getJson('/api/me/modules')->assertOk()->json('data'));
        $this->assertNotNull($superModules->firstWhere('slug', 'operational_staff_logbook'));
    }

    private function staffUser(string $name, string $rut, bool $active = true): User
    {
        $staff = Staff::query()->create([
            'full_name' => $name,
            'rut' => $rut,
            'status' => $active ? 'activo' : 'inactivo',
            'active' => $active,
        ]);

        return User::factory()->create([
            'name' => $name,
            'user_type' => 'staff',
            'staff_id' => $staff->id,
            'active' => $active,
        ]);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create(['name' => 'Superadmin', 'user_type' => 'staff', 'active' => true]);
        $role = Role::query()->firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super Admin', 'active' => true],
        );
        $user->roles()->attach($role);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function entryFor(User $owner, array $overrides = []): OperationalStaffLogEntry
    {
        return OperationalStaffLogEntry::query()->create(array_replace([
            'owner_user_id' => $owner->id,
            'staff_id' => $owner->staff_id,
            'owner_name_snapshot' => $owner->name,
            'occurred_at' => '2026-09-03 10:00:00',
            'category' => 'general',
            'title' => 'Registro inicial',
            'details' => 'Antecedente operativo registrado por la funcionaria.',
        ], $overrides));
    }

    /**
     * @return array<string, string>
     */
    private function payload(): array
    {
        return [
            'occurred_at' => '2026-09-05 09:00',
            'category' => 'general',
            'title' => 'Registro no autorizado',
            'details' => 'Este registro no debe persistirse.',
        ];
    }
}
