<?php

namespace Tests\Feature\Rbac;

use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\SystemModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingRemunerationPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private const ACCOUNTING_PERMISSIONS = [
        'contabilidad.acceso_confidencial',
        'contabilidad.ver',
        'contabilidad.dashboard',
        'contabilidad.presupuesto.ver',
        'contabilidad.presupuesto.crear',
        'contabilidad.presupuesto.aprobar',
        'contabilidad.centros_costo.gestionar',
        'contabilidad.manual_cuentas.gestionar',
        'contabilidad.ingresos.gestionar',
        'contabilidad.egresos.gestionar',
        'contabilidad.ejecucion_presupuestaria.ver',
        'contabilidad.ejecucion_presupuestaria.importar',
        'contabilidad.ejecucion_presupuestaria.exportar',
        'contabilidad.pagos.gestionar',
        'contabilidad.caja_chica.gestionar',
        'contabilidad.fondos_rendir.gestionar',
        'contabilidad.conciliacion.gestionar',
        'contabilidad.subvenciones.ver',
        'contabilidad.subvenciones.importar',
        'contabilidad.subvenciones.aprobar',
        'contabilidad.subvenciones.conciliar',
        'contabilidad.subvenciones.contabilizar',
        'contabilidad.cheques.gestionar',
        'contabilidad.facturas.gestionar',
        'contabilidad.boletas.gestionar',
        'contabilidad.f29.gestionar',
        'contabilidad.dj.gestionar',
        'contabilidad.renta.gestionar',
        'contabilidad.balance.ver',
        'contabilidad.reportes.exportar',
        'contabilidad.admin',
    ];

    private const REMUNERATION_PERMISSIONS = [
        'remuneraciones.acceso_confidencial',
        'remuneraciones.ver',
        'remuneraciones.dashboard',
        'remuneraciones.trabajadores.gestionar',
        'remuneraciones.contratos.gestionar',
        'remuneraciones.parametros.gestionar',
        'remuneraciones.conceptos.gestionar',
        'remuneraciones.movimientos.gestionar',
        'remuneraciones.liquidaciones.calcular',
        'remuneraciones.liquidaciones.aprobar',
        'remuneraciones.pagos.gestionar',
        'remuneraciones.contabilidad.centralizar',
        'remuneraciones.reportes.ver',
        'remuneraciones.reportes.exportar',
        'remuneraciones.importar',
        'remuneraciones.periodos.cerrar',
        'remuneraciones.rrhh.gestionar',
        'remuneraciones.admin',
        'remuneraciones.liquidaciones_pdf.ver',
        'remuneraciones.liquidaciones_pdf.importar',
        'remuneraciones.liquidaciones_pdf.incidencias',
        'remuneraciones.liquidaciones_pdf.reprocesar',
        'remuneraciones.liquidaciones_pdf.exportar',
        'remuneraciones.liquidaciones_pdf.propuesta_pago',
        'remuneraciones.liquidaciones_pdf.anular',
        'remuneraciones.liquidaciones_pdf.auditoria',
    ];

    public function test_permission_matrices_are_installed_and_grouped(): void
    {
        $this->assertPermissionGroupContains('contabilidad', self::ACCOUNTING_PERMISSIONS);
        $this->assertPermissionGroupContains('remuneraciones', self::REMUNERATION_PERMISSIONS);
    }

    public function test_navigation_modules_are_installed_under_their_correct_parents(): void
    {
        $accounting = SystemModule::query()->where('slug', 'accounting')->firstOrFail();
        $remuneration = SystemModule::query()->where('slug', 'remuneration')->firstOrFail();

        $this->assertTrue($accounting->active);
        $this->assertTrue($remuneration->active);
        $this->assertSame(22, $accounting->children()->count());
        $this->assertSame(34, $remuneration->children()->count());

        $this->assertDatabaseHas('system_modules', [
            'slug' => 'accounting_dashboard',
            'parent_id' => $accounting->id,
            'frontend_route' => '/contabilidad',
            'active' => true,
        ]);
        $this->assertDatabaseHas('system_modules', [
            'slug' => 'remuneration_dashboard',
            'parent_id' => $remuneration->id,
            'frontend_route' => '/remuneraciones',
            'active' => true,
        ]);
        $this->assertDatabaseHas('system_modules', [
            'slug' => 'remuneration_payslips_pdf',
            'parent_id' => $remuneration->id,
            'frontend_route' => '/remuneraciones/liquidaciones-sueldo',
            'active' => true,
        ]);
    }

    public function test_migration_is_additive_idempotent_and_preserves_existing_assignments(): void
    {
        $role = Role::query()->create([
            'name' => 'Rol existente',
            'slug' => 'rol_existente',
            'active' => true,
        ]);
        $sentinel = Permission::query()->create([
            'name' => 'Permiso existente',
            'slug' => 'permiso.existente',
            'description' => 'No debe eliminarse.',
            'active' => true,
        ]);
        $role->permissions()->attach($sentinel->id);

        $superAdmin = Role::query()->firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super Admin', 'active' => true],
        );

        $migration = require database_path('migrations/2026_07_26_190000_ensure_accounting_and_remuneration_permissions.php');
        $migration->up();
        $migration->up();

        $this->assertDatabaseHas('permissions', ['id' => $sentinel->id, 'slug' => 'permiso.existente']);
        $this->assertDatabaseHas('permission_role', [
            'role_id' => $role->id,
            'permission_id' => $sentinel->id,
        ]);
        $this->assertDatabaseMissing('permission_role', [
            'role_id' => $role->id,
            'permission_id' => Permission::query()->where('slug', 'contabilidad.acceso_confidencial')->value('id'),
        ]);
        $this->assertDatabaseMissing('permission_role', [
            'role_id' => $role->id,
            'permission_id' => Permission::query()->where('slug', 'remuneraciones.acceso_confidencial')->value('id'),
        ]);

        $subsidyWorkflowPermissions = [
            'contabilidad.subvenciones.importar',
            'contabilidad.subvenciones.aprobar',
            'contabilidad.subvenciones.conciliar',
            'contabilidad.subvenciones.contabilizar',
        ];
        $expectedPermissions = array_merge(
            array_values(array_diff(self::ACCOUNTING_PERMISSIONS, $subsidyWorkflowPermissions)),
            array_values(array_filter(
                self::REMUNERATION_PERMISSIONS,
                fn (string $slug): bool => ! str_starts_with($slug, 'remuneraciones.liquidaciones_pdf.'),
            )),
        );
        $this->assertEqualsCanonicalizing(
            $expectedPermissions,
            $superAdmin->permissions()
                ->whereIn('slug', $expectedPermissions)
                ->pluck('slug')
                ->all(),
        );

        $expectedModuleIds = SystemModule::query()
            ->whereIn('slug', [
                'accounting',
                'accounting_dashboard',
                'remuneration',
                'remuneration_dashboard',
                'remuneration_payslips_pdf',
                'remuneration_payslip_matrix',
                'remuneration_payslip_reconciliation',
                'remuneration_payslip_imports',
            ])
            ->pluck('id')
            ->all();

        $this->assertEqualsCanonicalizing(
            $expectedModuleIds,
            $superAdmin->modules()->whereIn('system_modules.id', $expectedModuleIds)->pluck('system_modules.id')->all(),
        );
    }

    /**
     * @param  array<int, string>  $permissionSlugs
     */
    private function assertPermissionGroupContains(string $groupSlug, array $permissionSlugs): void
    {
        $permissions = Permission::query()
            ->whereIn('slug', $permissionSlugs)
            ->get();

        $this->assertCount(count($permissionSlugs), $permissions);
        $this->assertTrue($permissions->every(fn (Permission $permission) => $permission->active));

        $group = PermissionGroup::query()
            ->with('permissions')
            ->where('slug', $groupSlug)
            ->firstOrFail();

        $this->assertTrue($group->active);
        $this->assertEqualsCanonicalizing(
            $permissionSlugs,
            $group->permissions->pluck('slug')->all(),
        );
    }
}
