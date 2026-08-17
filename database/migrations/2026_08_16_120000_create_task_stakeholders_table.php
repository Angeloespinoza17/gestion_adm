<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_stakeholders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['task_id', 'user_id']);
            $table->index(['user_id', 'task_id']);
        });

        $this->registerReportsPermissionAndNavigation();
    }

    public function down(): void
    {
        Schema::dropIfExists('task_stakeholders');
    }

    private function registerReportsPermissionAndNavigation(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('system_modules')) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();
            DB::table('permissions')->insertOrIgnore([
                'slug' => 'ver_reportes_tareas',
                'name' => 'Ver Reportes Globales de Tareas',
                'description' => 'Permite consultar el reporte institucional de tareas sin ampliar la visibilidad del backlog personal.',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('permissions')->where('slug', 'ver_reportes_tareas')->update([
                'name' => 'Ver Reportes Globales de Tareas',
                'description' => 'Permite consultar el reporte institucional de tareas sin ampliar la visibilidad del backlog personal.',
                'active' => true,
                'updated_at' => $now,
            ]);

            DB::table('permissions')->where('slug', 'ver_tareas_equipo')->update([
                'active' => false,
                'description' => 'Permiso retirado: el acceso a tareas de terceros ahora requiere ser stakeholder explícito.',
                'updated_at' => $now,
            ]);

            $tasksModuleId = DB::table('system_modules')->where('slug', 'tasks')->value('id');
            if (!$tasksModuleId) {
                return;
            }

            DB::table('system_modules')->insertOrIgnore([
                'slug' => 'tasks_reports',
                'name' => 'Reportes de tareas',
                'frontend_route' => '/tasks/reports',
                'icon' => null,
                'sort_order' => 3,
                'active' => true,
                'parent_id' => $tasksModuleId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('system_modules')->where('slug', 'tasks_reports')->update([
                'name' => 'Reportes de tareas',
                'frontend_route' => '/tasks/reports',
                'sort_order' => 3,
                'active' => true,
                'parent_id' => $tasksModuleId,
                'updated_at' => $now,
            ]);

            $permissionId = DB::table('permissions')->where('slug', 'ver_reportes_tareas')->value('id');
            $reportModuleId = DB::table('system_modules')->where('slug', 'tasks_reports')->value('id');

            if ($permissionId && Schema::hasTable('permission_groups') && Schema::hasTable('permission_group_permission')) {
                $groupId = DB::table('permission_groups')->where('slug', 'tareas')->value('id');
                if ($groupId) {
                    DB::table('permission_group_permission')->insertOrIgnore([
                        'permission_group_id' => $groupId,
                        'permission_id' => $permissionId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            if (!$reportModuleId || !Schema::hasTable('roles') || !Schema::hasTable('role_system_module')) {
                return;
            }

            foreach (DB::table('roles')->whereIn('slug', ['super_admin', 'superadmin'])->pluck('id') as $roleId) {
                DB::table('role_system_module')->insertOrIgnore([
                    'role_id' => $roleId,
                    'system_module_id' => $reportModuleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }
};
