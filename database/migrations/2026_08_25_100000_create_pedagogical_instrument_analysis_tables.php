<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pedagogical_instruments')) {
            Schema::create('pedagogical_instruments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('school_id')->constrained('lcd_schools', 'id', 'ped_inst_school_fk')->restrictOnDelete();
                $table->foreignId('academic_year_id')->constrained('academic_years', 'id', 'ped_inst_year_fk')->restrictOnDelete();
                $table->foreignId('owner_user_id')->constrained('users', 'id', 'ped_inst_owner_fk')->restrictOnDelete();
                $table->foreignId('subject_id')->constrained('schedule_subjects', 'id', 'ped_inst_subject_fk')->restrictOnDelete();
                $table->foreignId('unit_id')->nullable()->constrained('lcd_curriculum_units', 'id', 'ped_inst_unit_fk')->nullOnDelete();
                $table->string('title', 191);
                $table->string('grade_label', 80)->nullable();
                $table->string('instrument_type', 50);
                $table->string('evaluation_purpose', 30);
                $table->string('work_modality', 30);
                $table->date('application_date')->nullable();
                $table->unsignedSmallInteger('duration_minutes')->nullable();
                $table->decimal('declared_total_points', 10, 2)->nullable();
                $table->decimal('passing_percentage', 6, 2)->nullable();
                $table->decimal('weighting_percentage', 6, 2)->nullable();
                $table->decimal('minimum_grade', 5, 2)->nullable();
                $table->decimal('maximum_grade', 5, 2)->nullable();
                $table->string('status', 40)->default('draft');
                $table->text('accessibility_measures')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users', 'id', 'ped_inst_created_by_fk')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users', 'id', 'ped_inst_updated_by_fk')->nullOnDelete();
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['school_id', 'academic_year_id', 'status'], 'ped_inst_school_year_status_idx');
                $table->index(['school_id', 'subject_id', 'application_date'], 'ped_inst_school_subject_date_idx');
                $table->index(['school_id', 'owner_user_id', 'status'], 'ped_inst_school_owner_status_idx');
                $table->index(['school_id', 'instrument_type', 'evaluation_purpose'], 'ped_inst_school_type_purpose_idx');
            });
        }

        if (! Schema::hasTable('pedagogical_instrument_courses')) {
            Schema::create('pedagogical_instrument_courses', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('instrument_id')->constrained('pedagogical_instruments', 'id', 'ped_inst_course_instrument_fk')->restrictOnDelete();
                $table->foreignId('course_id')->constrained('course_sections', 'id', 'ped_inst_course_course_fk')->restrictOnDelete();
                $table->timestamps();

                $table->unique(['instrument_id', 'course_id'], 'ped_inst_courses_instrument_course_uq');
                $table->index(['course_id', 'instrument_id'], 'ped_inst_courses_course_instrument_idx');
            });
        }

        if (! Schema::hasTable('pedagogical_instrument_objectives')) {
            Schema::create('pedagogical_instrument_objectives', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('instrument_id')->constrained('pedagogical_instruments', 'id', 'ped_inst_obj_instrument_fk')->restrictOnDelete();
                $table->foreignId('learning_objective_id')->nullable()->constrained('lcd_learning_objectives', 'id', 'ped_inst_obj_learning_fk')->nullOnDelete();
                $table->string('origin', 20);
                $table->string('detected_code', 100)->nullable();
                $table->text('source_excerpt')->nullable();
                $table->unsignedInteger('page_number')->nullable();
                $table->string('confirmation_status', 30)->default('pending');
                $table->foreignId('confirmed_by')->nullable()->constrained('users', 'id', 'ped_inst_obj_confirmed_by_fk')->nullOnDelete();
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamps();

                $table->unique(['instrument_id', 'learning_objective_id', 'origin'], 'ped_inst_obj_instrument_objective_origin_uq');
                $table->unique(['instrument_id', 'detected_code', 'origin'], 'ped_inst_obj_instrument_code_origin_uq');
                $table->index(['instrument_id', 'confirmation_status'], 'ped_inst_obj_instrument_status_idx');
            });
        }

        if (! Schema::hasTable('pedagogical_instrument_files')) {
            Schema::create('pedagogical_instrument_files', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('school_id')->constrained('lcd_schools', 'id', 'ped_inst_file_school_fk')->restrictOnDelete();
                $table->foreignId('instrument_id')->constrained('pedagogical_instruments', 'id', 'ped_inst_file_instrument_fk')->restrictOnDelete();
                $table->unsignedInteger('version');
                $table->string('original_filename', 191);
                $table->string('internal_filename', 100);
                $table->string('storage_disk', 50);
                $table->string('storage_path', 500);
                $table->string('mime_type', 100);
                $table->unsignedBigInteger('file_size');
                $table->char('sha256', 64);
                $table->unsignedInteger('page_count')->nullable();
                $table->boolean('is_encrypted')->default(false);
                $table->boolean('has_text_layer')->nullable();
                $table->json('technical_metadata')->nullable();
                $table->foreignId('uploaded_by')->nullable()->constrained('users', 'id', 'ped_inst_file_uploaded_by_fk')->nullOnDelete();
                $table->timestamps();

                $table->unique(['instrument_id', 'version'], 'ped_inst_files_instrument_version_uq');
                $table->unique(['school_id', 'sha256'], 'ped_inst_files_school_hash_uq');
                $table->index(['instrument_id', 'created_at'], 'ped_inst_files_instrument_date_idx');
            });
        }

        if (! Schema::hasTable('pedagogical_instrument_analysis_runs')) {
            Schema::create('pedagogical_instrument_analysis_runs', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('instrument_id')->constrained('pedagogical_instruments', 'id', 'ped_inst_run_instrument_fk')->restrictOnDelete();
                $table->foreignId('instrument_file_id')->constrained('pedagogical_instrument_files', 'id', 'ped_inst_run_file_fk')->restrictOnDelete();
                $table->string('status', 40)->default('pending');
                $table->string('extractor', 100);
                $table->string('extractor_version', 50);
                $table->string('rules_version', 50);
                $table->longText('extracted_text')->nullable();
                $table->longText('normalized_text')->nullable();
                $table->json('extracted_data')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->string('error_code', 100)->nullable();
                $table->text('error_message')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users', 'id', 'ped_inst_run_created_by_fk')->nullOnDelete();
                $table->timestamps();

                $table->index(['instrument_id', 'created_at'], 'ped_inst_runs_instrument_date_idx');
                $table->index(['instrument_file_id', 'rules_version', 'status'], 'ped_inst_runs_file_rules_status_idx');
                $table->index(['status', 'started_at'], 'ped_inst_runs_status_started_idx');
            });
        }

        if (! Schema::hasTable('pedagogical_instrument_validation_results')) {
            Schema::create('pedagogical_instrument_validation_results', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('analysis_run_id')->constrained('pedagogical_instrument_analysis_runs', 'id', 'ped_inst_result_run_fk')->restrictOnDelete();
                $table->string('code', 100);
                $table->string('category', 40);
                $table->string('severity', 20);
                $table->string('outcome', 30);
                $table->string('reliability', 30);
                $table->string('field_path', 191)->nullable();
                $table->string('title', 191);
                $table->text('message');
                $table->json('detected_value')->nullable();
                $table->json('expected_value')->nullable();
                $table->text('source_excerpt')->nullable();
                $table->unsignedInteger('page_number')->nullable();
                $table->boolean('is_blocking')->default(false);
                $table->timestamp('resolved_at')->nullable();
                $table->foreignId('resolved_by')->nullable()->constrained('users', 'id', 'ped_inst_result_resolved_by_fk')->nullOnDelete();
                $table->string('resolution_status', 30)->nullable();
                $table->text('resolution_notes')->nullable();
                $table->timestamps();

                $table->index(['analysis_run_id', 'severity', 'outcome'], 'ped_inst_results_run_severity_outcome_idx');
                $table->index(['analysis_run_id', 'is_blocking', 'resolved_at'], 'ped_inst_results_run_blocking_resolved_idx');
                $table->index(['code', 'outcome'], 'ped_inst_results_code_outcome_idx');
            });
        }

        if (! Schema::hasTable('pedagogical_instrument_validation_events')) {
            Schema::create('pedagogical_instrument_validation_events', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('validation_result_id')->constrained('pedagogical_instrument_validation_results', 'id', 'ped_inst_event_result_fk')->restrictOnDelete();
                $table->string('action', 30);
                $table->text('notes')->nullable();
                $table->json('before_snapshot')->nullable();
                $table->json('after_snapshot')->nullable();
                $table->foreignId('performed_by')->nullable()->constrained('users', 'id', 'ped_inst_event_performed_by_fk')->nullOnDelete();
                $table->timestamp('performed_at');
                $table->timestamps();

                $table->index(['validation_result_id', 'performed_at'], 'ped_inst_val_events_result_date_idx');
            });
        }

        $this->ensureForeignKeys();
    }

    private function ensureForeignKeys(): void
    {
        $foreignKeys = [
            ['pedagogical_instruments', 'school_id', 'lcd_schools', 'ped_inst_school_fk', false],
            ['pedagogical_instruments', 'academic_year_id', 'academic_years', 'ped_inst_year_fk', false],
            ['pedagogical_instruments', 'owner_user_id', 'users', 'ped_inst_owner_fk', false],
            ['pedagogical_instruments', 'subject_id', 'schedule_subjects', 'ped_inst_subject_fk', false],
            ['pedagogical_instruments', 'unit_id', 'lcd_curriculum_units', 'ped_inst_unit_fk', true],
            ['pedagogical_instruments', 'created_by', 'users', 'ped_inst_created_by_fk', true],
            ['pedagogical_instruments', 'updated_by', 'users', 'ped_inst_updated_by_fk', true],
            ['pedagogical_instrument_courses', 'instrument_id', 'pedagogical_instruments', 'ped_inst_course_instrument_fk', false],
            ['pedagogical_instrument_courses', 'course_id', 'course_sections', 'ped_inst_course_course_fk', false],
            ['pedagogical_instrument_objectives', 'instrument_id', 'pedagogical_instruments', 'ped_inst_obj_instrument_fk', false],
            ['pedagogical_instrument_objectives', 'learning_objective_id', 'lcd_learning_objectives', 'ped_inst_obj_learning_fk', true],
            ['pedagogical_instrument_objectives', 'confirmed_by', 'users', 'ped_inst_obj_confirmed_by_fk', true],
            ['pedagogical_instrument_files', 'school_id', 'lcd_schools', 'ped_inst_file_school_fk', false],
            ['pedagogical_instrument_files', 'instrument_id', 'pedagogical_instruments', 'ped_inst_file_instrument_fk', false],
            ['pedagogical_instrument_files', 'uploaded_by', 'users', 'ped_inst_file_uploaded_by_fk', true],
            ['pedagogical_instrument_analysis_runs', 'instrument_id', 'pedagogical_instruments', 'ped_inst_run_instrument_fk', false],
            ['pedagogical_instrument_analysis_runs', 'instrument_file_id', 'pedagogical_instrument_files', 'ped_inst_run_file_fk', false],
            ['pedagogical_instrument_analysis_runs', 'created_by', 'users', 'ped_inst_run_created_by_fk', true],
            ['pedagogical_instrument_validation_results', 'analysis_run_id', 'pedagogical_instrument_analysis_runs', 'ped_inst_result_run_fk', false],
            ['pedagogical_instrument_validation_results', 'resolved_by', 'users', 'ped_inst_result_resolved_by_fk', true],
            ['pedagogical_instrument_validation_events', 'validation_result_id', 'pedagogical_instrument_validation_results', 'ped_inst_event_result_fk', false],
            ['pedagogical_instrument_validation_events', 'performed_by', 'users', 'ped_inst_event_performed_by_fk', true],
        ];

        foreach ($foreignKeys as [$tableName, $column, $foreignTable, $constraintName, $nullOnDelete]) {
            $exists = collect(Schema::getForeignKeys($tableName))
                ->contains(fn (array $foreign): bool => in_array($column, $foreign['columns'] ?? [], true));
            if ($exists) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($column, $foreignTable, $constraintName, $nullOnDelete): void {
                $foreign = $table->foreign($column, $constraintName)->references('id')->on($foreignTable);
                $nullOnDelete ? $foreign->nullOnDelete() : $foreign->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (app()->environment('production')) {
            return;
        }

        Schema::dropIfExists('pedagogical_instrument_validation_events');
        Schema::dropIfExists('pedagogical_instrument_validation_results');
        Schema::dropIfExists('pedagogical_instrument_analysis_runs');
        Schema::dropIfExists('pedagogical_instrument_files');
        Schema::dropIfExists('pedagogical_instrument_objectives');
        Schema::dropIfExists('pedagogical_instrument_courses');
        Schema::dropIfExists('pedagogical_instruments');
    }
};
