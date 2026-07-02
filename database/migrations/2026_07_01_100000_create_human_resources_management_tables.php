<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_document_controls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('related_area', 80)->default('rrhh')->index();
            $table->string('document_type', 100)->index();
            $table->string('title');
            $table->string('folio', 120)->nullable();
            $table->date('issued_at')->nullable()->index();
            $table->date('expires_at')->nullable()->index();
            $table->unsignedSmallInteger('alert_days')->default(30);
            $table->string('status', 40)->default('vigente')->index();
            $table->string('owner_area', 120)->nullable();
            $table->string('file_path')->nullable();
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['staff_id', 'document_type', 'status'], 'hr_docs_staff_type_status_idx');
        });

        Schema::create('hr_medical_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('period_id')->nullable()->constrained('remuneration_periods')->nullOnDelete();
            $table->foreignId('document_control_id')->nullable()->constrained('hr_document_controls')->nullOnDelete();
            $table->foreignId('payroll_movement_id')->nullable()->constrained('remuneration_movements')->nullOnDelete();
            $table->string('license_number', 120)->nullable()->index();
            $table->string('issuer', 160)->nullable();
            $table->string('diagnosis_group', 160)->nullable();
            $table->date('starts_at')->index();
            $table->date('ends_at')->index();
            $table->decimal('days', 8, 2)->default(0);
            $table->boolean('affects_payroll')->default(true);
            $table->string('subsidy_status', 60)->nullable();
            $table->string('status', 40)->default('ingresada')->index();
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['staff_id', 'starts_at', 'ends_at'], 'hr_med_leave_staff_dates_idx');
        });

        Schema::create('hr_job_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cargo_id')->nullable()->constrained('cargos')->nullOnDelete();
            $table->string('code', 80)->unique();
            $table->string('title');
            $table->string('area', 140)->nullable()->index();
            $table->text('purpose')->nullable();
            $table->json('responsibilities')->nullable();
            $table->json('requirements')->nullable();
            $table->json('competencies')->nullable();
            $table->json('workload_profile')->nullable();
            $table->string('version', 40)->default('1.0');
            $table->string('status', 40)->default('vigente')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('hr_onboarding_processes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('job_profile_id')->nullable()->constrained('hr_job_profiles')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('starts_at')->index();
            $table->date('target_completion_at')->nullable()->index();
            $table->date('completed_at')->nullable();
            $table->string('status', 40)->default('pendiente')->index();
            $table->json('documents_checklist')->nullable();
            $table->json('trainings_checklist')->nullable();
            $table->json('accesses_checklist')->nullable();
            $table->json('materials_checklist')->nullable();
            $table->decimal('completion_percent', 5, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('hr_climate_surveys', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('scope', 140)->nullable()->index();
            $table->date('starts_at')->nullable()->index();
            $table->date('ends_at')->nullable()->index();
            $table->string('status', 40)->default('borrador')->index();
            $table->unsignedInteger('response_count')->default(0);
            $table->decimal('satisfaction_score', 5, 2)->nullable();
            $table->string('risk_level', 40)->default('medio')->index();
            $table->json('questions')->nullable();
            $table->json('alerts')->nullable();
            $table->json('report_payload')->nullable();
            $table->text('summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('hr_climate_action_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->nullable()->constrained('hr_climate_surveys')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('risk_level', 40)->default('medio')->index();
            $table->text('action')->nullable();
            $table->date('due_date')->nullable()->index();
            $table->date('completed_at')->nullable();
            $table->string('status', 40)->default('pendiente')->index();
            $table->json('evidence')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('hr_workload_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->foreignId('period_id')->nullable()->constrained('remuneration_periods')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('replacement_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('function_name');
            $table->string('role_type', 80)->nullable()->index();
            $table->decimal('contracted_hours', 8, 2)->default(0);
            $table->decimal('classroom_hours', 8, 2)->default(0);
            $table->decimal('non_classroom_hours', 8, 2)->default(0);
            $table->decimal('coordination_hours', 8, 2)->default(0);
            $table->decimal('pie_hours', 8, 2)->default(0);
            $table->decimal('sep_hours', 8, 2)->default(0);
            $table->decimal('replacement_hours', 8, 2)->default(0);
            $table->date('starts_at')->nullable()->index();
            $table->date('ends_at')->nullable()->index();
            $table->string('status', 40)->default('vigente')->index();
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['staff_id', 'period_id', 'status'], 'hr_workload_staff_period_status_idx');
        });

        Schema::create('hr_cv_bank_entries', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('rut', 20)->nullable()->index();
            $table->string('email')->nullable();
            $table->string('phone', 80)->nullable();
            $table->string('source', 120)->nullable();
            $table->string('desired_position', 160)->nullable()->index();
            $table->string('specialty', 160)->nullable()->index();
            $table->decimal('experience_years', 5, 2)->nullable();
            $table->string('availability', 120)->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->string('status', 40)->default('postulante')->index();
            $table->string('cv_path')->nullable();
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('hr_replacement_pool_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cv_bank_entry_id')->nullable()->constrained('hr_cv_bank_entries')->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('full_name');
            $table->string('specialty', 160)->nullable()->index();
            $table->string('subject_area', 160)->nullable()->index();
            $table->date('available_from')->nullable()->index();
            $table->date('available_until')->nullable()->index();
            $table->decimal('preferred_hours', 8, 2)->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->date('last_replacement_at')->nullable();
            $table->string('status', 40)->default('disponible')->index();
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('hr_labor_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->string('certificate_type', 100)->default('antiguedad')->index();
            $table->string('purpose', 180)->nullable();
            $table->date('requested_at')->nullable()->index();
            $table->date('issued_at')->nullable()->index();
            $table->string('folio', 120)->nullable()->unique();
            $table->foreignId('signed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 40)->default('solicitado')->index();
            $table->json('payload')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_labor_certificates');
        Schema::dropIfExists('hr_replacement_pool_entries');
        Schema::dropIfExists('hr_cv_bank_entries');
        Schema::dropIfExists('hr_workload_assignments');
        Schema::dropIfExists('hr_climate_action_plans');
        Schema::dropIfExists('hr_climate_surveys');
        Schema::dropIfExists('hr_onboarding_processes');
        Schema::dropIfExists('hr_job_profiles');
        Schema::dropIfExists('hr_medical_leaves');
        Schema::dropIfExists('hr_document_controls');
    }
};
