<?php

namespace Tests\Feature\Library;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LibraryManagerRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private const PERMISSIONS = [
        'ver_modulo_biblioteca',
        'crear_libros_biblioteca',
        'editar_libros_biblioteca',
        'eliminar_libros_biblioteca',
        'administrar_catalogo_biblioteca',
        'administrar_inventario_biblioteca',
        'registrar_prestamos_biblioteca',
        'registrar_devoluciones_biblioteca',
        'renovar_prestamos_biblioteca',
        'gestionar_mora_biblioteca',
        'gestionar_reservas_biblioteca',
        'gestionar_plan_lector_biblioteca',
        'gestionar_uso_espacios_biblioteca',
        'gestionar_categorias_biblioteca',
        'gestionar_almacenaje_biblioteca',
        'gestionar_textos_escolares_biblioteca',
        'gestionar_materiales_biblioteca',
        'gestionar_pases_biblioteca',
        'ver_estadisticas_biblioteca',
        'exportar_reportes_biblioteca',
    ];

    private const MODULES = [
        'biblioteca',
        'biblioteca_dashboard',
        'biblioteca_catalogo',
        'biblioteca_categorias',
        'biblioteca_almacenaje',
        'biblioteca_inventario',
        'biblioteca_prestamos',
        'biblioteca_materiales',
        'biblioteca_textos_escolares',
        'biblioteca_reservas',
        'biblioteca_plan_lector',
        'biblioteca_espacios',
        'biblioteca_pases',
        'biblioteca_reportes',
    ];

    public function test_library_manager_role_has_every_library_permission_and_module(): void
    {
        $role = Role::query()
            ->with(['permissions', 'modules'])
            ->where('slug', 'encargado_biblioteca')
            ->firstOrFail();

        $this->assertSame('Encargado de Biblioteca', $role->name);
        $this->assertTrue($role->active);
        $this->assertEmpty(array_diff(self::PERMISSIONS, $role->permissions->pluck('slug')->all()));
        $this->assertEmpty(array_diff(self::MODULES, $role->modules->pluck('slug')->all()));
    }

    public function test_library_manager_can_access_the_library_dashboard(): void
    {
        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->where('slug', 'encargado_biblioteca')->firstOrFail();
        $user->roles()->attach($role);

        Sanctum::actingAs($user);

        $this->getJson('/api/biblioteca/dashboard')->assertOk();
    }

    public function test_library_manager_migration_is_idempotent(): void
    {
        $role = Role::query()->where('slug', 'encargado_biblioteca')->firstOrFail();
        $permissionCount = $role->permissions()->count();
        $moduleCount = $role->modules()->count();

        $migration = require database_path('migrations/2026_08_10_000004_create_library_manager_role.php');
        $migration->up();

        $role->refresh();

        $this->assertSame($permissionCount, $role->permissions()->count());
        $this->assertSame($moduleCount, $role->modules()->count());
        $this->assertSame(1, Role::query()->where('slug', 'encargado_biblioteca')->count());
    }
}
