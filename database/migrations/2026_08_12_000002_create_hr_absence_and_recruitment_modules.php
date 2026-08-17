<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSIONS = [
        'rrhh.ausencias.ver' => 'Ver gestión de ausencias y saldos',
        'rrhh.ausencias.gestionar' => 'Gestionar ausencias y saldos',
        'rrhh.ausencias.importar' => 'Importar ausencias históricas',
        'rrhh.ausencias.exportar' => 'Exportar reportes de ausencias',
        'rrhh.seleccion.ver' => 'Ver selección y banco de talento',
        'rrhh.seleccion.gestionar' => 'Gestionar selección y banco de talento',
        'rrhh.seleccion.importar' => 'Importar antecedentes de selección',
        'rrhh.psicolaborales.confidencial' => 'Acceder a informes psicolaborales confidenciales',
    ];

    public function up(): void
    {
        Schema::create('hr_absence_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->unsignedSmallInteger('year')->index();
            $table->decimal('administrative_entitlement', 8, 2)->default(0);
            $table->decimal('administrative_adjustment', 8, 2)->default(0);
            $table->decimal('compensatory_entitlement', 8, 2)->default(0);
            $table->decimal('compensatory_adjustment', 8, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['staff_id', 'year'], 'hr_abs_balance_staff_year_uq');
        });

        Schema::create('hr_absence_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('permission_request_id')->nullable()->constrained('permission_requests')->nullOnDelete();
            $table->foreignId('medical_leave_id')->nullable()->constrained('hr_medical_leaves')->nullOnDelete();
            $table->string('absence_type', 60)->index();
            $table->date('starts_on')->index();
            $table->date('ends_on')->index();
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->decimal('quantity', 8, 2)->default(1);
            $table->string('unit', 20)->default('dias');
            $table->string('rest_type', 30)->nullable();
            $table->string('status', 50)->default('registrada')->index();
            $table->string('source', 50)->default('manual')->index();
            $table->boolean('affects_attendance')->default(true);
            $table->boolean('affects_payroll')->default(false);
            $table->string('external_key', 64)->nullable()->unique();
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['staff_id', 'starts_on', 'absence_type'], 'hr_abs_staff_date_type_idx');
        });

        Schema::create('hr_absence_balance_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('balance_id')->constrained('hr_absence_balances')->cascadeOnDelete();
            $table->foreignId('absence_record_id')->nullable()->constrained('hr_absence_records')->nullOnDelete();
            $table->string('bucket', 30)->index();
            $table->string('movement_type', 30)->default('ajuste');
            $table->decimal('quantity', 8, 2);
            $table->date('effective_on')->index();
            $table->string('description')->nullable();
            $table->string('external_key', 64)->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('hr_recruitment_vacancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_profile_id')->nullable()->constrained('hr_job_profiles')->nullOnDelete();
            $table->foreignId('cargo_id')->nullable()->constrained('cargos')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title')->index();
            $table->string('area', 140)->nullable()->index();
            $table->unsignedSmallInteger('vacancy_count')->default(1);
            $table->string('employment_type', 80)->nullable();
            $table->decimal('weekly_hours', 6, 2)->nullable();
            $table->string('reason', 160)->nullable();
            $table->date('opened_on')->nullable()->index();
            $table->date('target_start_on')->nullable();
            $table->date('closes_on')->nullable();
            $table->string('status', 40)->default('borrador')->index();
            $table->text('description')->nullable();
            $table->text('requirements')->nullable();
            $table->string('external_key', 64)->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('hr_recruitment_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vacancy_id')->nullable()->constrained('hr_recruitment_vacancies')->nullOnDelete();
            $table->foreignId('cv_bank_entry_id')->constrained('hr_cv_bank_entries')->cascadeOnDelete();
            $table->foreignId('hired_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('stage', 50)->default('cv_recibido')->index();
            $table->string('source', 120)->nullable();
            $table->date('applied_on')->nullable()->index();
            $table->decimal('score', 5, 2)->nullable();
            $table->string('reconsideration', 30)->nullable();
            $table->text('reconsideration_notes')->nullable();
            $table->string('outcome', 80)->nullable();
            $table->text('notes')->nullable();
            $table->string('external_key', 64)->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['vacancy_id', 'cv_bank_entry_id'], 'hr_recruit_app_vacancy_candidate_uq');
        });

        Schema::create('hr_psycholabor_interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('hr_recruitment_applications')->cascadeOnDelete();
            $table->foreignId('interviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('scheduled_at')->nullable()->index();
            $table->dateTime('completed_at')->nullable();
            $table->string('interviewer_name')->nullable();
            $table->string('result', 60)->nullable()->index();
            $table->boolean('induction_required')->default(false);
            $table->string('reconsideration', 30)->nullable();
            $table->text('considerations')->nullable();
            $table->text('confidential_notes')->nullable();
            $table->string('report_path')->nullable();
            $table->string('report_file_name')->nullable();
            $table->string('report_mime_type', 100)->nullable();
            $table->unsignedBigInteger('report_file_size')->nullable();
            $table->string('status', 40)->default('programada')->index();
            $table->string('source', 120)->nullable();
            $table->string('external_key', 64)->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('hr_cv_bank_entries', function (Blueprint $table) {
            $table->string('normalized_name')->nullable()->index()->after('full_name');
            $table->string('cv_file_name')->nullable()->after('cv_path');
            $table->string('cv_mime_type', 100)->nullable()->after('cv_file_name');
            $table->unsignedBigInteger('cv_file_size')->nullable()->after('cv_mime_type');
        });

        $this->registerAccess();
    }

    public function down(): void
    {
        Schema::table('hr_cv_bank_entries', function (Blueprint $table) {
            $table->dropColumn(['normalized_name', 'cv_file_name', 'cv_mime_type', 'cv_file_size']);
        });
        Schema::dropIfExists('hr_psycholabor_interviews');
        Schema::dropIfExists('hr_recruitment_applications');
        Schema::dropIfExists('hr_recruitment_vacancies');
        Schema::dropIfExists('hr_absence_balance_movements');
        Schema::dropIfExists('hr_absence_records');
        Schema::dropIfExists('hr_absence_balances');
    }

    private function registerAccess(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('system_modules')) {
            return;
        }

        $now = now();
        foreach (self::PERMISSIONS as $slug => $name) {
            DB::table('permissions')->updateOrInsert(['slug' => $slug], [
                'name' => $name,
                'description' => 'Permiso de herramientas de personas dentro del módulo de Gestión Operativa.',
                'active' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ]);
        }

        DB::table('system_modules')->updateOrInsert(['slug' => 'operational_management'], [
            'name' => 'Gestión Operativa',
            'frontend_route' => null,
            'icon' => 'bx-briefcase-alt-2',
            'sort_order' => 44,
            'active' => true,
            'parent_id' => null,
            'updated_at' => $now,
            'created_at' => $now,
        ]);
        $parentId = DB::table('system_modules')->where('slug', 'operational_management')->value('id');
        foreach ([
            ['slug' => 'hr_absence_management', 'name' => 'Ausencias y saldos', 'route' => '/human-resources/absences', 'sort' => 5],
            ['slug' => 'hr_recruitment_management', 'name' => 'Selección y banco de talento', 'route' => '/human-resources/recruitment', 'sort' => 6],
        ] as $module) {
            DB::table('system_modules')->updateOrInsert(['slug' => $module['slug']], [
                'name' => $module['name'],
                'frontend_route' => $module['route'],
                'icon' => null,
                'sort_order' => $module['sort'],
                'active' => true,
                'parent_id' => $parentId,
                'updated_at' => $now,
                'created_at' => $now,
            ]);
        }

        $this->attachAccess(
            ['rrhh', 'administrador', 'remuneraciones_admin', 'super_admin'],
            array_keys(self::PERMISSIONS),
            ['operational_management', 'hr_absence_management', 'hr_recruitment_management'],
            $now,
        );
        $this->attachAccess(
            ['remuneraciones_analista'],
            ['rrhh.ausencias.ver', 'rrhh.ausencias.gestionar', 'rrhh.ausencias.importar', 'rrhh.ausencias.exportar'],
            ['operational_management', 'hr_absence_management'],
            $now,
        );
    }

    private function attachAccess(array $roleSlugs, array $permissionSlugs, array $moduleSlugs, mixed $now): void
    {
        $roleIds = DB::table('roles')->whereIn('slug', $roleSlugs)->pluck('id');
        $permissionIds = DB::table('permissions')->whereIn('slug', $permissionSlugs)->pluck('id');
        $moduleIds = DB::table('system_modules')->whereIn('slug', $moduleSlugs)->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            foreach ($moduleIds as $moduleId) {
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
