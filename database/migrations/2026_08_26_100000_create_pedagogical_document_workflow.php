<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pedagogical_instruments') && ! Schema::hasColumn('pedagogical_instruments', 'workflow_status')) {
            Schema::table('pedagogical_instruments', function (Blueprint $table): void {
                $table->string('workflow_status', 40)->default('submitted')->after('status');
                $table->timestamp('submitted_at')->nullable()->after('workflow_status');
                $table->timestamp('approved_at')->nullable()->after('submitted_at');
                $table->index(['school_id', 'workflow_status', 'submitted_at'], 'ped_inst_workflow_queue_idx');
            });
        }

        if (! Schema::hasTable('pedagogical_coordinator_assignments')) {
            Schema::create('pedagogical_coordinator_assignments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('school_id')->constrained('lcd_schools', 'id', 'ped_coord_school_fk')->restrictOnDelete();
                $table->foreignId('academic_year_id')->constrained('academic_years', 'id', 'ped_coord_year_fk')->restrictOnDelete();
                $table->foreignId('coordinator_user_id')->constrained('users', 'id', 'ped_coord_user_fk')->restrictOnDelete();
                $table->string('target_type', 20);
                $table->unsignedBigInteger('target_id');
                $table->foreignId('assigned_by')->nullable()->constrained('users', 'id', 'ped_coord_assigned_by_fk')->nullOnDelete();
                $table->timestamps();

                $table->unique(['school_id', 'academic_year_id', 'coordinator_user_id', 'target_type', 'target_id'], 'ped_coord_scope_uq');
                $table->index(['school_id', 'academic_year_id', 'target_type', 'target_id'], 'ped_coord_scope_lookup_idx');
                $table->index(['coordinator_user_id', 'academic_year_id'], 'ped_coord_user_year_idx');
            });
        }

        if (! Schema::hasTable('pedagogical_guidance_documents')) {
            Schema::create('pedagogical_guidance_documents', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('school_id')->constrained('lcd_schools', 'id', 'ped_guidance_school_fk')->restrictOnDelete();
                $table->string('document_type', 40);
                $table->string('title', 191);
                $table->text('description')->nullable();
                $table->longText('content');
                $table->boolean('active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users', 'id', 'ped_guidance_created_by_fk')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users', 'id', 'ped_guidance_updated_by_fk')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['school_id', 'active', 'document_type'], 'ped_guidance_catalog_idx');
            });
        }

        if (! Schema::hasTable('pedagogical_instrument_ai_reports')) {
            Schema::create('pedagogical_instrument_ai_reports', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('instrument_id')->constrained('pedagogical_instruments', 'id', 'ped_ai_instrument_fk')->restrictOnDelete();
                $table->foreignId('instrument_file_id')->constrained('pedagogical_instrument_files', 'id', 'ped_ai_file_fk')->restrictOnDelete();
                $table->string('status', 30)->default('pending');
                $table->string('model', 100);
                $table->string('prompt_version', 50);
                $table->string('provider_response_id', 191)->nullable();
                $table->json('report')->nullable();
                $table->json('usage')->nullable();
                $table->string('error_code', 100)->nullable();
                $table->text('error_message')->nullable();
                $table->foreignId('requested_by')->nullable()->constrained('users', 'id', 'ped_ai_requested_by_fk')->nullOnDelete();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();

                $table->index(['instrument_id', 'instrument_file_id', 'created_at'], 'ped_ai_instrument_file_idx');
                $table->index(['status', 'created_at'], 'ped_ai_status_idx');
            });
        }

        if (! Schema::hasTable('pedagogical_instrument_reviews')) {
            Schema::create('pedagogical_instrument_reviews', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('instrument_id')->constrained('pedagogical_instruments', 'id', 'ped_review_instrument_fk')->restrictOnDelete();
                $table->foreignId('instrument_file_id')->constrained('pedagogical_instrument_files', 'id', 'ped_review_file_fk')->restrictOnDelete();
                $table->string('decision', 40);
                $table->text('coordinator_notes')->nullable();
                $table->foreignId('ai_report_id')->nullable()->constrained('pedagogical_instrument_ai_reports', 'id', 'ped_review_ai_report_fk')->nullOnDelete();
                $table->boolean('share_ai_report')->default(false);
                $table->foreignId('reviewed_by')->nullable()->constrained('users', 'id', 'ped_review_reviewer_fk')->nullOnDelete();
                $table->timestamp('reviewed_at');
                $table->timestamps();

                $table->index(['instrument_id', 'reviewed_at'], 'ped_review_history_idx');
                $table->index(['instrument_file_id', 'decision'], 'ped_review_file_decision_idx');
            });
        }

        if (! Schema::hasTable('pedagogical_review_guidance_document')) {
            Schema::create('pedagogical_review_guidance_document', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('review_id')->constrained('pedagogical_instrument_reviews', 'id', 'ped_review_guide_review_fk')->restrictOnDelete();
                $table->foreignId('guidance_document_id')->constrained('pedagogical_guidance_documents', 'id', 'ped_review_guide_document_fk')->restrictOnDelete();
                $table->timestamps();

                $table->unique(['review_id', 'guidance_document_id'], 'ped_review_guidance_uq');
            });
        }

        if (! Schema::hasTable('pedagogical_instrument_print_requests')) {
            Schema::create('pedagogical_instrument_print_requests', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('school_id')->constrained('lcd_schools', 'id', 'ped_print_school_fk')->restrictOnDelete();
                $table->foreignId('instrument_id')->constrained('pedagogical_instruments', 'id', 'ped_print_instrument_fk')->restrictOnDelete();
                $table->foreignId('instrument_file_id')->constrained('pedagogical_instrument_files', 'id', 'ped_print_file_fk')->restrictOnDelete();
                $table->foreignId('review_id')->unique()->constrained('pedagogical_instrument_reviews', 'id', 'ped_print_review_fk')->restrictOnDelete();
                $table->string('status', 30)->default('pending');
                $table->unsignedInteger('download_count')->default(0);
                $table->unsignedInteger('print_count')->default(0);
                $table->foreignId('last_downloaded_by')->nullable()->constrained('users', 'id', 'ped_print_downloaded_by_fk')->nullOnDelete();
                $table->foreignId('last_printed_by')->nullable()->constrained('users', 'id', 'ped_print_printed_by_fk')->nullOnDelete();
                $table->foreignId('completed_by')->nullable()->constrained('users', 'id', 'ped_print_completed_by_fk')->nullOnDelete();
                $table->timestamp('last_downloaded_at')->nullable();
                $table->timestamp('last_printed_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['school_id', 'status', 'created_at'], 'ped_print_queue_idx');
            });
        }
    }

    public function down(): void
    {
        // Migración productiva forward-only: conserva documentos, revisiones y trazabilidad.
    }
};
