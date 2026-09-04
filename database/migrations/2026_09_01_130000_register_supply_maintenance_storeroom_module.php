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
                'slug' => 'supplies',
                'name' => 'Abastecimiento',
                'frontend_route' => null,
                'icon' => 'bx-package',
                'sort_order' => 95,
                'active' => true,
                'parent_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $parentId = DB::table('system_modules')->where('slug', 'supplies')->value('id');
            if (! $parentId) {
                return;
            }

            DB::table('system_modules')->insertOrIgnore([
                'slug' => 'supplies_maintenance_storeroom',
                'name' => 'Pañol de mantenimiento',
                'frontend_route' => '/supplies/maintenance-storeroom',
                'icon' => null,
                'sort_order' => 4,
                'active' => true,
                'parent_id' => $parentId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if (! Schema::hasTable('roles') || ! Schema::hasTable('role_system_module')) {
                return;
            }

            $moduleIds = DB::table('system_modules')
                ->whereIn('slug', ['supplies', 'supplies_maintenance_storeroom'])
                ->pluck('id');
            $roleIds = DB::table('roles')
                ->whereIn('slug', ['super_admin', 'administrador', 'encargado_mantencion'])
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
        });
    }

    public function down(): void
    {
        // Registro aditivo: no se eliminan accesos ni trazabilidad al revertir.
    }
};
