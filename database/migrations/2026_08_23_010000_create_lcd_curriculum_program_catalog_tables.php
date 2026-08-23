<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lcd_curriculum_versions')) {
            Schema::create('lcd_curriculum_versions', function (Blueprint $table): void {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('decree', 160)->nullable();
                $table->string('resolution', 160)->nullable();
                $table->string('issuing_authority', 180)->nullable();
                $table->unsignedSmallInteger('publication_year')->nullable();
                $table->date('valid_from')->nullable();
                $table->date('valid_until')->nullable();
                $table->string('status', 40)->default('draft');
                $table->text('official_url')->nullable();
                $table->char('identity_hash', 64)->unique();
                $table->timestamp('verified_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'valid_from', 'valid_until'], 'lcd_curr_versions_status_dates_idx');
            });
        }

        if (! Schema::hasTable('lcd_curriculum_programs')) {
            Schema::create('lcd_curriculum_programs', function (Blueprint $table): void {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('schedule_subject_id')->constrained('schedule_subjects')->restrictOnDelete();
                $table->foreignId('education_level_id')->constrained('education_levels')->restrictOnDelete();
                $table->foreignId('curriculum_version_id')->constrained('lcd_curriculum_versions')->restrictOnDelete();
                $table->foreignId('curriculum_catalog_id')->nullable()->constrained('lcd_curriculum_catalogs')->restrictOnDelete();
                $table->string('level_code', 60)->nullable();
                $table->string('grade_code', 60);
                $table->string('cycle_code', 60)->nullable();
                $table->string('modality_code', 60)->nullable();
                $table->string('formation_type_code', 60)->nullable();
                $table->string('curriculum_track', 30)->nullable();
                $table->string('official_name');
                $table->string('official_code', 160)->nullable();
                $table->text('description')->nullable();
                $table->unsignedSmallInteger('estimated_weeks')->nullable();
                $table->unsignedInteger('estimated_pedagogical_hours')->nullable();
                $table->date('valid_from')->nullable();
                $table->date('valid_until')->nullable();
                $table->string('status', 40)->default('draft');
                $table->char('identity_hash', 64)->unique();
                $table->unsignedInteger('revision')->default(1);
                $table->timestamp('published_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['schedule_subject_id', 'grade_code', 'status'], 'lcd_curr_program_subject_grade_status_idx');
                $table->index(['education_level_id', 'status'], 'lcd_curr_program_level_status_idx');
                $table->index(['curriculum_version_id', 'status'], 'lcd_curr_program_version_status_idx');
            });
        }

        if (! Schema::hasTable('lcd_curriculum_documents')) {
            Schema::create('lcd_curriculum_documents', function (Blueprint $table): void {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('curriculum_version_id')->nullable()->constrained('lcd_curriculum_versions')->restrictOnDelete();
                $table->string('document_type', 60)->default('unknown');
                $table->string('title');
                $table->string('subtitle')->nullable();
                $table->string('original_filename');
                $table->string('disk', 80)->default('local');
                $table->string('storage_path');
                $table->string('mime_type', 160);
                $table->unsignedBigInteger('file_size');
                $table->char('sha256', 64);
                $table->text('official_url')->nullable();
                $table->string('issuing_authority', 180)->nullable();
                $table->string('decree', 160)->nullable();
                $table->string('resolution', 160)->nullable();
                $table->string('edition', 100)->nullable();
                $table->string('isbn', 80)->nullable();
                $table->unsignedSmallInteger('publication_year')->nullable();
                $table->unsignedInteger('page_count')->default(0);
                $table->foreignId('primary_subject_id')->nullable()->constrained('schedule_subjects')->restrictOnDelete();
                $table->foreignId('primary_education_level_id')->nullable()->constrained('education_levels')->restrictOnDelete();
                $table->string('extraction_status', 40)->default('uploaded');
                $table->string('review_status', 40)->default('pending');
                $table->json('classification')->nullable();
                $table->json('metadata')->nullable();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();

                $table->unique('sha256', 'lcd_curr_documents_sha_uq');
                $table->index(['document_type', 'review_status'], 'lcd_curr_documents_type_review_idx');
                $table->index(['primary_subject_id', 'primary_education_level_id'], 'lcd_curr_documents_primary_scope_idx');
            });
        }

        if (! Schema::hasTable('lcd_curriculum_document_program')) {
            Schema::create('lcd_curriculum_document_program', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('curriculum_document_id')->constrained('lcd_curriculum_documents')->restrictOnDelete();
                $table->foreignId('curriculum_program_id')->constrained('lcd_curriculum_programs')->restrictOnDelete();
                $table->string('role', 40)->default('primary');
                $table->unsignedSmallInteger('official_order')->default(1);
                $table->unsignedInteger('page_start')->nullable();
                $table->unsignedInteger('page_end')->nullable();
                $table->timestamps();

                $table->unique(['curriculum_document_id', 'curriculum_program_id', 'role'], 'lcd_curr_doc_program_role_uq');
            });
        }

        if (! Schema::hasTable('lcd_curriculum_document_pages')) {
            Schema::create('lcd_curriculum_document_pages', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('curriculum_document_id')->constrained('lcd_curriculum_documents')->restrictOnDelete();
                $table->unsignedInteger('physical_page_number');
                $table->string('printed_page_label', 40)->nullable();
                $table->longText('raw_text')->nullable();
                $table->longText('normalized_text')->nullable();
                $table->longText('layout_text')->nullable();
                $table->longText('ocr_text')->nullable();
                $table->string('extraction_method', 40)->default('text_layer');
                $table->decimal('confidence', 5, 4)->default(0);
                $table->boolean('has_tables')->default(false);
                $table->boolean('has_images')->default(false);
                $table->json('processing_warnings')->nullable();
                $table->char('content_hash', 64)->nullable();
                $table->timestamps();

                $table->unique(['curriculum_document_id', 'physical_page_number'], 'lcd_curr_doc_pages_number_uq');
                $table->index(['curriculum_document_id', 'extraction_method'], 'lcd_curr_doc_pages_method_idx');
            });
        }

        if (! Schema::hasTable('lcd_curriculum_document_sections')) {
            Schema::create('lcd_curriculum_document_sections', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('curriculum_document_id')->constrained('lcd_curriculum_documents')->restrictOnDelete();
                $table->foreignId('parent_section_id')->nullable();
                $table->foreign('parent_section_id', 'lcd_curr_sections_parent_fk')->references('id')->on('lcd_curriculum_document_sections')->restrictOnDelete();
                $table->string('section_type', 80);
                $table->string('heading')->nullable();
                $table->string('normalized_heading')->nullable();
                $table->unsignedInteger('official_order')->default(1);
                $table->unsignedInteger('page_start');
                $table->unsignedInteger('page_end');
                $table->longText('full_text')->nullable();
                $table->json('structured_data')->nullable();
                $table->decimal('confidence', 5, 4)->default(0);
                $table->string('review_status', 40)->default('pending');
                $table->timestamps();

                $table->index(['curriculum_document_id', 'section_type', 'official_order'], 'lcd_curr_sections_doc_type_order_idx');
                $table->index(['curriculum_document_id', 'page_start', 'page_end'], 'lcd_curr_sections_doc_pages_idx');
            });
        }

        if (! Schema::hasTable('lcd_curriculum_axes')) {
            Schema::create('lcd_curriculum_axes', function (Blueprint $table): void {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('schedule_subject_id')->nullable()->constrained('schedule_subjects')->restrictOnDelete();
                $table->string('canonical_name');
                $table->string('normalized_name');
                $table->text('description')->nullable();
                $table->char('identity_hash', 64)->unique();
                $table->timestamps();

                $table->index(['schedule_subject_id', 'normalized_name'], 'lcd_curr_axes_subject_name_idx');
            });
        }

        if (! Schema::hasTable('lcd_curriculum_units')) {
            Schema::create('lcd_curriculum_units', function (Blueprint $table): void {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('curriculum_program_id')->constrained('lcd_curriculum_programs')->restrictOnDelete();
                $table->string('unit_code', 80);
                $table->string('official_title')->nullable();
                $table->string('friendly_focus')->nullable();
                $table->longText('purpose')->nullable();
                $table->longText('description')->nullable();
                $table->unsignedSmallInteger('semester')->nullable();
                $table->unsignedSmallInteger('official_order');
                $table->unsignedInteger('estimated_pedagogical_hours')->nullable();
                $table->unsignedInteger('page_start')->nullable();
                $table->unsignedInteger('page_end')->nullable();
                $table->foreignId('source_document_id')->nullable()->constrained('lcd_curriculum_documents')->restrictOnDelete();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['curriculum_program_id', 'unit_code'], 'lcd_curr_units_program_code_uq');
                $table->index(['curriculum_program_id', 'official_order'], 'lcd_curr_units_program_order_idx');
            });
        }

        if (! Schema::hasTable('lcd_curriculum_skills')) {
            Schema::create('lcd_curriculum_skills', function (Blueprint $table): void {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->string('code', 80)->nullable();
                $table->string('name');
                $table->string('normalized_name');
                $table->string('category', 100)->nullable();
                $table->string('process_stage', 100)->nullable();
                $table->text('description')->nullable();
                $table->char('identity_hash', 64)->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('lcd_curriculum_skill_formulations')) {
            Schema::create('lcd_curriculum_skill_formulations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('curriculum_skill_id')->constrained('lcd_curriculum_skills')->restrictOnDelete();
                $table->foreignId('curriculum_program_id')->constrained('lcd_curriculum_programs')->restrictOnDelete();
                $table->text('official_wording');
                $table->string('official_code', 80)->nullable();
                $table->foreignId('source_document_id')->nullable()->constrained('lcd_curriculum_documents')->restrictOnDelete();
                $table->unsignedInteger('source_page')->nullable();
                $table->char('identity_hash', 64)->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('lcd_curriculum_attitudes')) {
            Schema::create('lcd_curriculum_attitudes', function (Blueprint $table): void {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->string('code', 80)->nullable();
                $table->longText('text');
                $table->text('description')->nullable();
                $table->string('oat_origin', 100)->nullable();
                $table->char('identity_hash', 64)->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('lcd_curriculum_keywords')) {
            Schema::create('lcd_curriculum_keywords', function (Blueprint $table): void {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->string('original_text');
                $table->string('normalized_text')->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('lcd_curriculum_elements')) {
            Schema::create('lcd_curriculum_elements', function (Blueprint $table): void {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->string('element_type', 80);
                $table->string('code', 100)->nullable();
                $table->string('title')->nullable();
                $table->longText('content');
                $table->longText('normalized_content')->nullable();
                $table->json('structured_data')->nullable();
                $table->char('identity_hash', 64)->unique();
                $table->timestamps();

                $table->index(['element_type', 'code'], 'lcd_curr_elements_type_code_idx');
            });
        }

        if (! Schema::hasTable('lcd_curriculum_program_axes')) {
            Schema::create('lcd_curriculum_program_axes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('curriculum_program_id')->constrained('lcd_curriculum_programs')->restrictOnDelete();
                $table->foreignId('curriculum_axis_id')->constrained('lcd_curriculum_axes')->restrictOnDelete();
                $table->unsignedSmallInteger('official_order')->default(1);
                $table->foreignId('source_document_id')->nullable()->constrained('lcd_curriculum_documents')->restrictOnDelete();
                $table->unsignedInteger('source_page')->nullable();
                $table->text('original_text')->nullable();
                $table->timestamps();

                $table->unique(['curriculum_program_id', 'curriculum_axis_id'], 'lcd_curr_program_axes_uq');
            });
        }

        if (! Schema::hasTable('lcd_curriculum_program_objectives')) {
            Schema::create('lcd_curriculum_program_objectives', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('curriculum_program_id')->constrained('lcd_curriculum_programs')->restrictOnDelete();
                $table->foreignId('learning_objective_id')->constrained('lcd_learning_objectives')->restrictOnDelete();
                $table->string('role', 40)->default('main');
                $table->unsignedSmallInteger('official_order')->default(1);
                $table->foreignId('source_document_id')->nullable()->constrained('lcd_curriculum_documents')->restrictOnDelete();
                $table->unsignedInteger('source_page')->nullable();
                $table->text('original_text')->nullable();
                $table->timestamps();

                $table->unique(['curriculum_program_id', 'learning_objective_id', 'role'], 'lcd_curr_program_objectives_uq');
            });
        }

        if (! Schema::hasTable('lcd_curriculum_unit_objectives')) {
            Schema::create('lcd_curriculum_unit_objectives', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('curriculum_unit_id')->constrained('lcd_curriculum_units')->restrictOnDelete();
                $table->foreignId('learning_objective_id')->constrained('lcd_learning_objectives')->restrictOnDelete();
                $table->string('role', 40)->default('main');
                $table->unsignedSmallInteger('official_order')->default(1);
                $table->foreignId('source_document_id')->nullable()->constrained('lcd_curriculum_documents')->restrictOnDelete();
                $table->unsignedInteger('source_page')->nullable();
                $table->text('original_text')->nullable();
                $table->timestamps();

                $table->unique(['curriculum_unit_id', 'learning_objective_id', 'role'], 'lcd_curr_unit_objectives_uq');
                $table->index(['learning_objective_id', 'curriculum_unit_id'], 'lcd_curr_unit_objective_reverse_idx');
            });
        }

        if (! Schema::hasTable('lcd_curriculum_axis_objectives')) {
            Schema::create('lcd_curriculum_axis_objectives', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('curriculum_axis_id')->constrained('lcd_curriculum_axes')->restrictOnDelete();
                $table->foreignId('learning_objective_id')->constrained('lcd_learning_objectives')->restrictOnDelete();
                $table->foreignId('curriculum_program_id')->constrained('lcd_curriculum_programs')->restrictOnDelete();
                $table->foreignId('source_document_id')->nullable()->constrained('lcd_curriculum_documents')->restrictOnDelete();
                $table->unsignedInteger('source_page')->nullable();
                $table->timestamps();

                $table->unique(['curriculum_axis_id', 'learning_objective_id', 'curriculum_program_id'], 'lcd_curr_axis_objectives_uq');
            });
        }

        if (! Schema::hasTable('lcd_curriculum_unit_skills')) {
            Schema::create('lcd_curriculum_unit_skills', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('curriculum_unit_id')->constrained('lcd_curriculum_units')->restrictOnDelete();
                $table->foreignId('curriculum_skill_id')->constrained('lcd_curriculum_skills')->restrictOnDelete();
                $table->unsignedSmallInteger('official_order')->default(1);
                $table->foreignId('source_document_id')->nullable()->constrained('lcd_curriculum_documents')->restrictOnDelete();
                $table->unsignedInteger('source_page')->nullable();
                $table->text('original_text')->nullable();
                $table->timestamps();

                $table->unique(['curriculum_unit_id', 'curriculum_skill_id'], 'lcd_curr_unit_skills_uq');
            });
        }

        if (! Schema::hasTable('lcd_curriculum_unit_attitudes')) {
            Schema::create('lcd_curriculum_unit_attitudes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('curriculum_unit_id')->constrained('lcd_curriculum_units')->restrictOnDelete();
                $table->foreignId('curriculum_attitude_id')->constrained('lcd_curriculum_attitudes')->restrictOnDelete();
                $table->unsignedSmallInteger('official_order')->default(1);
                $table->foreignId('source_document_id')->nullable()->constrained('lcd_curriculum_documents')->restrictOnDelete();
                $table->unsignedInteger('source_page')->nullable();
                $table->text('original_text')->nullable();
                $table->timestamps();

                $table->unique(['curriculum_unit_id', 'curriculum_attitude_id'], 'lcd_curr_unit_attitudes_uq');
            });
        }

        if (! Schema::hasTable('lcd_curriculum_unit_keywords')) {
            Schema::create('lcd_curriculum_unit_keywords', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('curriculum_unit_id')->constrained('lcd_curriculum_units')->restrictOnDelete();
                $table->foreignId('curriculum_keyword_id')->constrained('lcd_curriculum_keywords')->restrictOnDelete();
                $table->unsignedSmallInteger('official_order')->default(1);
                $table->foreignId('source_document_id')->nullable()->constrained('lcd_curriculum_documents')->restrictOnDelete();
                $table->unsignedInteger('source_page')->nullable();
                $table->string('original_text')->nullable();
                $table->timestamps();

                $table->unique(['curriculum_unit_id', 'curriculum_keyword_id'], 'lcd_curr_unit_keywords_uq');
            });
        }

        if (! Schema::hasTable('lcd_curriculum_element_relations')) {
            Schema::create('lcd_curriculum_element_relations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('curriculum_element_id')->constrained('lcd_curriculum_elements')->restrictOnDelete();
                $table->string('related_type', 80);
                $table->unsignedBigInteger('related_id');
                $table->string('role', 40)->default('primary');
                $table->unsignedSmallInteger('official_order')->default(1);
                $table->decimal('weight', 8, 4)->nullable();
                $table->foreignId('source_document_id')->nullable()->constrained('lcd_curriculum_documents')->restrictOnDelete();
                $table->unsignedInteger('source_page')->nullable();
                $table->longText('original_text')->nullable();
                $table->text('observation')->nullable();
                $table->timestamps();

                $table->unique(['curriculum_element_id', 'related_type', 'related_id', 'role'], 'lcd_curr_element_relations_uq');
                $table->index(['related_type', 'related_id', 'official_order'], 'lcd_curr_element_related_idx');
            });
        }

        if (! Schema::hasTable('lcd_curriculum_import_files')) {
            Schema::create('lcd_curriculum_import_files', function (Blueprint $table): void {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
                $table->foreignId('import_batch_id')->constrained('lcd_curriculum_import_batches')->restrictOnDelete();
                $table->foreignId('curriculum_document_id')->nullable()->constrained('lcd_curriculum_documents')->restrictOnDelete();
                $table->string('original_name');
                $table->string('detected_mime_type', 160);
                $table->unsignedBigInteger('size_bytes');
                $table->char('sha256', 64);
                $table->string('disk', 80)->default('local');
                $table->string('private_path');
                $table->string('status', 40)->default('uploaded');
                $table->unsignedSmallInteger('progress')->default(0);
                $table->string('current_stage', 60)->default('uploaded');
                $table->unsignedInteger('last_valid_stage_order')->default(0);
                $table->unsignedInteger('attempt_count')->default(0);
                $table->timestamp('cancel_requested_at')->nullable();
                $table->timestamp('processing_started_at')->nullable();
                $table->timestamp('processing_completed_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->text('error_summary')->nullable();
                $table->json('detected_metadata')->nullable();
                $table->json('warnings')->nullable();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['school_id', 'sha256'], 'lcd_curr_import_files_school_sha_uq');
                $table->index(['import_batch_id', 'status'], 'lcd_curr_import_files_batch_status_idx');
                $table->index(['school_id', 'academic_year_id', 'status'], 'lcd_curr_import_files_scope_status_idx');
            });
        }

        if (! Schema::hasTable('lcd_curriculum_import_candidates')) {
            Schema::create('lcd_curriculum_import_candidates', function (Blueprint $table): void {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('import_file_id')->constrained('lcd_curriculum_import_files')->restrictOnDelete();
                $table->foreignId('parent_candidate_id')->nullable();
                $table->foreign('parent_candidate_id', 'lcd_curr_candidates_parent_fk')->references('id')->on('lcd_curriculum_import_candidates')->restrictOnDelete();
                $table->string('entity_type', 80);
                $table->string('candidate_key', 160);
                $table->longText('detected_value')->nullable();
                $table->longText('normalized_value')->nullable();
                $table->json('structured_payload')->nullable();
                $table->decimal('confidence', 5, 4)->default(0);
                $table->unsignedInteger('physical_page')->nullable();
                $table->string('printed_page', 40)->nullable();
                $table->text('source_excerpt')->nullable();
                $table->string('suggested_existing_type', 100)->nullable();
                $table->unsignedBigInteger('suggested_existing_id')->nullable();
                $table->string('suggested_action', 40)->default('review');
                $table->string('review_status', 40)->default('pending');
                $table->json('warnings')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_notes')->nullable();
                $table->timestamps();

                $table->unique(['import_file_id', 'candidate_key'], 'lcd_curr_candidates_file_key_uq');
                $table->index(['import_file_id', 'entity_type', 'review_status'], 'lcd_curr_candidates_file_type_review_idx');
                $table->index(['suggested_existing_type', 'suggested_existing_id'], 'lcd_curr_candidates_existing_idx');
            });
        }

        if (! Schema::hasTable('lcd_curriculum_import_conflicts')) {
            Schema::create('lcd_curriculum_import_conflicts', function (Blueprint $table): void {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('import_file_id')->constrained('lcd_curriculum_import_files')->restrictOnDelete();
                $table->foreignId('import_candidate_id')->nullable()->constrained('lcd_curriculum_import_candidates')->restrictOnDelete();
                $table->string('conflict_type', 80);
                $table->string('severity', 30)->default('warning');
                $table->string('status', 30)->default('open');
                $table->string('title');
                $table->text('description');
                $table->json('context')->nullable();
                $table->text('resolution')->nullable();
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();

                $table->index(['import_file_id', 'severity', 'status'], 'lcd_curr_conflicts_file_severity_idx');
            });
        }

        if (! Schema::hasTable('lcd_curriculum_import_logs')) {
            Schema::create('lcd_curriculum_import_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('import_file_id')->constrained('lcd_curriculum_import_files')->restrictOnDelete();
                $table->string('stage', 60);
                $table->string('level', 20)->default('info');
                $table->string('message');
                $table->json('context')->nullable();
                $table->unsignedSmallInteger('progress')->nullable();
                $table->timestamp('occurred_at');
                $table->timestamps();

                $table->index(['import_file_id', 'id'], 'lcd_curr_import_logs_file_id_idx');
            });
        }

        if (Schema::hasTable('lcd_class_sessions')) {
            Schema::table('lcd_class_sessions', function (Blueprint $table): void {
                if (! Schema::hasColumn('lcd_class_sessions', 'curriculum_program_id')) {
                    $table->foreignId('curriculum_program_id')->nullable()->constrained('lcd_curriculum_programs')->restrictOnDelete();
                }
                if (! Schema::hasColumn('lcd_class_sessions', 'curriculum_unit_id')) {
                    $table->foreignId('curriculum_unit_id')->nullable()->constrained('lcd_curriculum_units')->restrictOnDelete();
                }
                if (! Schema::hasColumn('lcd_class_sessions', 'curriculum_axis_id')) {
                    $table->foreignId('curriculum_axis_id')->nullable()->constrained('lcd_curriculum_axes')->restrictOnDelete();
                }
            });
        }

        if (Schema::hasTable('lcd_assessments')) {
            Schema::table('lcd_assessments', function (Blueprint $table): void {
                if (! Schema::hasColumn('lcd_assessments', 'curriculum_program_id')) {
                    $table->foreignId('curriculum_program_id')->nullable()->constrained('lcd_curriculum_programs')->restrictOnDelete();
                }
                if (! Schema::hasColumn('lcd_assessments', 'curriculum_unit_id')) {
                    $table->foreignId('curriculum_unit_id')->nullable()->constrained('lcd_curriculum_units')->restrictOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        // Forward-only: los programas, fuentes, páginas y revisiones publicadas no se eliminan por rollback.
    }
};
