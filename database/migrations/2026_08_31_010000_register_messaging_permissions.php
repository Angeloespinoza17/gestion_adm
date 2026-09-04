<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSIONS = [
        'messaging.send_announcement' => 'Enviar anuncios institucionales',
        'messaging.view_receipts' => 'Ver informes globales de acuses',
        'messaging.send_reminder' => 'Enviar recordatorios de acuse',
        'messaging.waive_acknowledgement' => 'Eximir acuses pendientes',
        'messaging.moderate' => 'Moderar mensajería',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('roles')
            || ! Schema::hasTable('permissions')
            || ! Schema::hasTable('system_modules')) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();

            foreach (self::PERMISSIONS as $slug => $name) {
                DB::table('permissions')->insertOrIgnore([
                    'slug' => $slug,
                    'name' => $name,
                    'description' => $name,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('system_modules')->insertOrIgnore([
                'slug' => 'messaging',
                'name' => 'Mensajería',
                'frontend_route' => '/mensajeria',
                'icon' => 'bx-message-rounded-dots',
                'sort_order' => 43,
                'active' => true,
                'parent_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $moduleId = DB::table('system_modules')->where('slug', 'messaging')->value('id');
            $permissionIds = DB::table('permissions')
                ->whereIn('slug', array_keys(self::PERMISSIONS))
                ->pluck('id');

            if ($moduleId
                && Schema::hasTable('permission_groups')
                && Schema::hasTable('permission_group_permission')) {
                DB::table('permission_groups')->insertOrIgnore([
                    'slug' => 'mensajeria',
                    'system_module_id' => $moduleId,
                    'name' => 'Mensajería',
                    'description' => 'Permisos administrativos de mensajería institucional.',
                    'sort_order' => 24,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $groupId = DB::table('permission_groups')->where('slug', 'mensajeria')->value('id');

                if ($groupId) {
                    foreach ($permissionIds as $permissionId) {
                        DB::table('permission_group_permission')->insertOrIgnore([
                            'permission_group_id' => $groupId,
                            'permission_id' => $permissionId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            }

            $roleIds = DB::table('roles')
                ->whereIn('slug', ['super_admin', 'administrador', 'direccion'])
                ->pluck('id');

            foreach ($roleIds as $roleId) {
                if (Schema::hasTable('permission_role')) {
                    foreach ($permissionIds as $permissionId) {
                        DB::table('permission_role')->insertOrIgnore([
                            'role_id' => $roleId,
                            'permission_id' => $permissionId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
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
        });
    }

    public function down(): void
    {
        // Conservador: no se eliminan permisos ni asignaciones existentes.
    }
};
