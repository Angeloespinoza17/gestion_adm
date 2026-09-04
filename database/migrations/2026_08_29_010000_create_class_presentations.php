<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('class_presentations')) {
            Schema::create('class_presentations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('series_uuid');
                $table->unsignedSmallInteger('version')->default(1);
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
                $table->foreignId('course_id')->constrained('course_sections')->restrictOnDelete();
                $table->foreignId('subject_id')->constrained('schedule_subjects')->restrictOnDelete();
                $table->foreignId('unit_id')->constrained('lcd_curriculum_units')->restrictOnDelete();
                $table->string('title');
                $table->string('status', 40)->default('draft');
                $table->unsignedTinyInteger('progress')->default(0);
                $table->json('configuration');
                $table->json('curricular_snapshot');
                $table->longText('deck_json')->nullable();
                $table->string('model', 120)->nullable();
                $table->string('prompt_name', 100);
                $table->string('prompt_version', 40);
                $table->string('openai_response_id', 191)->nullable();
                $table->string('failure_code', 100)->nullable();
                $table->text('failure_message')->nullable();
                $table->timestamp('generated_at')->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();

                $table->unique(['series_uuid', 'version'], 'class_presentations_series_version_uq');
                $table->index(['school_id', 'user_id', 'created_at'], 'class_presentations_school_user_idx');
                $table->index(['school_id', 'status', 'created_at'], 'class_presentations_school_status_idx');
                $table->index(['academic_year_id', 'course_id', 'subject_id', 'unit_id'], 'class_presentations_curriculum_idx');
            });
        }

        if (! Schema::hasTable('class_presentation_learning_objective')) {
            Schema::create('class_presentation_learning_objective', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('class_presentation_id');
                $table->foreignId('learning_objective_id');
                $table->timestamps();
                $table->unique(['class_presentation_id', 'learning_objective_id'], 'class_presentation_objective_uq');
                $table->foreign('class_presentation_id', 'cp_objective_presentation_fk')
                    ->references('id')->on('class_presentations')->restrictOnDelete();
                $table->foreign('learning_objective_id', 'cp_objective_learning_fk')
                    ->references('id')->on('lcd_learning_objectives')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('class_presentation_files')) {
            Schema::create('class_presentation_files', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('class_presentation_id');
                $table->unsignedSmallInteger('version');
                $table->string('type', 30);
                $table->string('disk', 80);
                $table->text('path');
                $table->string('filename');
                $table->string('mime_type', 191);
                $table->unsignedBigInteger('size');
                $table->char('checksum', 64);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['class_presentation_id', 'version', 'type'], 'class_presentation_files_lookup_idx');
                $table->foreign('class_presentation_id', 'cp_file_presentation_fk')
                    ->references('id')->on('class_presentations')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('class_presentation_reference_files')) {
            Schema::create('class_presentation_reference_files', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('class_presentation_id');
                $table->string('disk', 80);
                $table->text('path');
                $table->string('original_filename');
                $table->string('mime_type', 191);
                $table->unsignedBigInteger('size');
                $table->char('checksum', 64);
                $table->foreignId('uploaded_by');
                $table->timestamps();

                $table->index(['class_presentation_id', 'created_at'], 'class_presentation_refs_lookup_idx');
                $table->foreign('class_presentation_id', 'cp_reference_presentation_fk')
                    ->references('id')->on('class_presentations')->restrictOnDelete();
                $table->foreign('uploaded_by', 'cp_reference_uploader_fk')
                    ->references('id')->on('users')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('class_presentation_generation_runs')) {
            Schema::create('class_presentation_generation_runs', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('class_presentation_id');
                $table->unsignedSmallInteger('version');
                $table->string('status', 40);
                $table->string('model', 120)->nullable();
                $table->string('openai_response_id', 191)->nullable();
                $table->unsignedInteger('input_tokens')->nullable();
                $table->unsignedInteger('output_tokens')->nullable();
                $table->unsignedInteger('total_tokens')->nullable();
                $table->unsignedInteger('duration_ms')->nullable();
                $table->string('failure_code', 100)->nullable();
                $table->text('failure_message')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();

                $table->index(['class_presentation_id', 'version', 'created_at'], 'class_presentation_runs_lookup_idx');
                $table->foreign('class_presentation_id', 'cp_run_presentation_fk')
                    ->references('id')->on('class_presentations')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Migración productiva forward-only: nunca elimina presentaciones ni archivos históricos.
    }
};
