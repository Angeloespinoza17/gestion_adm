<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lcd_amendment_requests')) {
            Schema::create('lcd_amendment_requests', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('book_id')->nullable()->constrained('lcd_books')->restrictOnDelete();
                $table->string('amendable_type', 120);
                $table->unsignedBigInteger('amendable_id');
                $table->unsignedInteger('original_revision');
                $table->string('section', 100)->nullable();
                $table->string('field', 120)->nullable();
                $table->json('before_snapshot')->nullable();
                $table->json('proposed_snapshot');
                $table->text('reason');
                $table->string('status', 40)->default('requested');
                $table->boolean('requires_signature')->default(true);
                $table->json('evidence')->nullable();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('requested_at');
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_note')->nullable();
                $table->unsignedInteger('applied_revision')->nullable();
                $table->timestamp('applied_at')->nullable();
                $table->timestamps();

                $table->index(['amendable_type', 'amendable_id', 'status'], 'lcd_amendments_record_status_idx');
                $table->index(['school_id', 'book_id', 'status'], 'lcd_amendments_scope_status_idx');
            });
        }

        if (! Schema::hasTable('lcd_amendment_approvals')) {
            Schema::create('lcd_amendment_approvals', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('amendment_request_id')->constrained('lcd_amendment_requests')->restrictOnDelete();
                $table->unsignedSmallInteger('approval_order')->default(1);
                $table->string('required_role', 100);
                $table->string('decision', 30)->default('pending');
                $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('decided_at')->nullable();
                $table->text('comment')->nullable();
                $table->char('decision_hash', 64)->nullable();
                $table->timestamps();

                $table->unique(['amendment_request_id', 'approval_order'], 'lcd_amendment_approvals_order_uq');
                $table->index(['decision', 'required_role'], 'lcd_amendment_approvals_pending_idx');
            });
        }

        if (! Schema::hasTable('lcd_record_revisions')) {
            Schema::create('lcd_record_revisions', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('amendment_request_id')->nullable()->constrained('lcd_amendment_requests')->restrictOnDelete();
                $table->foreignId('previous_revision_id')->nullable()->constrained('lcd_record_revisions')->restrictOnDelete();
                $table->string('revisable_type', 120);
                $table->unsignedBigInteger('revisable_id');
                $table->unsignedInteger('revision');
                $table->json('payload');
                $table->char('payload_hash', 64);
                $table->text('reason')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('created_at')->nullable();

                $table->unique(['revisable_type', 'revisable_id', 'revision'], 'lcd_record_revisions_record_rev_uq');
                $table->index(['school_id', 'created_at'], 'lcd_record_revisions_school_date_idx');
            });
        }

        if (! Schema::hasTable('lcd_closure_reopenings')) {
            Schema::create('lcd_closure_reopenings', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('book_id')->nullable()->constrained('lcd_books')->restrictOnDelete();
                $table->string('closable_type', 120);
                $table->unsignedBigInteger('closable_id');
                $table->unsignedInteger('closed_revision');
                $table->string('scope', 80);
                $table->string('status', 40)->default('pending');
                $table->text('reason');
                $table->char('original_snapshot_hash', 64);
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('requested_at');
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('reopened_at')->nullable();
                $table->timestamp('reclosed_at')->nullable();
                $table->unsignedInteger('replacement_revision')->nullable();
                $table->timestamps();

                $table->index(['closable_type', 'closable_id', 'status'], 'lcd_reopenings_record_status_idx');
                $table->index(['school_id', 'book_id', 'status'], 'lcd_reopenings_scope_status_idx');
            });
        }

        if (! Schema::hasTable('lcd_grading_schemes')) {
            Schema::create('lcd_grading_schemes', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('regulatory_profile_id')->nullable()->constrained('lcd_regulatory_profiles')->restrictOnDelete();
                $table->string('code', 80);
                $table->string('name');
                $table->string('version', 40);
                $table->string('scale_type', 40)->default('numeric');
                $table->decimal('minimum_value', 10, 4)->nullable();
                $table->decimal('maximum_value', 10, 4)->nullable();
                $table->decimal('passing_value', 10, 4)->nullable();
                $table->unsignedTinyInteger('decimal_places')->default(1);
                $table->string('rounding_mode', 30)->default('half_up');
                $table->json('equivalences')->nullable();
                $table->json('rules')->nullable();
                $table->date('valid_from')->nullable();
                $table->date('valid_to')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->unique(['school_id', 'code', 'version'], 'lcd_grading_schemes_school_code_version_uq');
                $table->index(['school_id', 'active'], 'lcd_grading_schemes_school_active_idx');
            });
        }

        if (! Schema::hasTable('lcd_assessment_periods')) {
            Schema::create('lcd_assessment_periods', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('book_id')->constrained('lcd_books')->restrictOnDelete();
                $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
                $table->foreignId('book_period_id')->nullable()->constrained('lcd_book_periods')->restrictOnDelete();
                $table->string('code', 60);
                $table->string('name');
                $table->string('type', 40)->default('term');
                $table->date('starts_on');
                $table->date('ends_on');
                $table->decimal('weight', 7, 4)->nullable();
                $table->string('status', 30)->default('open');
                $table->unsignedInteger('revision')->default(1);
                $table->timestamp('closed_at')->nullable();
                $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['book_id', 'code'], 'lcd_assessment_periods_book_code_uq');
                $table->index(['book_id', 'starts_on', 'ends_on'], 'lcd_assessment_periods_date_idx');
            });
        }

        if (! Schema::hasTable('lcd_assessments')) {
            Schema::create('lcd_assessments', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('book_id')->constrained('lcd_books')->restrictOnDelete();
                $table->foreignId('teaching_group_id')->constrained('lcd_teaching_groups')->restrictOnDelete();
                $table->foreignId('assessment_period_id')->constrained('lcd_assessment_periods')->restrictOnDelete();
                $table->foreignId('grading_scheme_id')->constrained('lcd_grading_schemes')->restrictOnDelete();
                $table->foreignId('schedule_subject_id')->constrained('schedule_subjects')->restrictOnDelete();
                $table->foreignId('teacher_assignment_id')->constrained('lcd_teacher_assignments')->restrictOnDelete();
                $table->string('code', 80)->nullable();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('assessment_type', 60);
                $table->date('assessment_date');
                $table->date('results_due_on')->nullable();
                $table->decimal('weight', 7, 4)->default(1);
                $table->decimal('maximum_score', 10, 4)->nullable();
                $table->json('instrument_metadata')->nullable();
                $table->string('status', 40)->default('draft');
                $table->unsignedInteger('revision')->default(1);
                $table->unsignedInteger('lock_version')->default(1);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['teaching_group_id', 'assessment_period_id', 'status'], 'lcd_assessments_group_period_status_idx');
                $table->index(['book_id', 'schedule_subject_id', 'assessment_date'], 'lcd_assessments_book_subject_date_idx');
            });
        }

        if (! Schema::hasTable('lcd_assessment_objectives')) {
            Schema::create('lcd_assessment_objectives', function (Blueprint $table) {
                $table->id();
                $table->foreignId('assessment_id')->constrained('lcd_assessments')->restrictOnDelete();
                $table->foreignId('learning_objective_id')->nullable()->constrained('lcd_learning_objectives')->nullOnDelete();
                $table->string('objective_code_snapshot', 100)->nullable();
                $table->text('objective_description_snapshot');
                $table->decimal('weight', 7, 4)->nullable();
                $table->timestamps();

                $table->index(['assessment_id', 'learning_objective_id'], 'lcd_assessment_objectives_link_idx');
            });
        }

        if (! Schema::hasTable('lcd_student_results')) {
            Schema::create('lcd_student_results', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('assessment_id')->constrained('lcd_assessments')->restrictOnDelete();
                $table->foreignId('student_profile_id')->constrained('student_profiles')->restrictOnDelete();
                $table->foreignId('student_enrollment_id')->constrained('student_enrollments')->restrictOnDelete();
                $table->foreignId('enrollment_link_id')->constrained('lcd_enrollment_links')->restrictOnDelete();
                $table->string('status', 40)->default('pending');
                $table->decimal('raw_score', 10, 4)->nullable();
                $table->decimal('numeric_value', 10, 4)->nullable();
                $table->string('qualitative_value', 120)->nullable();
                $table->decimal('normalized_percentage', 7, 4)->nullable();
                $table->boolean('absent')->default(false);
                $table->boolean('exempt')->default(false);
                $table->text('observation')->nullable();
                $table->unsignedInteger('revision')->default(1);
                $table->char('record_hash', 64)->nullable();
                $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('recorded_at')->nullable();
                $table->timestamps();

                $table->unique(['assessment_id', 'student_profile_id'], 'lcd_student_results_assessment_student_uq');
                $table->index(['student_profile_id', 'status'], 'lcd_student_results_student_status_idx');
                $table->index(['assessment_id', 'status'], 'lcd_student_results_assessment_status_idx');
            });
        }

        if (! Schema::hasTable('lcd_grade_closures')) {
            Schema::create('lcd_grade_closures', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('book_id')->constrained('lcd_books')->restrictOnDelete();
                $table->foreignId('teaching_group_id')->constrained('lcd_teaching_groups')->restrictOnDelete();
                $table->foreignId('assessment_period_id')->constrained('lcd_assessment_periods')->restrictOnDelete();
                $table->foreignId('schedule_subject_id')->nullable()->constrained('schedule_subjects')->restrictOnDelete();
                $table->foreignId('student_profile_id')->nullable()->constrained('student_profiles')->restrictOnDelete();
                $table->string('scope', 60);
                $table->string('status', 30)->default('closed');
                $table->json('snapshot');
                $table->char('snapshot_hash', 64);
                $table->unsignedInteger('revision')->default(1);
                $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('closed_at');
                $table->timestamps();

                $table->index(['book_id', 'assessment_period_id', 'status'], 'lcd_grade_closures_book_period_idx');
                $table->index(['teaching_group_id', 'schedule_subject_id', 'student_profile_id'], 'lcd_grade_closures_scope_idx');
            });
        }
    }

    public function down(): void
    {
        // Forward-only migration: production records must never be removed by rollback.
    }
};
