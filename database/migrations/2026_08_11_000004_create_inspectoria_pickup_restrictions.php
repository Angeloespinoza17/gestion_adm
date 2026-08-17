<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSION = 'gestionar_restricciones_inspectoria';

    public function up(): void
    {
        Schema::create('inspectoria_pickup_restrictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
            $table->string('restricted_person_name');
            $table->string('restricted_person_rut', 30)->nullable();
            $table->string('restricted_person_relationship', 100)->nullable();
            $table->string('restriction_type', 50)->index();
            $table->text('reason');
            $table->string('legal_reference', 500)->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['student_profile_id', 'active', 'starts_on'], 'inspectoria_restrictions_student_active_idx');
            $table->index(['course_section_id', 'active'], 'inspectoria_restrictions_course_active_idx');
        });

        if (! Schema::hasTable('permissions') || ! Schema::hasTable('system_modules')) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();
            DB::table('permissions')->insertOrIgnore([
                'slug' => self::PERMISSION,
                'name' => 'Gestionar restricciones de retiro en Inspectoría',
                'description' => 'Permite crear, actualizar y finalizar restricciones de retiro para alumnas de cursos asignados.',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('permissions')->where('slug', self::PERMISSION)->update(['active' => true, 'updated_at' => $now]);

            $parentId = DB::table('system_modules')->where('slug', 'inspectoria')->value('id');
            DB::table('system_modules')->insertOrIgnore([
                'slug' => 'inspectoria_restricciones',
                'name' => 'Restricciones de retiro',
                'frontend_route' => '/inspectoria/restricciones',
                'icon' => null,
                'sort_order' => 5,
                'parent_id' => $parentId,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('system_modules')->where('slug', 'inspectoria_restricciones')->update([
                'name' => 'Restricciones de retiro',
                'frontend_route' => '/inspectoria/restricciones',
                'sort_order' => 5,
                'parent_id' => $parentId,
                'active' => true,
                'updated_at' => $now,
            ]);
            DB::table('system_modules')->where('slug', 'inspectoria_retiros')->update(['sort_order' => 6, 'updated_at' => $now]);
            DB::table('system_modules')->where('slug', 'inspectoria_bitacora')->update(['sort_order' => 7, 'updated_at' => $now]);

            $permissionId = DB::table('permissions')->where('slug', self::PERMISSION)->value('id');
            $moduleId = DB::table('system_modules')->where('slug', 'inspectoria_restricciones')->value('id');
            $roleIds = DB::table('roles')->whereIn('slug', ['inspectoria', 'coordinador_inspectoria'])->pluck('id');

            foreach ($roleIds as $roleId) {
                if (Schema::hasTable('permission_role')) {
                    DB::table('permission_role')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId, 'created_at' => $now, 'updated_at' => $now]);
                }
                if (Schema::hasTable('role_system_module')) {
                    DB::table('role_system_module')->insertOrIgnore(['role_id' => $roleId, 'system_module_id' => $moduleId, 'created_at' => $now, 'updated_at' => $now]);
                }
            }

            if (Schema::hasTable('permission_groups') && Schema::hasTable('permission_group_permission')) {
                $groupId = DB::table('permission_groups')->where('slug', 'inspectoria')->value('id');
                if ($groupId) {
                    DB::table('permission_group_permission')->insertOrIgnore(['permission_group_id' => $groupId, 'permission_id' => $permissionId, 'created_at' => $now, 'updated_at' => $now]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspectoria_pickup_restrictions');
    }
};
