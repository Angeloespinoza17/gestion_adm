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

        $settingsId = DB::table('system_modules')->where('slug', 'settings')->value('id');

        DB::table('system_modules')->insertOrIgnore([
            'slug' => 'settings_deploy',
            'name' => 'Deploy',
            'frontend_route' => '/deploy',
            'icon' => null,
            'sort_order' => 7,
            'active' => true,
            'parent_id' => $settingsId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('system_modules')
            ->where('slug', 'settings_deploy')
            ->update(
            [
                'name' => 'Deploy',
                'frontend_route' => '/deploy',
                'icon' => null,
                'sort_order' => 7,
                'active' => true,
                'parent_id' => $settingsId,
                'updated_at' => now(),
            ],
            );

        if (! Schema::hasTable('roles') || ! Schema::hasTable('role_system_module')) {
            return;
        }

        $roleId = DB::table('roles')->where('slug', 'super_admin')->value('id');
        $moduleId = DB::table('system_modules')->where('slug', 'settings_deploy')->value('id');

        if ($roleId && $moduleId) {
            DB::table('role_system_module')->insertOrIgnore([
                'role_id' => $roleId,
                'system_module_id' => $moduleId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // El módulo es aditivo. No se elimina información RBAC durante rollbacks.
    }
};
