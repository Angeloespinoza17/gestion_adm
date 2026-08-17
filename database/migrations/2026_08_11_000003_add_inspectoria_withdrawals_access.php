<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSION = 'ver_retiros_inspectoria';

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('system_modules')) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();

            DB::table('permissions')->insertOrIgnore([
                'slug' => self::PERMISSION,
                'name' => 'Ver retiros de alumnas en Inspectoría',
                'description' => 'Permite consultar retiros registrados por Portería dentro de los cursos asignados.',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('permissions')->where('slug', self::PERMISSION)->update([
                'name' => 'Ver retiros de alumnas en Inspectoría',
                'description' => 'Permite consultar retiros registrados por Portería dentro de los cursos asignados.',
                'active' => true,
                'updated_at' => $now,
            ]);

            $parentId = DB::table('system_modules')->where('slug', 'inspectoria')->value('id');
            DB::table('system_modules')->insertOrIgnore([
                'slug' => 'inspectoria_retiros',
                'name' => 'Retiros de alumnas',
                'frontend_route' => '/inspectoria/retiros',
                'icon' => null,
                'sort_order' => 5,
                'parent_id' => $parentId,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('system_modules')->where('slug', 'inspectoria_retiros')->update([
                'name' => 'Retiros de alumnas',
                'frontend_route' => '/inspectoria/retiros',
                'sort_order' => 5,
                'parent_id' => $parentId,
                'active' => true,
                'updated_at' => $now,
            ]);
            DB::table('system_modules')->where('slug', 'inspectoria_bitacora')->update([
                'sort_order' => 6,
                'updated_at' => $now,
            ]);

            $permissionId = DB::table('permissions')->where('slug', self::PERMISSION)->value('id');
            $moduleId = DB::table('system_modules')->where('slug', 'inspectoria_retiros')->value('id');

            if (Schema::hasTable('permission_role')) {
                $roleIds = DB::table('roles')->whereIn('slug', ['inspectoria', 'coordinador_inspectoria'])->pluck('id');
                foreach ($roleIds as $roleId) {
                    DB::table('permission_role')->insertOrIgnore([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            if (Schema::hasTable('role_system_module')) {
                $roleIds = DB::table('roles')->whereIn('slug', ['inspectoria', 'coordinador_inspectoria'])->pluck('id');
                foreach ($roleIds as $roleId) {
                    DB::table('role_system_module')->insertOrIgnore([
                        'role_id' => $roleId,
                        'system_module_id' => $moduleId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            if (Schema::hasTable('permission_groups') && Schema::hasTable('permission_group_permission')) {
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
        // Catálogo aditivo: se conserva para no retirar accesos ya utilizados.
    }
};
