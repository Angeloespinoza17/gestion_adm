<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSION = 'pedagogical-instruments.statistics';

    private const MODULE = 'pedagogical_statistics';

    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions') || ! Schema::hasTable('system_modules')) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();
            DB::table('permissions')->insertOrIgnore([
                'slug' => self::PERMISSION,
                'name' => 'Ver estadísticas pedagógicas',
                'description' => 'Permite consultar evolución, brechas y trayectorias de informes documentales dentro del alcance autorizado.',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $parentId = DB::table('system_modules')->where('slug', 'pedagogical_management')->value('id');
            if (! $parentId) {
                return;
            }

            DB::table('system_modules')->insertOrIgnore([
                'slug' => self::MODULE,
                'name' => 'Evolución y estadísticas',
                'frontend_route' => '/gestion-pedagogica/estadisticas',
                'icon' => 'bx-line-chart',
                'sort_order' => 4,
                'active' => true,
                'parent_id' => $parentId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $permissionId = DB::table('permissions')->where('slug', self::PERMISSION)->value('id');
            $moduleId = DB::table('system_modules')->where('slug', self::MODULE)->value('id');
            $roleIds = DB::table('roles')->whereIn('slug', [
                'coordinadora_academica', 'coordinador_academico', 'jefe_utp',
                'super_admin', 'superadmin', 'administrador', 'direccion',
            ])->pluck('id');

            foreach ($roleIds as $roleId) {
                if ($permissionId && Schema::hasTable('permission_role')) {
                    DB::table('permission_role')->insertOrIgnore([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
                if ($moduleId && Schema::hasTable('role_system_module')) {
                    DB::table('role_system_module')->insertOrIgnore([
                        'role_id' => $roleId,
                        'system_module_id' => $moduleId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            if ($permissionId && Schema::hasTable('permission_groups') && Schema::hasTable('permission_group_permission')) {
                $groupId = DB::table('permission_groups')->where('slug', 'gestion_pedagogica')->value('id');
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
        // Cambio aditivo: conserva permisos y asignaciones existentes.
    }
};
