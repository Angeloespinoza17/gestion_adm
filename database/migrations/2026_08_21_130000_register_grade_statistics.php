<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSION = 'grade_statistics.view';

    private const MODULE = 'students_grade_statistics';

    public function up(): void
    {
        $this->addReportingIndexes();

        if (! Schema::hasTable('permissions') || ! Schema::hasTable('system_modules')) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();
            $parentId = DB::table('system_modules')->where('slug', 'students')->value('id');

            DB::table('permissions')->insertOrIgnore([
                'slug' => self::PERMISSION,
                'name' => 'Ver estadísticas de calificaciones',
                'description' => 'Permite consultar indicadores agregados de calificaciones, cobertura y pendientes sin datos nominales.',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('permissions')->where('slug', self::PERMISSION)->update([
                'name' => 'Ver estadísticas de calificaciones',
                'description' => 'Permite consultar indicadores agregados de calificaciones, cobertura y pendientes sin datos nominales.',
                'active' => true,
                'updated_at' => $now,
            ]);

            DB::table('system_modules')->insertOrIgnore([
                'slug' => self::MODULE,
                'name' => 'Estadísticas de calificaciones',
                'frontend_route' => '/students/grade-statistics',
                'icon' => 'bx-line-chart',
                'sort_order' => 10,
                'active' => true,
                'parent_id' => $parentId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('system_modules')->where('slug', self::MODULE)->update([
                'name' => 'Estadísticas de calificaciones',
                'frontend_route' => '/students/grade-statistics',
                'icon' => 'bx-line-chart',
                'sort_order' => 10,
                'active' => true,
                'parent_id' => $parentId,
                'updated_at' => $now,
            ]);

            $permissionId = DB::table('permissions')->where('slug', self::PERMISSION)->value('id');
            $moduleId = DB::table('system_modules')->where('slug', self::MODULE)->value('id');
            $roles = DB::table('roles')->whereIn('slug', ['super_admin', 'administrador', 'direccion', 'coordinador_academico'])->get(['id', 'slug']);

            if ($permissionId && Schema::hasTable('permission_role')) {
                foreach ($roles as $role) {
                    DB::table('permission_role')->insertOrIgnore([
                        'role_id' => $role->id,
                        'permission_id' => $permissionId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            if ($moduleId && Schema::hasTable('role_system_module')) {
                foreach ($roles as $role) {
                    DB::table('role_system_module')->insertOrIgnore([
                        'role_id' => $role->id,
                        'system_module_id' => $moduleId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    if ($parentId && in_array($role->slug, ['direccion', 'coordinador_academico'], true)) {
                        DB::table('role_system_module')->insertOrIgnore([
                            'role_id' => $role->id,
                            'system_module_id' => $parentId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            }

            if ($permissionId && Schema::hasTable('permission_groups') && Schema::hasTable('permission_group_permission')) {
                $groupId = DB::table('permission_groups')->where('slug', 'estudiantes')->value('id');
                if ($groupId) {
                    DB::table('permission_group_permission')->insertOrIgnore([
                        'permission_group_id' => $groupId,
                        'permission_id' => $permissionId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        });
    }

    private function addReportingIndexes(): void
    {
        if (Schema::hasTable('annual_grade_import_columns')) {
            Schema::table('annual_grade_import_columns', function (Blueprint $table): void {
                $table->index('assessment_id', 'annual_grade_import_column_assessment_idx');
            });
        }

        if (Schema::hasTable('annual_grade_import_rows')) {
            Schema::table('annual_grade_import_rows', function (Blueprint $table): void {
                $table->index(['annual_grade_import_id', 'student_profile_id'], 'annual_grade_import_row_student_idx');
            });
        }
    }

    public function down(): void
    {
        // Migración forward-only: no elimina índices, accesos ni configuración de producción.
    }
};
