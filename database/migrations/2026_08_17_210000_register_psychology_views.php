<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const VIEWS = [
        ['slug' => 'psychology_dashboard', 'name' => 'Resumen', 'route' => '/psychology', 'sort' => 1, 'roles' => ['inspectoria', 'psicologo', 'coordinador_psicologia', 'convivencia_escolar', 'direccion', 'super_admin']],
        ['slug' => 'psychology_referrals', 'name' => 'Derivaciones', 'route' => '/psychology/referrals', 'sort' => 2, 'roles' => ['inspectoria', 'psicologo', 'coordinador_psicologia']],
        ['slug' => 'psychology_cases', 'name' => 'Casos', 'route' => '/psychology/cases', 'sort' => 3, 'roles' => ['psicologo', 'coordinador_psicologia', 'convivencia_escolar']],
        ['slug' => 'psychology_calendar', 'name' => 'Agenda', 'route' => '/psychology/calendar', 'sort' => 4, 'roles' => ['psicologo', 'coordinador_psicologia', 'convivencia_escolar']],
        ['slug' => 'psychology_tasks', 'name' => 'Tareas', 'route' => '/psychology/tasks', 'sort' => 5, 'roles' => ['psicologo', 'coordinador_psicologia', 'convivencia_escolar']],
        ['slug' => 'psychology_alerts', 'name' => 'Alertas', 'route' => '/psychology/alerts', 'sort' => 6, 'roles' => ['psicologo', 'coordinador_psicologia', 'convivencia_escolar']],
        ['slug' => 'psychology_reports', 'name' => 'Reportes', 'route' => '/psychology/reports', 'sort' => 7, 'roles' => ['psicologo', 'coordinador_psicologia', 'convivencia_escolar', 'direccion', 'super_admin']],
        ['slug' => 'psychology_configuration', 'name' => 'Configuración', 'route' => '/psychology/configuration', 'sort' => 8, 'roles' => ['coordinador_psicologia', 'super_admin']],
        ['slug' => 'psychology_audit', 'name' => 'Auditoría', 'route' => '/psychology/audit', 'sort' => 9, 'roles' => ['coordinador_psicologia', 'super_admin']],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('system_modules') || ! Schema::hasTable('roles') || ! Schema::hasTable('role_system_module')) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();
            $parentId = DB::table('system_modules')->where('slug', 'psychology')->value('id');

            if (! $parentId) {
                $parentId = DB::table('system_modules')->insertGetId([
                    'slug' => 'psychology',
                    'name' => 'Psicología Escolar',
                    'frontend_route' => null,
                    'icon' => 'bx-brain',
                    'sort_order' => 29,
                    'active' => true,
                    'parent_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('system_modules')->where('id', $parentId)->update([
                    'name' => 'Psicología Escolar',
                    'frontend_route' => null,
                    'icon' => 'bx-brain',
                    'sort_order' => 29,
                    'active' => true,
                    'parent_id' => null,
                    'updated_at' => $now,
                ]);
            }

            foreach (self::VIEWS as $view) {
                DB::table('system_modules')->updateOrInsert(
                    ['slug' => $view['slug']],
                    [
                        'name' => $view['name'],
                        'frontend_route' => $view['route'],
                        'icon' => null,
                        'sort_order' => $view['sort'],
                        'active' => true,
                        'parent_id' => $parentId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );

                $moduleId = DB::table('system_modules')->where('slug', $view['slug'])->value('id');
                foreach (DB::table('roles')->whereIn('slug', $view['roles'])->pluck('id') as $roleId) {
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
        if (! Schema::hasTable('system_modules')) {
            return;
        }

        $viewIds = DB::table('system_modules')->whereIn('slug', array_column(self::VIEWS, 'slug'))->pluck('id');
        if (Schema::hasTable('role_system_module')) {
            DB::table('role_system_module')->whereIn('system_module_id', $viewIds)->delete();
        }
        DB::table('system_modules')->whereIn('id', $viewIds)->delete();
        DB::table('system_modules')->where('slug', 'psychology')->update([
            'frontend_route' => '/psychology',
            'updated_at' => now(),
        ]);
    }
};
