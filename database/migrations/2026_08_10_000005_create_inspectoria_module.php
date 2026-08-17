<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSIONS = [
        'ver_modulo_inspectoria' => 'Ver módulo de Inspectoría',
        'registrar_atenciones_inspectoria' => 'Registrar atenciones de Inspectoría',
        'asignar_cursos_inspectoria' => 'Asignar cursos a inspectoras',
        'gestionar_pases_inspectoria' => 'Gestionar pases prioritarios de Inspectoría',
        'ver_fichas_inspectoria' => 'Ver fichas de alumnas en Inspectoría',
        'registrar_bitacora_inspectoria' => 'Registrar bitácora diaria de Inspectoría',
    ];

    private const MODULES = [
        ['slug' => 'inspectoria_atenciones', 'name' => 'Atención rápida', 'route' => '/inspectoria/atenciones', 'sort' => 1],
        ['slug' => 'inspectoria_asignaciones', 'name' => 'Cursos e inspectoras', 'route' => '/inspectoria/asignaciones', 'sort' => 2],
        ['slug' => 'inspectoria_pases', 'name' => 'Pases prioritarios', 'route' => '/inspectoria/pases', 'sort' => 3],
        ['slug' => 'inspectoria_alumnas', 'name' => 'Alumnas y fichas', 'route' => '/inspectoria/alumnas', 'sort' => 4],
        ['slug' => 'inspectoria_bitacora', 'name' => 'Bitácora diaria', 'route' => '/inspectoria/bitacora', 'sort' => 5],
    ];

    public function up(): void
    {
        Schema::create('inspectoria_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('sequence_key', 40);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['sequence_key', 'year']);
        });

        Schema::create('inspectoria_course_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('course_section_id')->constrained('course_sections')->cascadeOnDelete();
            $table->foreignId('inspector_staff_id')->constrained('staff')->restrictOnDelete();
            $table->string('physical_location')->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['academic_year_id', 'course_section_id', 'active'], 'insp_assign_year_course_active_idx');
            $table->index(['inspector_staff_id', 'active'], 'insp_assign_staff_active_idx');
        });

        Schema::create('inspectoria_attentions', function (Blueprint $table) {
            $table->id();
            $table->string('attention_code', 80)->unique();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
            $table->foreignId('inspector_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('attended_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('attended_at')->index();
            $table->json('request_types');
            $table->json('actions_taken')->nullable();
            $table->string('priority', 20)->default('normal')->index();
            $table->string('status', 30)->default('registrada')->index();
            $table->string('student_name_snapshot');
            $table->string('course_name_snapshot')->nullable();
            $table->text('brief_note')->nullable();
            $table->boolean('guardian_notified')->default(false);
            $table->boolean('requires_follow_up')->default(false)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['student_profile_id', 'attended_at'], 'insp_attention_student_date_idx');
        });

        Schema::create('inspectoria_passes', function (Blueprint $table) {
            $table->id();
            $table->string('pass_code', 80)->unique();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
            $table->foreignId('inspector_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('student_name_snapshot');
            $table->string('student_rut_snapshot', 30)->nullable();
            $table->string('inspector_name_snapshot')->nullable();
            $table->string('destination', 80)->index();
            $table->string('destination_detail')->nullable();
            $table->dateTime('issued_at')->index();
            $table->dateTime('valid_from')->index();
            $table->dateTime('valid_until')->index();
            $table->string('status', 40)->default('emitido')->index();
            $table->unsignedTinyInteger('priority')->default(100)->index();
            $table->string('regulation_version', 80)->default('vigente');
            $table->text('reason');
            $table->longText('signature_data')->nullable();
            $table->string('signature_name')->nullable();
            $table->string('signature_rut', 30)->nullable();
            $table->dateTime('signed_at')->nullable();
            $table->dateTime('used_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('used_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['student_profile_id', 'status', 'valid_from', 'valid_until'], 'insp_pass_student_status_valid_idx');
        });

        Schema::create('inspectoria_daily_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->nullable()->constrained('student_profiles')->nullOnDelete();
            $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
            $table->foreignId('inspector_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('registered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('happened_at')->index();
            $table->string('category', 50)->index();
            $table->string('priority', 20)->default('media')->index();
            $table->string('status', 30)->default('registrado')->index();
            $table->string('title');
            $table->text('detail');
            $table->boolean('requires_follow_up')->default(false)->index();
            $table->text('follow_up_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['course_section_id', 'happened_at'], 'insp_log_course_date_idx');
        });

        if (Schema::hasTable('biblioteca_pases')) {
            Schema::table('biblioteca_pases', function (Blueprint $table) {
                $table->foreignId('superseded_by_inspectoria_pass_id')
                    ->nullable()
                    ->after('status')
                    ->constrained('inspectoria_passes')
                    ->nullOnDelete();
            });
        }

        $this->registerAccess();
    }

    public function down(): void
    {
        if (Schema::hasTable('biblioteca_pases') && Schema::hasColumn('biblioteca_pases', 'superseded_by_inspectoria_pass_id')) {
            Schema::table('biblioteca_pases', function (Blueprint $table) {
                $table->dropConstrainedForeignId('superseded_by_inspectoria_pass_id');
            });
        }

        Schema::dropIfExists('inspectoria_daily_logs');
        Schema::dropIfExists('inspectoria_passes');
        Schema::dropIfExists('inspectoria_attentions');
        Schema::dropIfExists('inspectoria_course_assignments');
        Schema::dropIfExists('inspectoria_sequences');
    }

    private function registerAccess(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('system_modules')) {
            return;
        }

        $now = now();
        foreach (self::PERMISSIONS as $slug => $name) {
            DB::table('permissions')->insertOrIgnore([
                'slug' => $slug,
                'name' => $name,
                'description' => 'Permiso operativo del módulo de Inspectoría.',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('system_modules')->insertOrIgnore([
            'slug' => 'inspectoria',
            'name' => 'Inspectoría',
            'frontend_route' => null,
            'icon' => 'bx-shield-quarter',
            'sort_order' => 24,
            'active' => true,
            'parent_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $parentId = DB::table('system_modules')->where('slug', 'inspectoria')->value('id');

        foreach (self::MODULES as $module) {
            DB::table('system_modules')->insertOrIgnore([
                'slug' => $module['slug'],
                'name' => $module['name'],
                'frontend_route' => $module['route'],
                'icon' => null,
                'sort_order' => $module['sort'],
                'active' => true,
                'parent_id' => $parentId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $roleId = Schema::hasTable('roles') ? DB::table('roles')->where('slug', 'inspectoria')->value('id') : null;
        if (! $roleId) {
            return;
        }

        if (Schema::hasTable('permission_role')) {
            $inspectorPermissionSlugs = array_values(array_filter(
                array_keys(self::PERMISSIONS),
                fn (string $slug): bool => $slug !== 'asignar_cursos_inspectoria',
            ));
            foreach (DB::table('permissions')->whereIn('slug', $inspectorPermissionSlugs)->pluck('id') as $permissionId) {
                DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (Schema::hasTable('role_system_module')) {
            foreach (DB::table('system_modules')->whereIn('slug', array_merge(['inspectoria'], array_column(self::MODULES, 'slug')))->pluck('id') as $moduleId) {
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
