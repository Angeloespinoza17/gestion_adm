<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createIfMissing('prevent_risk_methodologies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80);
            $table->unsignedInteger('version_number')->default(1);
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->boolean('active')->default(true);
            $table->json('configuration');
            $table->timestamps();
            $table->unique(['code', 'version_number'], 'prevent_risk_methodology_code_version_unique');
            $table->index(['active', 'valid_from', 'valid_until'], 'prevent_risk_methodology_active_dates_idx');
        });

        $this->createIfMissing('prevent_risk_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('methodology_id')->nullable()->constrained('prevent_risk_methodologies')->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('prevent_risk_catalog_items')->restrictOnDelete();
            $table->string('catalog_type', 60);
            $table->string('code', 100);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color', 20)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->boolean('active')->default(true);
            $table->json('configuration')->nullable();
            $table->timestamps();
            $table->unique(['catalog_type', 'code', 'methodology_id'], 'prevent_risk_catalog_code_method_unique');
            $table->index(['catalog_type', 'active', 'sort_order'], 'prevent_risk_catalog_type_active_sort_idx');
        });

        $this->createIfMissing('prevent_risk_matrices', function (Blueprint $table) {
            $table->id();
            $table->string('company_key', 100)->default('institution');
            $table->string('company_name');
            $table->string('company_tax_id', 40)->nullable();
            $table->string('company_address')->nullable();
            $table->foreignId('work_center_id')->nullable()->constrained('maintenance_dependencies')->nullOnDelete();
            $table->string('work_center_name_snapshot')->nullable();
            $table->string('code', 80);
            $table->string('folio', 80)->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('active_version_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_key', 'code'], 'prevent_risk_matrix_company_code_unique');
            $table->index(['company_key', 'work_center_id'], 'prevent_risk_matrix_scope_idx');
            $table->index('active_version_id', 'prevent_risk_matrix_active_version_idx');
        });

        $this->createIfMissing('prevent_risk_matrix_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_matrix_id')->constrained('prevent_risk_matrices')->restrictOnDelete();
            $table->foreignId('methodology_id')->constrained('prevent_risk_methodologies')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status', 30)->default('draft');
            $table->string('company_name_snapshot');
            $table->string('company_tax_id_snapshot', 40)->nullable();
            $table->string('company_address_snapshot')->nullable();
            $table->string('economic_activity_code_snapshot', 80)->nullable();
            $table->string('work_center_name_snapshot')->nullable();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->date('prepared_on')->nullable();
            $table->date('updated_on')->nullable();
            $table->unsignedInteger('total_workers')->nullable();
            $table->foreignId('program_responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('program_responsible_name_snapshot')->nullable();
            $table->string('program_responsible_position_snapshot')->nullable();
            $table->text('review_reason')->nullable();
            $table->text('review_notes')->nullable();
            $table->date('effective_from')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->date('next_review_at')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('legal_representative_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('legal_representative_name_snapshot')->nullable();
            $table->string('legal_representative_position_snapshot')->nullable();
            $table->foreignId('source_version_id')->nullable()->constrained('prevent_risk_matrix_versions')->restrictOnDelete();
            $table->unsignedBigInteger('source_import_batch_id')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->string('snapshot_hash', 64)->nullable();
            $table->json('snapshot_payload')->nullable();
            $table->timestamps();
            $table->unique(['risk_matrix_id', 'version_number'], 'prevent_risk_matrix_version_unique');
            $table->index(['status', 'next_review_at'], 'prevent_risk_version_status_review_idx');
            $table->index(['approved_at', 'status'], 'prevent_risk_version_approved_status_idx');
        });

        $this->addForeignKeyIfMissing('prevent_risk_matrices', 'prevent_risk_matrix_active_version_fk', function (Blueprint $table) {
            $table->foreign('active_version_id', 'prevent_risk_matrix_active_version_fk')
                ->references('id')->on('prevent_risk_matrix_versions')->restrictOnDelete();
        });

        $this->createIfMissing('prevent_risk_matrix_processes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_matrix_version_id')->constrained('prevent_risk_matrix_versions')->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('process_type', 40)->default('operational');
            $table->unsignedInteger('display_order')->default(0);
            $table->text('observations')->nullable();
            $table->timestamps();
            $table->index(['risk_matrix_version_id', 'display_order'], 'prevent_risk_process_version_order_idx');
        });

        $this->createIfMissing('prevent_risk_matrix_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_matrix_process_id')->constrained('prevent_risk_matrix_processes')->restrictOnDelete();
            $table->text('activity_name');
            $table->text('task_name');
            $table->string('routine_type', 30)->default('routine');
            $table->foreignId('job_position_id')->nullable()->constrained('cargos')->nullOnDelete();
            $table->text('job_position_text')->nullable();
            $table->foreignId('location_id')->nullable()->constrained('maintenance_dependencies')->nullOnDelete();
            $table->text('specific_location')->nullable();
            $table->text('zero_exposure_justification')->nullable();
            $table->text('observations')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->index(['risk_matrix_process_id', 'display_order'], 'prevent_risk_task_process_order_idx');
            $table->index(['job_position_id', 'location_id'], 'prevent_risk_task_position_location_idx');
        });

        $this->createIfMissing('prevent_risk_task_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_matrix_task_id')->constrained('prevent_risk_matrix_tasks')->restrictOnDelete();
            $table->foreignId('cargo_id')->constrained('cargos')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['risk_matrix_task_id', 'cargo_id'], 'prevent_risk_task_position_unique');
        });

        $this->createIfMissing('prevent_risk_task_exposures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_matrix_task_id')->constrained('prevent_risk_matrix_tasks')->restrictOnDelete();
            $table->foreignId('exposure_category_id')->constrained('prevent_risk_catalog_items')->restrictOnDelete();
            $table->unsignedInteger('count')->default(0);
            $table->text('observations')->nullable();
            $table->timestamps();
            $table->unique(['risk_matrix_task_id', 'exposure_category_id'], 'prevent_risk_task_exposure_unique');
        });

        $this->createIfMissing('prevent_risk_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_matrix_task_id')->constrained('prevent_risk_matrix_tasks')->restrictOnDelete();
            $table->foreignId('risk_family_id')->constrained('prevent_risk_catalog_items')->restrictOnDelete();
            $table->foreignId('risk_catalog_item_id')->nullable()->constrained('prevent_risk_catalog_items')->restrictOnDelete();
            $table->string('specific_risk_code', 100)->nullable();
            $table->text('specific_risk_name');
            $table->text('possible_harm');
            $table->string('evaluation_method', 40)->default('vep');
            $table->string('declared_controlled_status', 40)->default('not_assessed');
            $table->string('verified_controlled_status', 40)->default('not_assessed');
            $table->string('legal_or_protocol_reference')->nullable();
            $table->text('notes')->nullable();
            $table->json('source_payload')->nullable();
            $table->unsignedInteger('source_row_number')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->index(['risk_family_id', 'evaluation_method'], 'prevent_risk_entry_family_method_idx');
            $table->index(['risk_matrix_task_id', 'display_order'], 'prevent_risk_entry_task_order_idx');
        });

        $this->createIfMissing('prevent_risk_hazard_factors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_entry_id')->constrained('prevent_risk_entries')->restrictOnDelete();
            $table->string('category', 50)->nullable();
            $table->text('hazard_description');
            $table->text('risk_factor_description')->nullable();
            $table->foreignId('source_catalog_id')->nullable()->constrained('prevent_risk_catalog_items')->restrictOnDelete();
            $table->timestamps();
            $table->index('risk_entry_id', 'prevent_risk_hazard_entry_idx');
        });

        $this->createIfMissing('prevent_risk_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_entry_id')->constrained('prevent_risk_entries')->restrictOnDelete();
            $table->foreignId('methodology_id')->constrained('prevent_risk_methodologies')->restrictOnDelete();
            $table->string('phase', 20)->default('current');
            $table->string('method', 40)->default('vep');
            $table->unsignedTinyInteger('probability')->nullable();
            $table->unsignedTinyInteger('consequence')->nullable();
            $table->decimal('calculated_score', 12, 3)->nullable();
            $table->foreignId('calculated_level_id')->nullable()->constrained('prevent_risk_catalog_items')->restrictOnDelete();
            $table->foreignId('protocol_id')->nullable()->constrained('prevent_risk_catalog_items')->restrictOnDelete();
            $table->string('protocol_version', 80)->nullable();
            $table->decimal('exposure_value', 14, 4)->nullable();
            $table->string('exposure_unit', 80)->nullable();
            $table->decimal('result_value', 14, 4)->nullable();
            $table->string('result_level', 100)->nullable();
            $table->string('instrument')->nullable();
            $table->string('evaluator')->nullable();
            $table->date('instrument_date')->nullable();
            $table->date('next_measurement_at')->nullable();
            $table->text('assessment_notes')->nullable();
            $table->foreignId('assessed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assessed_at')->nullable();
            $table->boolean('active')->default(true);
            $table->json('source_payload')->nullable();
            $table->timestamps();
            $table->index(['risk_entry_id', 'phase', 'active'], 'prevent_risk_assessment_current_idx');
            $table->index(['calculated_level_id', 'active'], 'prevent_risk_assessment_level_idx');
            $table->index('next_measurement_at', 'prevent_risk_assessment_measurement_idx');
        });

        $this->createIfMissing('prevent_risk_controls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_entry_id')->constrained('prevent_risk_entries')->restrictOnDelete();
            $table->string('control_stage', 30);
            $table->string('hierarchy_type', 50);
            $table->text('description');
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('responsible_employee_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->text('responsible_text')->nullable();
            $table->string('status', 30)->default('pending');
            $table->string('priority', 20)->default('medium');
            $table->date('planned_start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('periodicity_type', 30)->default('once');
            $table->unsignedInteger('periodicity_value')->nullable();
            $table->date('next_due_date')->nullable();
            $table->unsignedTinyInteger('progress_percentage')->default(0);
            $table->string('effectiveness_result', 30)->nullable();
            $table->text('effectiveness_notes')->nullable();
            $table->boolean('creates_program_action')->default(false);
            $table->timestamps();
            $table->index(['responsible_user_id', 'status', 'due_date'], 'prevent_risk_control_responsible_status_due_idx');
            $table->index(['status', 'due_date'], 'prevent_risk_control_status_due_idx');
            $table->index(['hierarchy_type', 'control_stage'], 'prevent_risk_control_hierarchy_stage_idx');
        });

        $this->createIfMissing('prevent_risk_evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_matrix_version_id')->constrained('prevent_risk_matrix_versions')->restrictOnDelete();
            $table->nullableMorphs('evidenceable');
            $table->string('evidence_type', 60);
            $table->string('description')->nullable();
            $table->date('evidence_date');
            $table->string('visibility', 30)->default('authorized');
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('file_size');
            $table->string('file_hash', 64);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['risk_matrix_version_id', 'evidence_type'], 'prevent_risk_evidence_version_type_idx');
            $table->index('file_hash', 'prevent_risk_evidence_hash_idx');
        });

        $this->createIfMissing('prevent_risk_matrix_participations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_matrix_version_id')->constrained('prevent_risk_matrix_versions', indexName: 'pr_participation_version_fk')->restrictOnDelete();
            $table->foreignId('participant_user_id')->nullable()->constrained('users', indexName: 'pr_participation_user_fk')->nullOnDelete();
            $table->foreignId('participant_employee_id')->nullable()->constrained('staff', indexName: 'pr_participation_employee_fk')->nullOnDelete();
            $table->string('participant_name_snapshot');
            $table->string('participant_role')->nullable();
            $table->string('representation_type', 60);
            $table->date('participation_date');
            $table->text('comments')->nullable();
            $table->text('response_or_resolution')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
            $table->index(['risk_matrix_version_id', 'representation_type'], 'prevent_risk_participation_version_type_idx');
        });

        $this->addForeignKeyIfMissing('prevent_risk_matrix_participations', 'pr_participation_version_fk', function (Blueprint $table) {
            $table->foreign('risk_matrix_version_id', 'pr_participation_version_fk')
                ->references('id')->on('prevent_risk_matrix_versions')->restrictOnDelete();
        });
        $this->addForeignKeyIfMissing('prevent_risk_matrix_participations', 'pr_participation_user_fk', function (Blueprint $table) {
            $table->foreign('participant_user_id', 'pr_participation_user_fk')
                ->references('id')->on('users')->nullOnDelete();
        });
        $this->addForeignKeyIfMissing('prevent_risk_matrix_participations', 'pr_participation_employee_fk', function (Blueprint $table) {
            $table->foreign('participant_employee_id', 'pr_participation_employee_fk')
                ->references('id')->on('staff')->nullOnDelete();
        });

        $this->createIfMissing('prevent_risk_matrix_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_matrix_version_id')->constrained('prevent_risk_matrix_versions')->restrictOnDelete();
            $table->string('review_type', 50);
            $table->string('trigger_reason', 60);
            $table->date('review_date');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('findings')->nullable();
            $table->boolean('requires_new_version')->default(false);
            $table->foreignId('resulting_version_id')->nullable()->constrained('prevent_risk_matrix_versions')->restrictOnDelete();
            $table->timestamps();
            $table->index(['risk_matrix_version_id', 'review_date'], 'prevent_risk_review_version_date_idx');
        });

        $this->createIfMissing('prevent_preventive_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_matrix_version_id')->unique()->constrained('prevent_risk_matrix_versions')->restrictOnDelete();
            $table->string('company_key', 100);
            $table->foreignId('work_center_id')->nullable()->constrained('maintenance_dependencies')->nullOnDelete();
            $table->string('code', 100)->unique();
            $table->string('name');
            $table->string('status', 30)->default('draft');
            $table->timestamp('generated_at');
            $table->date('due_to_be_prepared_at');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('progress_percentage')->default(0);
            $table->timestamps();
            $table->index(['status', 'due_to_be_prepared_at'], 'prevent_program_status_due_idx');
            $table->index(['company_key', 'work_center_id'], 'prevent_program_scope_idx');
        });

        $this->createIfMissing('prevent_preventive_program_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('preventive_program_id')->constrained('prevent_preventive_programs', indexName: 'pr_program_action_program_fk')->restrictOnDelete();
            $table->foreignId('risk_control_id')->unique()->constrained('prevent_risk_controls')->restrictOnDelete();
            $table->foreignId('process_id')->constrained('prevent_risk_matrix_processes')->restrictOnDelete();
            $table->foreignId('task_id')->constrained('prevent_risk_matrix_tasks')->restrictOnDelete();
            $table->foreignId('risk_entry_id')->constrained('prevent_risk_entries')->restrictOnDelete();
            $table->text('action_description');
            $table->string('hierarchy_type', 50);
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('work_center_id')->nullable()->constrained('maintenance_dependencies')->nullOnDelete();
            $table->date('planned_start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->date('actual_completion_date')->nullable();
            $table->string('periodicity', 30);
            $table->unsignedTinyInteger('progress_percentage')->default(0);
            $table->string('status', 30)->default('pending');
            $table->string('verification_result', 30)->nullable();
            $table->unsignedInteger('evidence_count')->default(0);
            $table->timestamps();
            $table->index(['responsible_id', 'status', 'due_date'], 'prevent_program_action_owner_status_due_idx');
            $table->index(['preventive_program_id', 'status'], 'prevent_program_action_program_status_idx');
        });

        $this->createIfMissing('prevent_risk_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('company_key', 100);
            $table->foreignId('work_center_id')->nullable()->constrained('maintenance_dependencies')->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('file_hash', 64)->index();
            $table->unsignedBigInteger('file_size');
            $table->string('status', 30)->default('uploaded');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('warning_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            $table->unsignedInteger('duplicate_rows')->default(0);
            $table->foreignId('target_matrix_id')->nullable()->constrained('prevent_risk_matrices')->restrictOnDelete();
            $table->foreignId('target_version_id')->nullable()->constrained('prevent_risk_matrix_versions')->restrictOnDelete();
            $table->json('mapping_configuration')->nullable();
            $table->json('preview_payload')->nullable();
            $table->json('summary')->nullable();
            $table->timestamps();
            $table->index(['company_key', 'status', 'created_at'], 'prevent_risk_import_scope_status_idx');
        });

        $this->addForeignKeyIfMissing('prevent_risk_matrix_versions', 'prevent_risk_version_import_fk', function (Blueprint $table) {
            $table->foreign('source_import_batch_id', 'prevent_risk_version_import_fk')
                ->references('id')->on('prevent_risk_import_batches')->restrictOnDelete();
        });

        $this->createIfMissing('prevent_risk_import_row_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_import_batch_id')->constrained('prevent_risk_import_batches')->restrictOnDelete();
            $table->string('sheet_name');
            $table->unsignedInteger('row_number');
            $table->string('column_name')->nullable();
            $table->text('raw_value')->nullable();
            $table->text('normalized_value')->nullable();
            $table->string('severity', 20);
            $table->string('issue_code', 80);
            $table->text('message');
            $table->string('resolution_status', 30)->default('pending');
            $table->timestamps();
            $table->index(['risk_import_batch_id', 'severity'], 'prevent_risk_import_issue_batch_severity_idx');
        });

        $this->createIfMissing('prevent_risk_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('company_key', 100);
            $table->foreignId('work_center_id')->nullable()->constrained('maintenance_dependencies')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->nullableMorphs('auditable');
            $table->string('action', 80);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();
            $table->index(['company_key', 'action', 'created_at'], 'prevent_risk_audit_scope_action_idx');
        });

        $this->createIfMissing('prevent_risk_alert_logs', function (Blueprint $table) {
            $table->id();
            $table->string('alert_key')->unique();
            $table->string('alert_type', 60);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->nullableMorphs('alertable');
            $table->timestamp('sent_at');
            $table->timestamps();
            $table->index(['alert_type', 'sent_at'], 'prevent_risk_alert_type_sent_idx');
        });
    }

    private function createIfMissing(string $table, \Closure $callback): void
    {
        if (! Schema::hasTable($table)) {
            Schema::create($table, $callback);
        }
    }

    private function addForeignKeyIfMissing(string $table, string $foreignKey, \Closure $callback): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $exists = collect(Schema::getForeignKeys($table))
            ->contains(fn (array $definition): bool => $definition['name'] === $foreignKey);

        if (! $exists) {
            Schema::table($table, $callback);
        }
    }

    public function down(): void
    {
        // Migración forward-only: un rollback nunca elimina matrices,
        // evaluaciones, evidencias, aprobaciones ni auditoría histórica.
    }
};
