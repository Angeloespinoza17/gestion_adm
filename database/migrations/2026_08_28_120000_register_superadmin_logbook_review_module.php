<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_modules')) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();

            DB::table('system_modules')->insertOrIgnore([
                'slug' => 'superadmin',
                'name' => 'Superadmin',
                'frontend_route' => null,
                'icon' => 'bx-lock-alt',
                'sort_order' => 118,
                'active' => true,
                'parent_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $parentId = DB::table('system_modules')->where('slug', 'superadmin')->value('id');
            if (! $parentId) {
                return;
            }

            DB::table('system_modules')->insertOrIgnore([
                'slug' => 'superadmin_logbook_review',
                'name' => 'Revisión de bitácoras',
                'frontend_route' => '/superadmin/bitacoras',
                'icon' => null,
                'sort_order' => 1,
                'active' => true,
                'parent_id' => $parentId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if (! Schema::hasTable('roles') || ! Schema::hasTable('role_system_module')) {
                return;
            }

            $roleId = DB::table('roles')->where('slug', 'super_admin')->value('id');
            $moduleIds = DB::table('system_modules')
                ->whereIn('slug', ['superadmin', 'superadmin_logbook_review'])
                ->pluck('id');

            foreach ($moduleIds as $moduleId) {
                if ($roleId) {
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
        // Módulo aditivo: no se eliminan asignaciones ni registros RBAC al revertir.
    }
};
