<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSION = 'ver_estadisticas_inspectoria';

    private const MODULE = 'inspectoria_estadisticas';

    public function up(): void
    {
        Schema::table('inspectoria_daily_logs', function (Blueprint $table) {
            $table->boolean('is_staff_lateness')->default(false)->after('category')->index();
            $table->foreignId('late_staff_id')->nullable()->after('is_staff_lateness')->constrained('staff')->nullOnDelete();
            $table->string('late_staff_name_snapshot')->nullable()->after('late_staff_id');
            $table->index(['is_staff_lateness', 'late_staff_id', 'happened_at'], 'insp_log_staff_lateness_idx');
        });

        Schema::create('inspectoria_daily_log_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_log_id')->constrained('inspectoria_daily_logs')->cascadeOnDelete();
            $table->foreignId('course_section_id')->constrained('course_sections')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['daily_log_id', 'course_section_id'], 'insp_log_course_unique');
            $table->index(['course_section_id', 'daily_log_id'], 'insp_course_log_idx');
        });

        $this->registerAccess();
    }

    public function down(): void
    {
        Schema::dropIfExists('inspectoria_daily_log_courses');

        Schema::table('inspectoria_daily_logs', function (Blueprint $table) {
            $table->dropIndex('insp_log_staff_lateness_idx');
            $table->dropConstrainedForeignId('late_staff_id');
            $table->dropColumn(['is_staff_lateness', 'late_staff_name_snapshot']);
        });
    }

    private function registerAccess(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('system_modules')) {
            return;
        }

        $now = now();
        DB::table('permissions')->insertOrIgnore([
            'slug' => self::PERMISSION,
            'name' => 'Ver estadísticas de Inspectoría',
            'description' => 'Permite consultar y exportar estadísticas de atrasos de funcionarios por curso.',
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $parentId = DB::table('system_modules')->where('slug', 'inspectoria')->value('id');
        DB::table('system_modules')->insertOrIgnore([
            'slug' => self::MODULE,
            'name' => 'Estadísticas',
            'frontend_route' => '/inspectoria/estadisticas',
            'icon' => 'bx-bar-chart-alt-2',
            'sort_order' => 8,
            'active' => true,
            'parent_id' => $parentId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $permissionId = DB::table('permissions')->where('slug', self::PERMISSION)->value('id');
        $moduleId = DB::table('system_modules')->where('slug', self::MODULE)->value('id');
        $roleIds = Schema::hasTable('roles')
            ? DB::table('roles')->whereIn('slug', ['inspectoria', 'coordinador_inspectoria', 'super_admin'])->pluck('id')
            : collect();

        foreach ($roleIds as $roleId) {
            if ($permissionId && Schema::hasTable('permission_role')) {
                DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if ($moduleId && Schema::hasTable('role_system_module')) {
                DB::table('role_system_module')->insertOrIgnore([
                    'role_id' => $roleId,
                    'system_module_id' => $moduleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
};
