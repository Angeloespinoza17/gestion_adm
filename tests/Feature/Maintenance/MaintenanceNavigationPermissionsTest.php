<?php

namespace Tests\Feature\Maintenance;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemModule;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MaintenanceNavigationPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_exposes_report_navigation_to_roles_that_already_can_view_reports(): void
    {
        $role = Role::query()->create([
            'name' => 'Reportes Mantención',
            'slug' => 'reportes-mantencion-prueba',
            'active' => true,
        ]);
        $permission = Permission::query()->where('slug', 'ver_reportes_mantencion')->firstOrFail();
        $role->permissions()->attach($permission);

        $migration = require database_path('migrations/2026_07_29_210000_add_maintenance_reports_navigation_and_permissions.php');
        $migration->up();

        $reportModule = SystemModule::query()->where('slug', 'maintenance_reports')->firstOrFail();

        $this->assertSame('/maintenance', $reportModule->frontend_route);
        $this->assertSame(1, $reportModule->sort_order);
        $this->assertTrue($role->modules()->where('system_modules.slug', 'maintenance_reports')->exists());

        $user = User::factory()->create(['active' => true, 'user_type' => 'staff']);
        $user->roles()->attach($role);
        Sanctum::actingAs($user);

        $this->getJson('/api/me/modules')
            ->assertOk()
            ->assertJsonFragment([
                'slug' => 'maintenance_reports',
                'frontend_route' => '/maintenance',
            ]);
    }

    public function test_rbac_catalog_assigns_report_module_without_granting_export_to_read_only_roles(): void
    {
        $this->seed(RbacSeeder::class);

        $this->assertDatabaseHas('system_modules', [
            'slug' => 'maintenance_reports',
            'frontend_route' => '/maintenance',
            'sort_order' => 1,
            'active' => true,
        ]);
        $this->assertDatabaseHas('permissions', [
            'slug' => 'ver_reportes_mantencion',
            'active' => true,
        ]);
        $this->assertDatabaseHas('permissions', [
            'slug' => 'exportar_mantencion',
            'active' => true,
        ]);

        foreach (['administrador', 'direccion', 'encargado_mantencion', 'prevencion_riesgos'] as $roleSlug) {
            $role = Role::query()->where('slug', $roleSlug)->firstOrFail();

            $this->assertTrue(
                $role->modules()->where('system_modules.slug', 'maintenance_reports')->exists(),
                "El rol {$roleSlug} debe ver el acceso /maintenance. Módulos actuales: "
                    .$role->modules()->pluck('system_modules.slug')->implode(', ')
            );
        }

        foreach (['direccion', 'prevencion_riesgos'] as $roleSlug) {
            $role = Role::query()->where('slug', $roleSlug)->firstOrFail();

            $this->assertFalse(
                $role->permissions()->where('permissions.slug', 'exportar_mantencion')->exists(),
                "El rol de solo lectura {$roleSlug} no debe recibir permisos de exportación implícitos."
            );
        }
    }
}
