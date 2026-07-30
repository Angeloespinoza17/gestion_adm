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
        $permissions = [
            [
                'slug' => 'ver_reportes_mantencion',
                'name' => 'Ver Reportes Mantención',
                'description' => 'Permite ver el resumen ejecutivo y los reportes operativos de Mantención.',
            ],
            [
                'slug' => 'exportar_mantencion',
                'name' => 'Exportar Mantención',
                'description' => 'Permite exportar los informes de Mantención en Excel y PDF.',
            ],
        ];

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

        DB::table('system_modules')->insertOrIgnore([
            'slug' => 'maintenance',
            'name' => 'Mantención',
            'frontend_route' => null,
            'icon' => 'bx-wrench',
            'sort_order' => 90,
            'active' => true,
            'parent_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('system_modules')
            ->where('slug', 'maintenance')
            ->update([
                'name' => 'Mantención',
                'icon' => 'bx-wrench',
                'active' => true,
                'updated_at' => $now,
            ]);

        $parentId = DB::table('system_modules')->where('slug', 'maintenance')->value('id');

        if (! $parentId) {
            return;
        }

        $children = [
            ['slug' => 'maintenance_reports', 'name' => 'Resumen y reportes', 'frontend_route' => '/maintenance', 'sort_order' => 1],
            ['slug' => 'maintenance_dependencies', 'name' => 'Áreas técnicas', 'frontend_route' => '/maintenance/dependencies', 'sort_order' => 2],
            ['slug' => 'maintenance_work_orders', 'name' => 'Órdenes de trabajo', 'frontend_route' => '/maintenance/work-orders', 'sort_order' => 3],
            ['slug' => 'maintenance_workload', 'name' => 'Carga de trabajo', 'frontend_route' => '/maintenance/workload', 'sort_order' => 4],
            ['slug' => 'maintenance_visits', 'name' => 'Planificación visitas', 'frontend_route' => '/maintenance/visits', 'sort_order' => 5],
            ['slug' => 'maintenance_annual_plans', 'name' => 'Plan anual mantención', 'frontend_route' => '/maintenance/annual-plans', 'sort_order' => 6],
        ];

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

        $reportModuleId = DB::table('system_modules')
            ->where('slug', 'maintenance_reports')
            ->value('id');
        $viewPermissionId = DB::table('permissions')
            ->where('slug', 'ver_reportes_mantencion')
            ->value('id');

        if (Schema::hasTable('permission_groups') && Schema::hasTable('permission_group_permission')) {
            DB::table('permission_groups')->insertOrIgnore([
                'system_module_id' => $parentId,
                'name' => 'Mantención',
                'slug' => 'mantencion',
                'description' => 'Resumen ejecutivo, reportes, órdenes de trabajo, visitas, carga de trabajo y plan anual de Mantención.',
                'sort_order' => 140,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('permission_groups')
                ->where('slug', 'mantencion')
                ->update([
                    'system_module_id' => $parentId,
                    'name' => 'Mantención',
                    'description' => 'Resumen ejecutivo, reportes, órdenes de trabajo, visitas, carga de trabajo y plan anual de Mantención.',
                    'sort_order' => 140,
                    'active' => true,
                    'updated_at' => $now,
                ]);

            $groupId = DB::table('permission_groups')->where('slug', 'mantencion')->value('id');
            $permissionIds = DB::table('permissions')
                ->whereIn('slug', array_column($permissions, 'slug'))
                ->pluck('id');

            foreach ($permissionIds as $permissionId) {
                DB::table('permission_group_permission')->insertOrIgnore([
                    'permission_group_id' => $groupId,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (! $reportModuleId || ! Schema::hasTable('roles') || ! Schema::hasTable('role_system_module')) {
            return;
        }

        $roleIds = collect();

        if ($viewPermissionId && Schema::hasTable('permission_role')) {
            $roleIds = $roleIds->merge(
                DB::table('permission_role')
                    ->where('permission_id', $viewPermissionId)
                    ->pluck('role_id')
            );
        }

        $roleIds = $roleIds
            ->merge(DB::table('roles')->whereIn('slug', ['super_admin', 'superadmin'])->pluck('id'))
            ->unique()
            ->values();

        foreach ($roleIds as $roleId) {
            DB::table('role_system_module')->insertOrIgnore([
                'role_id' => $roleId,
                'system_module_id' => $reportModuleId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Cambio aditivo: no se eliminan módulos, permisos ni asignaciones existentes.
    }
};
