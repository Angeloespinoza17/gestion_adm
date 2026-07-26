<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('system_modules')) {
            return;
        }

        $now = now();

        $modules = [
            [
                'parent' => [
                    'slug' => 'accounting',
                    'name' => 'Contabilidad',
                    'icon' => 'bx-wallet-alt',
                    'sort_order' => 86,
                ],
                'group' => [
                    'slug' => 'contabilidad',
                    'name' => 'Contabilidad',
                    'description' => 'Presupuesto, centros de costo, cuentas, ingresos, egresos, pagos, conciliación, impuestos y reportes.',
                    'sort_order' => 240,
                ],
                'permissions' => [
                    ['slug' => 'contabilidad.acceso_confidencial', 'name' => 'Acceso confidencial a Contabilidad', 'description' => 'Barrera obligatoria para acceder a cualquier dato, vista o acción del módulo de Contabilidad.'],
                    ['slug' => 'contabilidad.ver', 'name' => 'Ver módulo Contabilidad', 'description' => 'Permite acceder al módulo de Contabilidad y consultar sus catálogos generales.'],
                    ['slug' => 'contabilidad.dashboard', 'name' => 'Ver dashboard Contabilidad', 'description' => 'Permite consultar indicadores, alertas y resúmenes del panel contable.'],
                    ['slug' => 'contabilidad.presupuesto.ver', 'name' => 'Ver presupuesto Contabilidad', 'description' => 'Permite consultar presupuestos y sus líneas de distribución.'],
                    ['slug' => 'contabilidad.presupuesto.crear', 'name' => 'Crear presupuesto Contabilidad', 'description' => 'Permite crear y modificar presupuestos y sus líneas.'],
                    ['slug' => 'contabilidad.presupuesto.aprobar', 'name' => 'Aprobar presupuesto Contabilidad', 'description' => 'Permite aprobar, observar y cerrar presupuestos contables.'],
                    ['slug' => 'contabilidad.centros_costo.gestionar', 'name' => 'Gestionar centros de costo', 'description' => 'Permite crear, editar y administrar centros de costo.'],
                    ['slug' => 'contabilidad.manual_cuentas.gestionar', 'name' => 'Gestionar manual de cuentas', 'description' => 'Permite administrar versiones y cuentas del manual contable.'],
                    ['slug' => 'contabilidad.ingresos.gestionar', 'name' => 'Gestionar ingresos', 'description' => 'Permite registrar y modificar ingresos contables.'],
                    ['slug' => 'contabilidad.egresos.gestionar', 'name' => 'Gestionar egresos', 'description' => 'Permite registrar y modificar egresos y documentos de gasto.'],
                    ['slug' => 'contabilidad.pagos.gestionar', 'name' => 'Gestionar pagos', 'description' => 'Permite registrar y administrar pagos y cuentas por pagar.'],
                    ['slug' => 'contabilidad.caja_chica.gestionar', 'name' => 'Gestionar caja chica', 'description' => 'Permite administrar cajas chicas y sus movimientos.'],
                    ['slug' => 'contabilidad.fondos_rendir.gestionar', 'name' => 'Gestionar fondos por rendir', 'description' => 'Permite administrar fondos, rendiciones y respaldos asociados.'],
                    ['slug' => 'contabilidad.conciliacion.gestionar', 'name' => 'Gestionar conciliación bancaria', 'description' => 'Permite registrar y conciliar movimientos bancarios.'],
                    ['slug' => 'contabilidad.subvenciones.ver', 'name' => 'Ver panel de subvenciones', 'description' => 'Permite consultar fuentes de financiamiento y paneles de subvenciones.'],
                    ['slug' => 'contabilidad.cheques.gestionar', 'name' => 'Gestionar cheques', 'description' => 'Permite emitir, actualizar y controlar cheques.'],
                    ['slug' => 'contabilidad.facturas.gestionar', 'name' => 'Gestionar facturas', 'description' => 'Permite registrar y administrar facturas contables.'],
                    ['slug' => 'contabilidad.boletas.gestionar', 'name' => 'Gestionar boletas de honorarios', 'description' => 'Permite registrar y administrar boletas de honorarios.'],
                    ['slug' => 'contabilidad.f29.gestionar', 'name' => 'Gestionar F29 interno', 'description' => 'Permite preparar y controlar períodos y declaraciones F29.'],
                    ['slug' => 'contabilidad.dj.gestionar', 'name' => 'Gestionar declaraciones juradas', 'description' => 'Permite preparar y administrar declaraciones juradas contables.'],
                    ['slug' => 'contabilidad.renta.gestionar', 'name' => 'Gestionar declaración de renta', 'description' => 'Permite preparar y administrar la declaración anual de renta.'],
                    ['slug' => 'contabilidad.balance.ver', 'name' => 'Ver balances contables', 'description' => 'Permite consultar balances, flujo de caja y reportes contables.'],
                    ['slug' => 'contabilidad.reportes.exportar', 'name' => 'Exportar reportes contables', 'description' => 'Permite descargar reportes y exportaciones del módulo contable.'],
                    ['slug' => 'contabilidad.admin', 'name' => 'Administrar módulo Contabilidad', 'description' => 'Otorga administración integral sobre todas las funciones de Contabilidad.'],
                ],
                'children' => [
                    ['slug' => 'accounting_dashboard', 'name' => 'Dashboard', 'frontend_route' => '/contabilidad', 'sort_order' => 1],
                    ['slug' => 'accounting_renderings', 'name' => 'Rendición de cuentas', 'frontend_route' => '/contabilidad/rendiciones', 'sort_order' => 2],
                    ['slug' => 'accounting_budgets', 'name' => 'Presupuesto anual', 'frontend_route' => '/contabilidad/presupuesto', 'sort_order' => 3],
                    ['slug' => 'accounting_cost_centers', 'name' => 'Centros de costo', 'frontend_route' => '/contabilidad/centros-costo', 'sort_order' => 4],
                    ['slug' => 'accounting_manual', 'name' => 'Manual de cuentas', 'frontend_route' => '/contabilidad/manual-cuentas', 'sort_order' => 5],
                    ['slug' => 'accounting_incomes', 'name' => 'Ingresos', 'frontend_route' => '/contabilidad/ingresos', 'sort_order' => 6],
                    ['slug' => 'accounting_expenses', 'name' => 'Egresos / pagos', 'frontend_route' => '/contabilidad/egresos', 'sort_order' => 7],
                    ['slug' => 'accounting_cash_funds', 'name' => 'Caja chica', 'frontend_route' => '/contabilidad/caja-chica', 'sort_order' => 8],
                    ['slug' => 'accounting_funds_to_render', 'name' => 'Fondos por rendir', 'frontend_route' => '/contabilidad/fondos-rendir', 'sort_order' => 9],
                    ['slug' => 'accounting_reconciliation', 'name' => 'Conciliación bancaria', 'frontend_route' => '/contabilidad/conciliacion', 'sort_order' => 10],
                    ['slug' => 'accounting_subsidies', 'name' => 'Subvenciones', 'frontend_route' => '/contabilidad/subvenciones', 'sort_order' => 11],
                    ['slug' => 'accounting_cheques', 'name' => 'Cheques', 'frontend_route' => '/contabilidad/cheques', 'sort_order' => 12],
                    ['slug' => 'accounting_invoices', 'name' => 'Facturas', 'frontend_route' => '/contabilidad/facturas', 'sort_order' => 13],
                    ['slug' => 'accounting_honoraries', 'name' => 'Boletas de honorarios', 'frontend_route' => '/contabilidad/boletas-honorarios', 'sort_order' => 14],
                    ['slug' => 'accounting_cashflow', 'name' => 'Flujo de caja', 'frontend_route' => '/contabilidad/flujo-caja', 'sort_order' => 15],
                    ['slug' => 'accounting_payables', 'name' => 'Cuentas por pagar', 'frontend_route' => '/contabilidad/cuentas-por-pagar', 'sort_order' => 16],
                    ['slug' => 'accounting_f29', 'name' => 'Gestión F29', 'frontend_route' => '/contabilidad/f29', 'sort_order' => 17],
                    ['slug' => 'accounting_balance', 'name' => 'Balance 8 y 9 columnas', 'frontend_route' => '/contabilidad/balance', 'sort_order' => 18],
                    ['slug' => 'accounting_dj_income', 'name' => 'DJ Ingresos', 'frontend_route' => '/contabilidad/dj-ingresos', 'sort_order' => 19],
                    ['slug' => 'accounting_dj_rental', 'name' => 'DJ Arriendo', 'frontend_route' => '/contabilidad/dj-arriendo', 'sort_order' => 20],
                    ['slug' => 'accounting_income_tax', 'name' => 'Declaración de Renta', 'frontend_route' => '/contabilidad/declaracion-renta', 'sort_order' => 21],
                    ['slug' => 'accounting_reports', 'name' => 'Reportes', 'frontend_route' => '/contabilidad/reportes', 'sort_order' => 22],
                ],
            ],
            [
                'parent' => [
                    'slug' => 'remuneration',
                    'name' => 'Remuneraciones',
                    'icon' => 'bx-money',
                    'sort_order' => 84,
                ],
                'group' => [
                    'slug' => 'remuneraciones',
                    'name' => 'Remuneraciones',
                    'description' => 'Trabajadores, contratos, parámetros, conceptos, movimientos, liquidaciones, pagos, períodos y gestión de RR.HH.',
                    'sort_order' => 250,
                ],
                'permissions' => [
                    ['slug' => 'remuneraciones.acceso_confidencial', 'name' => 'Acceso confidencial a Remuneraciones', 'description' => 'Barrera obligatoria para acceder a cualquier dato, vista o acción del módulo de Remuneraciones.'],
                    ['slug' => 'remuneraciones.ver', 'name' => 'Ver módulo Remuneraciones', 'description' => 'Permite acceder al módulo de Remuneraciones y consultar sus catálogos generales.'],
                    ['slug' => 'remuneraciones.dashboard', 'name' => 'Ver dashboard Remuneraciones', 'description' => 'Permite consultar indicadores y resúmenes del panel de remuneraciones.'],
                    ['slug' => 'remuneraciones.trabajadores.gestionar', 'name' => 'Gestionar fichas remuneracionales', 'description' => 'Permite crear y modificar fichas remuneracionales de funcionarios.'],
                    ['slug' => 'remuneraciones.contratos.gestionar', 'name' => 'Gestionar contratos remuneracionales', 'description' => 'Permite administrar condiciones contractuales usadas en liquidaciones.'],
                    ['slug' => 'remuneraciones.parametros.gestionar', 'name' => 'Gestionar parámetros legales de remuneraciones', 'description' => 'Permite administrar parámetros legales y previsionales.'],
                    ['slug' => 'remuneraciones.conceptos.gestionar', 'name' => 'Gestionar haberes y descuentos', 'description' => 'Permite administrar conceptos, haberes, descuentos y configuraciones del libro.'],
                    ['slug' => 'remuneraciones.movimientos.gestionar', 'name' => 'Gestionar movimientos de remuneraciones', 'description' => 'Permite registrar movimientos variables de remuneraciones.'],
                    ['slug' => 'remuneraciones.liquidaciones.calcular', 'name' => 'Calcular liquidaciones', 'description' => 'Permite calcular y recalcular liquidaciones individuales o masivas.'],
                    ['slug' => 'remuneraciones.liquidaciones.aprobar', 'name' => 'Aprobar liquidaciones', 'description' => 'Permite aprobar, observar o anular liquidaciones.'],
                    ['slug' => 'remuneraciones.pagos.gestionar', 'name' => 'Gestionar pagos de remuneraciones', 'description' => 'Permite registrar y administrar pagos de liquidaciones.'],
                    ['slug' => 'remuneraciones.contabilidad.centralizar', 'name' => 'Centralizar remuneraciones', 'description' => 'Permite generar la centralización contable de remuneraciones.'],
                    ['slug' => 'remuneraciones.reportes.ver', 'name' => 'Ver reportes de remuneraciones', 'description' => 'Permite consultar reportes, estadísticas y análisis del libro de remuneraciones.'],
                    ['slug' => 'remuneraciones.reportes.exportar', 'name' => 'Exportar reportes de remuneraciones', 'description' => 'Permite descargar reportes y documentos de remuneraciones.'],
                    ['slug' => 'remuneraciones.importar', 'name' => 'Importar libro de remuneraciones', 'description' => 'Permite previsualizar e importar libros de remuneraciones.'],
                    ['slug' => 'remuneraciones.periodos.cerrar', 'name' => 'Cerrar y reabrir períodos de remuneraciones', 'description' => 'Permite cerrar y reabrir períodos de liquidación.'],
                    ['slug' => 'remuneraciones.rrhh.gestionar', 'name' => 'Gestionar RR.HH. integral', 'description' => 'Permite administrar licencias, documentos, inducción, clima y recursos de RR.HH.'],
                    ['slug' => 'remuneraciones.admin', 'name' => 'Administrar módulo Remuneraciones', 'description' => 'Otorga administración integral sobre todas las funciones de Remuneraciones.'],
                ],
                'children' => [
                    ['slug' => 'remuneration_dashboard', 'name' => 'Dashboard', 'frontend_route' => '/remuneraciones', 'sort_order' => 1],
                    ['slug' => 'remuneration_employees', 'name' => 'Trabajadores', 'frontend_route' => '/remuneraciones/trabajadores', 'sort_order' => 2],
                    ['slug' => 'remuneration_contracts', 'name' => 'Contratos', 'frontend_route' => '/remuneraciones/contratos', 'sort_order' => 3],
                    ['slug' => 'remuneration_periods', 'name' => 'Períodos', 'frontend_route' => '/remuneraciones/periodos', 'sort_order' => 4],
                    ['slug' => 'remuneration_parameters', 'name' => 'Parámetros', 'frontend_route' => '/remuneraciones/parametros', 'sort_order' => 5],
                    ['slug' => 'remuneration_concepts', 'name' => 'Haberes y descuentos', 'frontend_route' => '/remuneraciones/conceptos', 'sort_order' => 6],
                    ['slug' => 'remuneration_movements', 'name' => 'Movimientos', 'frontend_route' => '/remuneraciones/movimientos', 'sort_order' => 7],
                    ['slug' => 'remuneration_payrolls', 'name' => 'Liquidaciones', 'frontend_route' => '/remuneraciones/liquidaciones', 'sort_order' => 8],
                    ['slug' => 'remuneration_imports', 'name' => 'Importaciones', 'frontend_route' => '/remuneraciones/importaciones', 'sort_order' => 9],
                    ['slug' => 'remuneration_import_rows', 'name' => 'Libro importado', 'frontend_route' => '/remuneraciones/libro-importado', 'sort_order' => 10],
                    ['slug' => 'remuneration_book_analytics', 'name' => 'Datos y estadísticas', 'frontend_route' => '/remuneraciones/estadisticas-libro', 'sort_order' => 11],
                    ['slug' => 'remuneration_payments', 'name' => 'Pagos', 'frontend_route' => '/remuneraciones/pagos', 'sort_order' => 12],
                    ['slug' => 'remuneration_accounting', 'name' => 'Centralización', 'frontend_route' => '/remuneraciones/centralizacion', 'sort_order' => 13],
                    ['slug' => 'remuneration_reports', 'name' => 'Reportes', 'frontend_route' => '/remuneraciones/reportes', 'sort_order' => 14],
                    ['slug' => 'remuneration_medical_leaves', 'name' => 'Licencias médicas', 'frontend_route' => '/remuneraciones/licencias-medicas', 'sort_order' => 15],
                    ['slug' => 'remuneration_birthdays', 'name' => 'Cumpleaños', 'frontend_route' => '/remuneraciones/cumpleanos', 'sort_order' => 16],
                    ['slug' => 'remuneration_permissions', 'name' => 'Permisos', 'frontend_route' => '/remuneraciones/permisos', 'sort_order' => 17],
                    ['slug' => 'remuneration_staff_management', 'name' => 'Gestión funcionarios', 'frontend_route' => '/remuneraciones/gestion-funcionarios', 'sort_order' => 18],
                    ['slug' => 'remuneration_departments', 'name' => 'Departamentos', 'frontend_route' => '/remuneraciones/departamentos', 'sort_order' => 19],
                    ['slug' => 'remuneration_functions', 'name' => 'Funciones', 'frontend_route' => '/remuneraciones/funciones', 'sort_order' => 20],
                    ['slug' => 'remuneration_documents', 'name' => 'Control documental', 'frontend_route' => '/remuneraciones/control-documental', 'sort_order' => 21],
                    ['slug' => 'remuneration_onboarding', 'name' => 'Inducción', 'frontend_route' => '/remuneraciones/induccion', 'sort_order' => 22],
                    ['slug' => 'remuneration_climate', 'name' => 'Clima laboral', 'frontend_route' => '/remuneraciones/clima-laboral', 'sort_order' => 23],
                    ['slug' => 'remuneration_climate_plans', 'name' => 'Planes clima', 'frontend_route' => '/remuneraciones/planes-clima', 'sort_order' => 24],
                    ['slug' => 'remuneration_workload', 'name' => 'Dotación y carga', 'frontend_route' => '/remuneraciones/dotacion-carga', 'sort_order' => 25],
                    ['slug' => 'remuneration_cv_bank', 'name' => 'Banco CV', 'frontend_route' => '/remuneraciones/banco-cv', 'sort_order' => 26],
                    ['slug' => 'remuneration_replacements', 'name' => 'Banco de reemplazos', 'frontend_route' => '/remuneraciones/reemplazos', 'sort_order' => 27],
                    ['slug' => 'remuneration_job_profiles', 'name' => 'Perfiles de cargo', 'frontend_route' => '/remuneraciones/perfiles-cargo', 'sort_order' => 28],
                    ['slug' => 'remuneration_certificates', 'name' => 'Certificados laborales', 'frontend_route' => '/remuneraciones/certificados', 'sort_order' => 29],
                    ['slug' => 'remuneration_audit', 'name' => 'Auditoría', 'frontend_route' => '/remuneraciones/auditoria', 'sort_order' => 30],
                ],
            ],
        ];

        foreach ($modules as $module) {
            $permissionIds = $this->ensurePermissions($module['permissions'], $now);
            $moduleIds = $this->ensureModules($module['parent'], $module['children'], $now);
            $parentId = $moduleIds[0] ?? null;

            if ($parentId) {
                $this->ensurePermissionGroup($module['group'], $parentId, $permissionIds, $now);
            }

            $this->attachToSuperAdmin($permissionIds, $moduleIds, $now);
        }
    }

    /**
     * @param  array<int, array{slug:string,name:string,description:string}>  $permissions
     * @return array<int, int>
     */
    private function ensurePermissions(array $permissions, $now): array
    {
        foreach ($permissions as $permission) {
            DB::table('permissions')->insertOrIgnore([
                ...$permission,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('permissions')
                ->where('slug', $permission['slug'])
                ->update([
                    'name' => $permission['name'],
                    'description' => $permission['description'],
                    'active' => true,
                    'updated_at' => $now,
                ]);
        }

        return DB::table('permissions')
            ->whereIn('slug', array_column($permissions, 'slug'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  array{slug:string,name:string,icon:string,sort_order:int}  $parent
     * @param  array<int, array{slug:string,name:string,frontend_route:string,sort_order:int}>  $children
     * @return array<int, int>
     */
    private function ensureModules(array $parent, array $children, $now): array
    {
        DB::table('system_modules')->insertOrIgnore([
            ...$parent,
            'frontend_route' => null,
            'active' => true,
            'parent_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('system_modules')
            ->where('slug', $parent['slug'])
            ->update([
                'name' => $parent['name'],
                'frontend_route' => null,
                'icon' => $parent['icon'],
                'sort_order' => $parent['sort_order'],
                'active' => true,
                'parent_id' => null,
                'updated_at' => $now,
            ]);

        $parentId = DB::table('system_modules')->where('slug', $parent['slug'])->value('id');

        if (! $parentId) {
            return [];
        }

        foreach ($children as $child) {
            DB::table('system_modules')->insertOrIgnore([
                ...$child,
                'icon' => null,
                'active' => true,
                'parent_id' => $parentId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('system_modules')
                ->where('slug', $child['slug'])
                ->update([
                    'name' => $child['name'],
                    'frontend_route' => $child['frontend_route'],
                    'sort_order' => $child['sort_order'],
                    'active' => true,
                    'parent_id' => $parentId,
                    'updated_at' => $now,
                ]);
        }

        return DB::table('system_modules')
            ->whereIn('slug', array_merge([$parent['slug']], array_column($children, 'slug')))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  array{slug:string,name:string,description:string,sort_order:int}  $group
     * @param  array<int, int>  $permissionIds
     */
    private function ensurePermissionGroup(array $group, int $parentId, array $permissionIds, $now): void
    {
        if (! Schema::hasTable('permission_groups') || ! Schema::hasTable('permission_group_permission')) {
            return;
        }

        DB::table('permission_groups')->insertOrIgnore([
            ...$group,
            'system_module_id' => $parentId,
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('permission_groups')
            ->where('slug', $group['slug'])
            ->update([
                'system_module_id' => $parentId,
                'name' => $group['name'],
                'description' => $group['description'],
                'sort_order' => $group['sort_order'],
                'active' => true,
                'updated_at' => $now,
            ]);

        $groupId = DB::table('permission_groups')->where('slug', $group['slug'])->value('id');

        if (! $groupId) {
            return;
        }

        foreach ($permissionIds as $permissionId) {
            DB::table('permission_group_permission')->insertOrIgnore([
                'permission_group_id' => $groupId,
                'permission_id' => $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * @param  array<int, int>  $permissionIds
     * @param  array<int, int>  $moduleIds
     */
    private function attachToSuperAdmin(array $permissionIds, array $moduleIds, $now): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $roleId = DB::table('roles')->where('slug', 'super_admin')->value('id');

        if (! $roleId) {
            return;
        }

        if (Schema::hasTable('permission_role')) {
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (Schema::hasTable('role_system_module')) {
            foreach ($moduleIds as $moduleId) {
                DB::table('role_system_module')->insertOrIgnore([
                    'role_id' => $roleId,
                    'system_module_id' => $moduleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Cambio aditivo: no se eliminan permisos, módulos, roles ni asignaciones existentes.
    }
};
