<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('roles')
            || ! Schema::hasTable('permissions')
            || ! Schema::hasTable('system_modules')
        ) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();

            $this->attachAccess(
                ['encargado_web'],
                ['ver_metricas_sitio'],
                ['public_site', 'public_site_analytics'],
                $now,
            );

            $this->attachAccess(
                ['super_admin'],
                ['operational_logbook.view', 'operational_logbook.create'],
                ['operational_staff_logbook'],
                $now,
            );
        });
    }

    public function down(): void
    {
        // Migración aditiva: se preservan permisos, módulos y asignaciones.
    }

    /**
     * @param  array<int, string>  $roleSlugs
     * @param  array<int, string>  $permissionSlugs
     * @param  array<int, string>  $moduleSlugs
     */
    private function attachAccess(
        array $roleSlugs,
        array $permissionSlugs,
        array $moduleSlugs,
        mixed $now,
    ): void {
        $roleIds = DB::table('roles')
            ->whereIn('slug', $roleSlugs)
            ->where('active', true)
            ->pluck('id');

        if ($roleIds->isEmpty()) {
            return;
        }

        if (Schema::hasTable('permission_role')) {
            $permissionIds = DB::table('permissions')
                ->whereIn('slug', $permissionSlugs)
                ->where('active', true)
                ->pluck('id');

            $rows = [];
            foreach ($roleIds as $roleId) {
                foreach ($permissionIds as $permissionId) {
                    $rows[] = [
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if ($rows !== []) {
                DB::table('permission_role')->insertOrIgnore($rows);
            }
        }

        if (Schema::hasTable('role_system_module')) {
            $moduleIds = DB::table('system_modules')
                ->whereIn('slug', $moduleSlugs)
                ->where('active', true)
                ->pluck('id');

            $rows = [];
            foreach ($roleIds as $roleId) {
                foreach ($moduleIds as $moduleId) {
                    $rows[] = [
                        'role_id' => $roleId,
                        'system_module_id' => $moduleId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if ($rows !== []) {
                DB::table('role_system_module')->insertOrIgnore($rows);
            }
        }
    }
};
