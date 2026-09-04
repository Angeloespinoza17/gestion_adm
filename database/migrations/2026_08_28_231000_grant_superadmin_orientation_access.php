<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSIONS = [
        'orientation.view',
        'orientation.manage_plan',
        'orientation.manage_execution',
        'orientation.manage_evidence',
    ];

    private const MODULES = [
        'orientation',
        'orientation_annual_plan',
        'orientation_calendar',
        'orientation_statistics',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions') || ! Schema::hasTable('system_modules')) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();
            $roleIds = DB::table('roles')
                ->whereIn('slug', ['super_admin', 'superadmin'])
                ->pluck('id');

            if ($roleIds->isEmpty()) {
                return;
            }

            if (Schema::hasTable('permission_role')) {
                $permissionIds = DB::table('permissions')
                    ->whereIn('slug', self::PERMISSIONS)
                    ->pluck('id');

                foreach ($roleIds as $roleId) {
                    foreach ($permissionIds as $permissionId) {
                        DB::table('permission_role')->insertOrIgnore([
                            'role_id' => $roleId,
                            'permission_id' => $permissionId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            }

            if (Schema::hasTable('role_system_module')) {
                $moduleIds = DB::table('system_modules')
                    ->whereIn('slug', self::MODULES)
                    ->pluck('id');

                foreach ($roleIds as $roleId) {
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
        });
    }

    public function down(): void
    {
        // Se conservan los accesos para no retirar navegación ni permisos en producción.
    }
};
