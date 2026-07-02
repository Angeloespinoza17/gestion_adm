<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pme_planes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->unsignedInteger('school_year')->index();
            $table->string('name');
            $table->string('period_label', 120)->nullable();
            $table->string('cycle_name', 120)->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('state', 50)->default('borrador')->index();
            $table->boolean('is_active')->default(false)->index();
            $table->text('general_description')->nullable();
            $table->text('observations')->nullable();
            $table->foreignId('cloned_from_plan_id')->nullable()->constrained('pme_planes')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_year', 'state']);
        });

        Schema::create('pme_ciclos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pme_plan_id')->constrained('pme_planes')->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->string('state', 50)->default('pendiente')->index();
            $table->boolean('is_current')->default(false);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['pme_plan_id', 'name']);
        });

        Schema::create('pme_dimensiones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pme_objetivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pme_plan_id')->constrained('pme_planes')->cascadeOnDelete();
            $table->foreignId('pme_dimension_id')->constrained('pme_dimensiones')->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('strategic_goal')->nullable();
            $table->string('global_indicator')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('state', 50)->default('borrador')->index();
            $table->decimal('progress_percentage', 5, 2)->default(0);
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pme_estrategias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pme_objective_id')->constrained('pme_objetivos')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('execution_period', 120)->nullable();
            $table->string('state', 50)->default('planificada')->index();
            $table->decimal('progress_percentage', 5, 2)->default(0);
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pme_indicadores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pme_objective_id')->constrained('pme_objetivos')->cascadeOnDelete();
            $table->foreignId('pme_strategy_id')->nullable()->constrained('pme_estrategias')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('indicator_type', 80)->default('gestion')->index();
            $table->decimal('baseline_value', 12, 2)->nullable();
            $table->decimal('target_value', 12, 2)->nullable();
            $table->decimal('current_value', 12, 2)->nullable();
            $table->string('measurement_unit', 80)->nullable();
            $table->string('verification_source')->nullable();
            $table->string('measurement_frequency', 50)->default('mensual');
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('state', 50)->default('sin_medicion')->index();
            $table->decimal('compliance_percentage', 5, 2)->default(0);
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pme_indicador_mediciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pme_indicator_id')->constrained('pme_indicadores')->cascadeOnDelete();
            $table->date('measured_at')->index();
            $table->decimal('measured_value', 12, 2);
            $table->decimal('compliance_percentage', 5, 2)->default(0);
            $table->string('state', 50)->default('en_avance')->index();
            $table->string('information_source')->nullable();
            $table->text('analysis')->nullable();
            $table->text('observations')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pme_acciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pme_plan_id')->constrained('pme_planes')->cascadeOnDelete();
            $table->foreignId('pme_dimension_id')->constrained('pme_dimensiones')->restrictOnDelete();
            $table->foreignId('pme_objective_id')->constrained('pme_objetivos')->restrictOnDelete();
            $table->foreignId('pme_strategy_id')->constrained('pme_estrategias')->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('justification')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('responsible_area', 120)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('planned_budget', 14, 2)->default(0);
            $table->decimal('committed_budget', 14, 2)->default(0);
            $table->decimal('executed_budget', 14, 2)->default(0);
            $table->string('funding_source', 80)->default('SEP')->index();
            $table->string('cost_center_reference', 120)->nullable();
            $table->string('external_accounting_reference', 120)->nullable();
            $table->string('document_reference', 120)->nullable();
            $table->unsignedInteger('minimum_evidence_required')->default(1);
            $table->decimal('progress_percentage', 5, 2)->default(0);
            $table->timestamp('last_progress_at')->nullable();
            $table->string('state', 50)->default('borrador')->index();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['pme_plan_id', 'state']);
            $table->index(['start_date', 'end_date']);
        });

        Schema::create('pme_action_indicator', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pme_action_id')->constrained('pme_acciones')->cascadeOnDelete();
            $table->foreignId('pme_indicator_id')->constrained('pme_indicadores')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['pme_action_id', 'pme_indicator_id']);
        });

        Schema::create('pme_actividades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pme_action_id')->constrained('pme_acciones')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('scheduled_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->string('state', 50)->default('pendiente')->index();
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pme_hitos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pme_action_id')->constrained('pme_acciones')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('planned_date')->nullable();
            $table->date('actual_completion_date')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('progress_percentage', 5, 2)->default(0);
            $table->string('state', 50)->default('pendiente')->index();
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pme_medicion_metas_estrategicas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pme_objective_id')->constrained('pme_objetivos')->cascadeOnDelete();
            $table->string('goal_label');
            $table->decimal('baseline_value', 12, 2)->nullable();
            $table->decimal('expected_result', 12, 2)->nullable();
            $table->decimal('current_result', 12, 2)->nullable();
            $table->decimal('compliance_percentage', 5, 2)->default(0);
            $table->string('information_source')->nullable();
            $table->date('measured_at')->index();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('analysis')->nullable();
            $table->string('state', 50)->default('sin_medicion')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pme_monitoreos_reflexivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pme_plan_id')->constrained('pme_planes')->cascadeOnDelete();
            $table->foreignId('pme_dimension_id')->nullable()->constrained('pme_dimensiones')->nullOnDelete();
            $table->foreignId('pme_objective_id')->nullable()->constrained('pme_objetivos')->nullOnDelete();
            $table->foreignId('pme_strategy_id')->nullable()->constrained('pme_estrategias')->nullOnDelete();
            $table->foreignId('pme_action_id')->nullable()->constrained('pme_acciones')->nullOnDelete();
            $table->date('monitored_at')->index();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('guiding_questions')->nullable();
            $table->text('observed_progress')->nullable();
            $table->text('difficulties')->nullable();
            $table->text('reviewed_evidences')->nullable();
            $table->text('decisions_taken')->nullable();
            $table->text('required_adjustments')->nullable();
            $table->text('next_steps')->nullable();
            $table->string('state', 50)->default('borrador')->index();
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pme_evidencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pme_action_id')->nullable()->constrained('pme_acciones')->nullOnDelete();
            $table->foreignId('pme_activity_id')->nullable()->constrained('pme_actividades')->nullOnDelete();
            $table->foreignId('pme_milestone_id')->nullable()->constrained('pme_hitos')->nullOnDelete();
            $table->foreignId('pme_indicator_measurement_id')->nullable()->constrained('pme_indicador_mediciones')->nullOnDelete();
            $table->foreignId('pme_goal_measurement_id')->nullable()->constrained('pme_medicion_metas_estrategicas')->nullOnDelete();
            $table->foreignId('pme_reflective_monitoring_id')->nullable()->constrained('pme_monitoreos_reflexivos')->nullOnDelete();
            $table->string('evidence_type', 80)->default('documento_externo')->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('review_status', 50)->default('cargada')->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observations')->nullable();
            $table->text('review_comments')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pme_ingresos_sep', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pme_plan_id')->nullable()->constrained('pme_planes')->nullOnDelete();
            $table->unsignedInteger('school_year')->index();
            $table->unsignedTinyInteger('month')->index();
            $table->string('income_type', 80)->default('sep_regular')->index();
            $table->decimal('estimated_amount', 14, 2)->default(0);
            $table->decimal('received_amount', 14, 2)->default(0);
            $table->date('received_at')->nullable()->index();
            $table->string('bank_account')->nullable();
            $table->string('supporting_document_path')->nullable();
            $table->string('supporting_document_name')->nullable();
            $table->text('observations')->nullable();
            $table->string('state', 50)->default('registrado')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pme_estudiantes_sep', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->string('classification', 50)->default('pendiente_validacion')->index();
            $table->date('loaded_at')->nullable()->index();
            $table->string('source', 120)->nullable();
            $table->string('supporting_document_path')->nullable();
            $table->string('supporting_document_name')->nullable();
            $table->string('state', 50)->default('pendiente')->index();
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['student_profile_id', 'academic_year_id'], 'pme_student_sep_year_unique');
        });

        Schema::create('pme_reportes_generados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pme_plan_id')->nullable()->constrained('pme_planes')->nullOnDelete();
            $table->string('report_type', 80)->index();
            $table->string('title');
            $table->json('filters')->nullable();
            $table->json('summary')->nullable();
            $table->string('format', 20)->default('pantalla');
            $table->unsignedInteger('rows_count')->default(0);
            $table->string('state', 50)->default('generado')->index();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->string('file_path')->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();
        });

        Schema::create('pme_alertas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pme_plan_id')->nullable()->constrained('pme_planes')->nullOnDelete();
            $table->string('alert_type', 80)->index();
            $table->string('severity', 30)->default('media')->index();
            $table->string('title');
            $table->text('message')->nullable();
            $table->string('related_type', 120)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->date('due_date')->nullable();
            $table->string('state', 50)->default('pendiente')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->json('payload')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pme_historial_cambios', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 120)->index();
            $table->unsignedBigInteger('subject_id')->index();
            $table->string('action', 80)->index();
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->text('notes')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pme_historial_cambios');
        Schema::dropIfExists('pme_alertas');
        Schema::dropIfExists('pme_reportes_generados');
        Schema::dropIfExists('pme_estudiantes_sep');
        Schema::dropIfExists('pme_ingresos_sep');
        Schema::dropIfExists('pme_evidencias');
        Schema::dropIfExists('pme_monitoreos_reflexivos');
        Schema::dropIfExists('pme_medicion_metas_estrategicas');
        Schema::dropIfExists('pme_hitos');
        Schema::dropIfExists('pme_actividades');
        Schema::dropIfExists('pme_action_indicator');
        Schema::dropIfExists('pme_acciones');
        Schema::dropIfExists('pme_indicador_mediciones');
        Schema::dropIfExists('pme_indicadores');
        Schema::dropIfExists('pme_estrategias');
        Schema::dropIfExists('pme_objetivos');
        Schema::dropIfExists('pme_dimensiones');
        Schema::dropIfExists('pme_ciclos');
        Schema::dropIfExists('pme_planes');
    }
};
