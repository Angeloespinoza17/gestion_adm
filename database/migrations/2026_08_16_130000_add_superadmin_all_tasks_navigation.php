<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('system_modules')) {
            return;
        }

        $tasksModuleId = DB::table('system_modules')->where('slug', 'tasks')->value('id');
        if (!$tasksModuleId) {
            return;
        }

        $now = now();
        DB::table('system_modules')->insertOrIgnore([
            'slug' => 'tasks_all',
            'name' => 'Todas las tareas',
            'frontend_route' => '/tasks/all',
            'icon' => null,
            'sort_order' => 3,
            'active' => true,
            'parent_id' => $tasksModuleId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('system_modules')->where('slug', 'tasks_all')->update([
            'name' => 'Todas las tareas',
            'frontend_route' => '/tasks/all',
            'sort_order' => 3,
            'active' => true,
            'parent_id' => $tasksModuleId,
            'updated_at' => $now,
        ]);
        DB::table('system_modules')->where('slug', 'tasks_reports')->update([
            'sort_order' => 4,
            'updated_at' => $now,
        ]);

        if (!Schema::hasTable('roles') || !Schema::hasTable('role_system_module')) {
            return;
        }

        $moduleId = DB::table('system_modules')->where('slug', 'tasks_all')->value('id');
        foreach (DB::table('roles')->whereIn('slug', ['super_admin', 'superadmin'])->pluck('id') as $roleId) {
            DB::table('role_system_module')->insertOrIgnore([
                'role_id' => $roleId,
                'system_module_id' => $moduleId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Catálogo aditivo: se conserva para no alterar asignaciones activas.
    }
};
