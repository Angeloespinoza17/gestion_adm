<?php

namespace Tests\Feature\Rbac;

use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\SystemModule;
use App\Services\Rbac\RbacReconciliationService;
use App\Services\Rbac\RoleModuleSyncService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_is_reversible_and_apply_repairs_catalog_and_roles_idempotently(): void
    {
        $this->seed(RbacSeeder::class);

        $inventoryPermissions = Permission::query()
            ->whereIn('slug', [
                'ver_inventario',
                'ver_reportes_inventario',
                'imprimir_etiquetas_inventario',
                'administrar_categorias_inventario',
            ])
            ->pluck('id');
        $inventoryModules = SystemModule::query()
            ->where('slug', 'inventory')
            ->pluck('id');

        Role::query()
            ->whereIn('slug', ['enfermeria', 'estudiante'])
            ->get()
            ->each(function (Role $role) use ($inventoryPermissions, $inventoryModules): void {
                $role->permissions()->syncWithoutDetaching($inventoryPermissions);
                $role->modules()->syncWithoutDetaching($inventoryModules);
            });

        $service = app(RbacReconciliationService::class);
        $preview = $service->preview();

        $this->assertSame(0, $preview['audit']['critical_issue_count']);
        $this->assertSame(0, PermissionGroup::query()->count());
        $this->assertFalse(Permission::query()->where('slug', 'administrar_catalogos_enfermeria')->exists());

        $result = $service->apply();

        $this->assertSame(0, $result['audit']['critical_issue_count']);
        $this->assertSame(27, PermissionGroup::query()->where('active', true)->count());
        $this->assertTrue(Permission::query()->where('slug', 'administrar_catalogos_enfermeria')->where('active', true)->exists());

        $nurse = Role::query()->where('slug', 'enfermeria')->firstOrFail();
        $this->assertEqualsCanonicalizing([
            'ver_enfermeria',
            'crear_atenciones_enfermeria',
            'editar_atenciones_enfermeria',
            'eliminar_atenciones_enfermeria',
            'exportar_enfermeria',
            'administrar_inventario_enfermeria',
            'administrar_medicamentos_enfermeria',
            'administrar_catalogos_enfermeria',
            'gestionar_accidentes_enfermeria',
            'ver_reportes_enfermeria',
        ], $nurse->permissions()->whereIn('permissions.slug', [
            'ver_enfermeria',
            'crear_atenciones_enfermeria',
            'editar_atenciones_enfermeria',
            'eliminar_atenciones_enfermeria',
            'exportar_enfermeria',
            'administrar_inventario_enfermeria',
            'administrar_medicamentos_enfermeria',
            'administrar_catalogos_enfermeria',
            'gestionar_accidentes_enfermeria',
            'ver_reportes_enfermeria',
        ])->pluck('slug')->all());
        $this->assertSame(8, $nurse->modules()->whereIn('system_modules.slug', [
            'infirmary',
            'infirmary_dashboard',
            'infirmary_attentions',
            'infirmary_staff_attentions',
            'infirmary_categories',
            'infirmary_inventory',
            'infirmary_accidents',
            'infirmary_medications',
        ])->count());
        $this->assertFalse($nurse->permissions()->whereIn('permissions.slug', [
            'ver_inventario',
            'ver_reportes_inventario',
            'imprimir_etiquetas_inventario',
            'administrar_categorias_inventario',
        ])->exists());

        $student = Role::query()->where('slug', 'estudiante')->firstOrFail();
        $this->assertTrue($student->permissions()->where('permissions.slug', 'ver_dashboard')->exists());
        $this->assertFalse($student->permissions()->whereIn('permissions.slug', [
            'ver_inventario',
            'ver_reportes_inventario',
            'imprimir_etiquetas_inventario',
            'administrar_categorias_inventario',
        ])->exists());

        $secondResult = $service->apply();
        $this->assertSame([], $secondResult['permissions_created']);
        $this->assertSame([], $secondResult['modules_created']);
        $this->assertSame([], $secondResult['groups_created']);
        $this->assertSame([], $secondResult['role_changes']);
        $this->assertSame(0, $secondResult['audit']['critical_issue_count']);
    }

    public function test_group_permission_does_not_implicitly_grant_every_child_module(): void
    {
        $permission = Permission::query()->create(['name' => 'Ver módulo', 'slug' => 'ver_modulo', 'active' => true]);
        $parent = SystemModule::query()->create(['name' => 'Módulo', 'slug' => 'modulo', 'active' => true]);
        $child = SystemModule::query()->create([
            'name' => 'Administración',
            'slug' => 'modulo_administracion',
            'parent_id' => $parent->id,
            'active' => true,
        ]);
        $group = PermissionGroup::query()->create([
            'name' => 'Módulo',
            'slug' => 'modulo',
            'system_module_id' => $parent->id,
            'active' => true,
        ]);
        $group->permissions()->attach($permission);

        $moduleIds = app(RoleModuleSyncService::class)->moduleIdsForPermissionIds([$permission->id]);

        $this->assertContains($parent->id, $moduleIds);
        $this->assertNotContains($child->id, $moduleIds);
    }
}
