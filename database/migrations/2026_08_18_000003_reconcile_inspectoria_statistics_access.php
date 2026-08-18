<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSION = 'ver_estadisticas_inspectoria';

    private const MODULE = 'inspectoria_estadisticas';

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('system_modules')) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();
            $parentId = DB::table('system_modules')->where('slug', 'inspectoria')->value('id');

            DB::table('permissions')->insertOrIgnore([
                'slug' => self::PERMISSION,
                'name' => 'Ver estadísticas de Inspectoría',
                'description' => 'Permite consultar y exportar estadísticas de atrasos de funcionarios por curso.',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('permissions')->where('slug', self::PERMISSION)->update([
                'name' => 'Ver estadísticas de Inspectoría',
                'description' => 'Permite consultar y exportar estadísticas de atrasos de funcionarios por curso.',
                'active' => true,
                'updated_at' => $now,
            ]);

            DB::table('system_modules')->insertOrIgnore([
                'slug' => self::MODULE,
                'name' => 'Estadísticas',
                'frontend_route' => '/inspectoria/estadisticas',
                'icon' => 'bx-bar-chart-alt-2',
                'sort_order' => 8,
                'active' => true,
                'parent_id' => $parentId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('system_modules')->where('slug', self::MODULE)->update([
                'name' => 'Estadísticas',
                'frontend_route' => '/inspectoria/estadisticas',
                'icon' => 'bx-bar-chart-alt-2',
                'sort_order' => 8,
                'active' => true,
                'parent_id' => $parentId,
                'updated_at' => $now,
            ]);

            $permissionId = DB::table('permissions')->where('slug', self::PERMISSION)->value('id');
            $moduleId = DB::table('system_modules')->where('slug', self::MODULE)->value('id');

            if ($permissionId && Schema::hasTable('permission_role') && Schema::hasTable('roles')) {
                foreach (DB::table('roles')->whereIn('slug', ['inspectoria', 'coordinador_inspectoria', 'super_admin'])->pluck('id') as $roleId) {
                    DB::table('permission_role')->insertOrIgnore([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            if ($moduleId && Schema::hasTable('role_system_module') && Schema::hasTable('roles')) {
                foreach (DB::table('roles')->whereIn('slug', ['inspectoria', 'coordinador_inspectoria', 'super_admin'])->pluck('id') as $roleId) {
                    DB::table('role_system_module')->insertOrIgnore([
                        'role_id' => $roleId,
                        'system_module_id' => $moduleId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            if ($permissionId && Schema::hasTable('permission_groups') && Schema::hasTable('permission_group_permission')) {
                $groupId = DB::table('permission_groups')->where('slug', 'inspectoria')->value('id');

                if ($groupId) {
                    DB::table('permission_group_permission')->insertOrIgnore([
                        'permission_group_id' => $groupId,
                        'permission_id' => $permissionId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        // Reconciliación aditiva: no se retiran accesos ni registros en reversión.
    }
};
