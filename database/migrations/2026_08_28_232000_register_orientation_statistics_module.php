<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('system_modules')) {
            return;
        }

        DB::transaction(function (): void {
            $parentId = DB::table('system_modules')->where('slug', 'orientation')->value('id');
            if (! $parentId) {
                return;
            }

            $now = now();
            DB::table('system_modules')->insertOrIgnore([
                'slug' => 'orientation_statistics',
                'name' => 'Estadísticas',
                'frontend_route' => '/orientation/estadisticas',
                'icon' => null,
                'sort_order' => 3,
                'active' => true,
                'parent_id' => $parentId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if (! Schema::hasTable('role_system_module')) {
                return;
            }

            $moduleId = DB::table('system_modules')->where('slug', 'orientation_statistics')->value('id');
            $roleIds = DB::table('roles')
                ->whereIn('slug', ['orientacion', 'super_admin', 'superadmin'])
                ->pluck('id');

            foreach ($roleIds as $roleId) {
                DB::table('role_system_module')->insertOrIgnore([
                    'role_id' => $roleId,
                    'system_module_id' => $moduleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        // Migración aditiva: el acceso se conserva para evitar retirar navegación en producción.
    }
};
