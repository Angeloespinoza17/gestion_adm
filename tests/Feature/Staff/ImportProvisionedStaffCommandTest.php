<?php

namespace Tests\Feature\Staff;

use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\SystemModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ImportProvisionedStaffCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_provisions_staff_accounts_without_removing_departments_or_existing_roles(): void
    {
        $this->homeAccess();
        $existingRole = Role::query()->create([
            'name' => 'Rol existente',
            'slug' => 'rol_existente',
            'active' => true,
        ]);
        $department = Department::query()->create([
            'name' => 'Equipo directivo',
            'slug' => 'equipo_directivo',
            'active' => true,
        ]);
        $staff = Staff::query()->create([
            'full_name' => 'Nombre anterior',
            'rut' => '12345678-5',
            'institutional_email' => 'existente@example.test',
            'status' => 'activo',
            'active' => true,
        ]);
        $staff->departments()->attach($department);
        $existingUser = User::factory()->create([
            'email' => 'existente@example.test',
            'staff_id' => $staff->id,
            'user_type' => 'staff',
            'active' => true,
        ]);
        $existingUser->roles()->attach($existingRole);

        $path = $this->jsonFile([
            $this->record([
                'full_name' => 'Nombre Actualizado',
                'rut' => '12.345.678-5',
                'institutional_email' => 'existente@example.test',
                'account_email' => 'existente@example.test',
                'cargo' => 'Profesora Educación General Básica',
            ]),
            $this->record([
                'source_row' => 6,
                'full_name' => 'Cuenta Sin Correo',
                'rut' => '11.111.111-1',
                'institutional_email' => null,
                'account_email' => 'funcionario.111111111@cnscgestion.local',
                'generated_account_email' => true,
            ]),
        ]);

        try {
            $exitCode = Artisan::call('staff:import-provisioned', [
                'json' => $path,
                '--password' => 'ADMINADMIN',
            ]);
        } finally {
            @unlink($path);
        }

        $this->assertSame(0, $exitCode, Artisan::output());

        $temporaryRole = Role::query()
            ->where('slug', Role::TEMPORARY_HOME_ONLY_SLUG)
            ->firstOrFail();
        $this->assertSame(['ver_dashboard'], $temporaryRole->permissions()->pluck('slug')->all());
        $this->assertSame(['dashboard'], $temporaryRole->modules()->pluck('slug')->all());

        $updatedStaff = $staff->fresh();
        $updatedUser = $existingUser->fresh();
        $this->assertSame('Nombre Actualizado', $updatedStaff->full_name);
        $this->assertTrue($updatedStaff->departments()->whereKey($department->id)->exists());
        $this->assertTrue($updatedUser->roles()->whereKey($existingRole->id)->exists());
        $this->assertTrue($updatedUser->roles()->whereKey($temporaryRole->id)->exists());
        $this->assertTrue(Hash::check('ADMINADMIN', $updatedUser->password));

        $newStaff = Staff::query()->where('rut', '11111111-1')->firstOrFail();
        $newUser = User::query()->where('staff_id', $newStaff->id)->firstOrFail();
        $this->assertNull($newStaff->institutional_email);
        $this->assertSame('funcionario.111111111@cnscgestion.local', $newUser->email);
        $this->assertSame('staff', $newUser->user_type);
        $this->assertCount(0, $newStaff->departments);
        $this->assertSame([Role::TEMPORARY_HOME_ONLY_SLUG], $newUser->roles()->pluck('slug')->all());
        $this->assertSame(['ver_dashboard'], $newUser->permissionSlugs());
        $this->assertTrue(Hash::check('ADMINADMIN', $newUser->password));

        Sanctum::actingAs($newUser);
        $this->getJson('/api/me/permissions')
            ->assertOk()
            ->assertExactJson(['data' => ['ver_dashboard']]);
        $this->getJson('/api/me/modules')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'dashboard')
            ->assertJsonPath('data.0.frontend_route', '/inicio');
    }

    public function test_dry_run_rolls_back_every_change(): void
    {
        $this->homeAccess();
        $path = $this->jsonFile([$this->record()]);

        try {
            $exitCode = Artisan::call('staff:import-provisioned', [
                'json' => $path,
                '--password' => 'ADMINADMIN',
                '--dry-run' => true,
            ]);
        } finally {
            @unlink($path);
        }

        $this->assertSame(0, $exitCode, Artisan::output());
        $this->assertDatabaseCount('staff', 0);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseMissing('roles', ['slug' => Role::TEMPORARY_HOME_ONLY_SLUG]);
    }

    private function homeAccess(): void
    {
        Permission::query()->create([
            'name' => 'Ver Dashboard',
            'slug' => 'ver_dashboard',
            'active' => true,
        ]);
        SystemModule::query()->create([
            'name' => 'Inicio',
            'slug' => 'dashboard',
            'frontend_route' => '/inicio',
            'active' => true,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function record(array $overrides = []): array
    {
        return array_merge([
            'source_row' => 5,
            'full_name' => 'Funcionaria Importada',
            'rut' => '12.345.678-5',
            'birth_date' => '1990-01-02',
            'institutional_email' => 'funcionaria@example.test',
            'personal_email' => 'personal@example.test',
            'account_email' => 'funcionaria@example.test',
            'generated_account_email' => false,
            'phone' => null,
            'address' => null,
            'region' => null,
            'commune' => null,
            'cargo' => null,
            'contract_type' => 'indefinido',
            'start_date' => null,
            'end_date' => null,
            'status' => null,
            'workday' => null,
            'contract_hours' => null,
            'professional_title' => null,
            'specialty' => null,
            'professional_registration' => null,
            'internal_notes' => null,
            'active' => true,
            'can_receive_maintenance_orders' => false,
            'maintenance_role' => null,
        ], $overrides);
    }

    /** @param array<int, array<string, mixed>> $records */
    private function jsonFile(array $records): string
    {
        $path = tempnam(sys_get_temp_dir(), 'provisioned-staff-');
        $this->assertNotFalse($path);
        file_put_contents($path, json_encode($records, JSON_THROW_ON_ERROR));

        return $path;
    }
}
