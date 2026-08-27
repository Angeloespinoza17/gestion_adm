<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pedagogical_report_snapshots')) {
            Schema::create('pedagogical_report_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('review_id')->unique()->constrained('pedagogical_instrument_reviews', 'id', 'ped_stat_review_fk')->restrictOnDelete();
                $table->foreignId('ai_report_id')->unique()->constrained('pedagogical_instrument_ai_reports', 'id', 'ped_stat_report_fk')->restrictOnDelete();
                $table->foreignId('school_id')->constrained('lcd_schools', 'id', 'ped_stat_school_fk')->restrictOnDelete();
                $table->foreignId('academic_year_id')->constrained('academic_years', 'id', 'ped_stat_year_fk')->restrictOnDelete();
                $table->foreignId('instrument_id')->constrained('pedagogical_instruments', 'id', 'ped_stat_instrument_fk')->restrictOnDelete();
                $table->foreignId('instrument_file_id')->constrained('pedagogical_instrument_files', 'id', 'ped_stat_file_fk')->restrictOnDelete();
                $table->foreignId('owner_user_id')->constrained('users', 'id', 'ped_stat_owner_fk')->restrictOnDelete();
                $table->foreignId('subject_id')->constrained('schedule_subjects', 'id', 'ped_stat_subject_fk')->restrictOnDelete();
                $table->foreignId('reviewed_by')->nullable()->constrained('users', 'id', 'ped_stat_reviewer_fk')->nullOnDelete();
                $table->unsignedInteger('file_version');
                $table->string('decision', 40);
                $table->string('prompt_version', 50);
                $table->string('rubric_version', 50);
                $table->char('rubric_hash', 64);
                $table->unsignedSmallInteger('criteria_total')->default(0);
                $table->unsignedSmallInteger('applicable_count')->default(0);
                $table->unsignedSmallInteger('meets_count')->default(0);
                $table->unsignedSmallInteger('partially_meets_count')->default(0);
                $table->unsignedSmallInteger('does_not_meet_count')->default(0);
                $table->unsignedSmallInteger('not_evidenced_count')->default(0);
                $table->unsignedSmallInteger('not_applicable_count')->default(0);
                $table->unsignedSmallInteger('miscellaneous_count')->default(0);
                $table->decimal('compliance_percentage', 5, 2)->default(0);
                $table->decimal('evidence_coverage_percentage', 5, 2)->default(0);
                $table->dateTime('submitted_at')->nullable();
                $table->dateTime('analyzed_at')->nullable();
                $table->dateTime('reviewed_at');
                $table->timestamps();

                $table->index(['school_id', 'academic_year_id', 'reviewed_at'], 'ped_stat_school_year_date_idx');
                $table->index(['school_id', 'owner_user_id', 'reviewed_at'], 'ped_stat_owner_date_idx');
                $table->index(['school_id', 'subject_id', 'reviewed_at'], 'ped_stat_subject_date_idx');
                $table->index(['instrument_id', 'file_version'], 'ped_stat_instrument_version_idx');
                $table->index(['rubric_version', 'prompt_version'], 'ped_stat_versions_idx');
            });
        }

        if (! Schema::hasTable('pedagogical_report_criterion_snapshots')) {
            Schema::create('pedagogical_report_criterion_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('snapshot_id')->constrained('pedagogical_report_snapshots', 'id', 'ped_criterion_snapshot_fk')->cascadeOnDelete();
                $table->string('code', 20);
                $table->string('dimension', 120);
                $table->string('applicability', 191)->nullable();
                $table->text('criterion');
                $table->string('status', 30);
                $table->decimal('status_score', 4, 2)->nullable();
                $table->boolean('is_evidenced')->default(false);
                $table->unsignedInteger('page_number')->nullable();
                $table->timestamps();

                $table->unique(['snapshot_id', 'code'], 'ped_criterion_snapshot_code_uq');
                $table->index(['code', 'status', 'snapshot_id'], 'ped_criterion_code_status_idx');
                $table->index(['dimension', 'status'], 'ped_criterion_dimension_status_idx');
            });
        }

        if (! Schema::hasTable('pedagogical_report_misc_snapshots')) {
            Schema::create('pedagogical_report_misc_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('snapshot_id')->constrained('pedagogical_report_snapshots', 'id', 'ped_misc_snapshot_fk')->cascadeOnDelete();
                $table->unsignedSmallInteger('position');
                $table->string('category', 40);
                $table->string('severity', 30);
                $table->string('title', 191)->nullable();
                $table->timestamps();

                $table->unique(['snapshot_id', 'position'], 'ped_misc_snapshot_position_uq');
                $table->index(['category', 'severity', 'snapshot_id'], 'ped_misc_category_severity_idx');
            });
        }
    }

    public function down(): void
    {
        // Migración productiva forward-only: conserva los históricos estadísticos.
    }
};
