<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSIONS = [
        'social_work.referrals.submit' => [
            'name' => 'Enviar derivaciones a Trabajo Social',
            'description' => 'Permite registrar derivaciones y consultar únicamente las creadas por la persona usuaria.',
        ],
        'social_work.student_profile.update' => [
            'name' => 'Actualizar situación social de estudiantes',
            'description' => 'Permite actualizar PIE, clasificación SEP y participación en programas sociales.',
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();
            foreach (self::PERMISSIONS as $slug => $definition) {
                DB::table('permissions')->updateOrInsert(
                    ['slug' => $slug],
                    [...$definition, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
                );
            }

            $submitPermissionId = DB::table('permissions')->where('slug', 'social_work.referrals.submit')->value('id');
            $updatePermissionId = DB::table('permissions')->where('slug', 'social_work.student_profile.update')->value('id');
            $socialRoleIds = DB::table('roles')->whereIn('slug', ['trabajador_social', 'super_admin'])->pluck('id');
            $referringRoleIds = DB::table('roles')->whereIn('slug', ['inspectoria', 'coordinador_inspectoria'])->pluck('id');

            if (Schema::hasTable('permission_role')) {
                foreach ($socialRoleIds as $roleId) {
                    foreach ([$submitPermissionId, $updatePermissionId] as $permissionId) {
                        if ($permissionId) {
                            DB::table('permission_role')->insertOrIgnore([
                                'role_id' => $roleId, 'permission_id' => $permissionId,
                                'created_at' => $now, 'updated_at' => $now,
                            ]);
                        }
                    }
                }
                foreach ($referringRoleIds as $roleId) {
                    if ($submitPermissionId) {
                        DB::table('permission_role')->insertOrIgnore([
                            'role_id' => $roleId, 'permission_id' => $submitPermissionId,
                            'created_at' => $now, 'updated_at' => $now,
                        ]);
                    }
                }
            }

            if (Schema::hasTable('system_modules') && Schema::hasTable('role_system_module')) {
                $moduleIds = DB::table('system_modules')
                    ->whereIn('slug', ['social_work', 'social_work_referrals'])
                    ->pluck('id');
                foreach ($referringRoleIds as $roleId) {
                    foreach ($moduleIds as $moduleId) {
                        DB::table('role_system_module')->insertOrIgnore([
                            'role_id' => $roleId, 'system_module_id' => $moduleId,
                            'created_at' => $now, 'updated_at' => $now,
                        ]);
                    }
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        DB::transaction(function (): void {
            $permissionIds = DB::table('permissions')->whereIn('slug', array_keys(self::PERMISSIONS))->pluck('id');
            if (Schema::hasTable('permission_role')) {
                DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
            }
            if (Schema::hasTable('permission_group_permission')) {
                DB::table('permission_group_permission')->whereIn('permission_id', $permissionIds)->delete();
            }
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        });
    }
};
