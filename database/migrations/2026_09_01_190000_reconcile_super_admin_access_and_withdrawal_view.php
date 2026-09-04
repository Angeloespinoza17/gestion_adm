<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $now = now();

        DB::table('permissions')->insertOrIgnore([
            'slug' => 'libro_digital.withdrawals.view',
            'name' => 'Consultar salidas y retiros',
            'description' => 'Permiso del Libro Digital de Clases.',
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $permissionId = DB::table('permissions')
            ->where('slug', 'libro_digital.withdrawals.view')
            ->where('active', true)
            ->value('id');
        $groupId = Schema::hasTable('permission_groups')
            ? DB::table('permission_groups')->where('slug', 'libro_digital')->where('active', true)->value('id')
            : null;

        if ($permissionId && $groupId && Schema::hasTable('permission_group_permission')) {
            DB::table('permission_group_permission')->insertOrIgnore([
                'permission_group_id' => $groupId,
                'permission_id' => $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $superAdminId = DB::table('roles')->where('slug', 'super_admin')->value('id');

        if (! $superAdminId) {
            return;
        }

        if (Schema::hasTable('permission_role')) {
            DB::table('permissions')
                ->where('active', true)
                ->orderBy('id')
                ->pluck('id')
                ->each(fn ($activePermissionId) => DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $superAdminId,
                    'permission_id' => $activePermissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
        }

        if (Schema::hasTable('system_modules') && Schema::hasTable('role_system_module')) {
            DB::table('system_modules')
                ->where('active', true)
                ->orderBy('id')
                ->pluck('id')
                ->each(fn ($activeModuleId) => DB::table('role_system_module')->insertOrIgnore([
                    'role_id' => $superAdminId,
                    'system_module_id' => $activeModuleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
        }
    }

    public function down(): void
    {
        // No-op deliberado: el rollback no elimina permisos ni accesos existentes.
    }
};
