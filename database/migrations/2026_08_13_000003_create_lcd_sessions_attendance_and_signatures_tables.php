<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lcd_class_sessions')) {
            Schema::create('lcd_class_sessions', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('book_id')->constrained('lcd_books')->restrictOnDelete();
                $table->foreignId('teaching_group_id')->constrained('lcd_teaching_groups')->restrictOnDelete();
                $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
                $table->foreignId('regulatory_profile_id')->constrained('lcd_regulatory_profiles')->restrictOnDelete();
                $table->foreignId('roster_snapshot_id')->constrained('lcd_roster_snapshots')->restrictOnDelete();
                $table->foreignId('schedule_event_id')->nullable()->constrained('schedule_events')->nullOnDelete();
                $table->foreignId('school_day_block_id')->nullable()->constrained('school_day_blocks')->nullOnDelete();
                $table->foreignId('schedule_subject_id')->constrained('schedule_subjects')->restrictOnDelete();
                $table->foreignId('scheduled_teacher_id')->constrained('staff')->restrictOnDelete();
                $table->foreignId('actual_teacher_id')->constrained('staff')->restrictOnDelete();
                $table->foreignId('substitute_teacher_id')->nullable()->constrained('staff')->nullOnDelete();
                $table->date('session_date');
                $table->unsignedSmallInteger('pedagogical_hour_number')->nullable();
                $table->timestamp('scheduled_start_at')->nullable();
                $table->timestamp('scheduled_end_at')->nullable();
                $table->timestamp('actual_start_at')->nullable();
                $table->timestamp('actual_end_at')->nullable();
                $table->string('status', 40)->default('scheduled');
                $table->string('class_type', 50)->default('regular');
                $table->string('teacher_name_snapshot');
                $table->string('subject_snapshot');
                $table->string('course_snapshot');
                $table->text('objective_summary')->nullable();
                $table->longText('content_summary')->nullable();
                $table->longText('activity_summary')->nullable();
                $table->longText('observation')->nullable();
                $table->char('occurrence_key', 64);
                $table->char('canonical_hash', 64)->nullable();
                $table->unsignedInteger('revision')->default(1);
                $table->unsignedInteger('lock_version')->default(1);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['teaching_group_id', 'occurrence_key'], 'lcd_sessions_group_occurrence_uq');
                $table->index(['book_id', 'session_date', 'status'], 'lcd_sessions_book_date_status_idx');
                $table->index(['actual_teacher_id', 'session_date'], 'lcd_sessions_teacher_date_idx');
                $table->index(['schedule_subject_id', 'session_date'], 'lcd_sessions_subject_date_idx');
            });
        }

        if (! Schema::hasTable('lcd_session_topics')) {
            Schema::create('lcd_session_topics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('class_session_id')->constrained('lcd_class_sessions')->restrictOnDelete();
                $table->string('title');
                $table->text('description')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(1);
                $table->unsignedInteger('revision')->default(1);
                $table->timestamps();

                $table->index(['class_session_id', 'sort_order'], 'lcd_session_topics_order_idx');
            });
        }

        if (! Schema::hasTable('lcd_session_objectives')) {
            Schema::create('lcd_session_objectives', function (Blueprint $table) {
                $table->id();
                $table->foreignId('class_session_id')->constrained('lcd_class_sessions')->restrictOnDelete();
                $table->foreignId('learning_objective_id')->nullable()->constrained('lcd_learning_objectives')->nullOnDelete();
                $table->string('objective_code_snapshot', 100)->nullable();
                $table->text('objective_description_snapshot');
                $table->string('treatment_level', 40)->default('introduced');
                $table->unsignedTinyInteger('progress_percent')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(1);
                $table->timestamps();

                $table->index(['class_session_id', 'sort_order'], 'lcd_session_objectives_order_idx');
                $table->index(['learning_objective_id', 'treatment_level'], 'lcd_session_objectives_learning_idx');
            });
        }

        if (! Schema::hasTable('lcd_session_activities')) {
            Schema::create('lcd_session_activities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('class_session_id')->constrained('lcd_class_sessions')->restrictOnDelete();
                $table->string('activity_type', 60)->default('learning_activity');
                $table->longText('description');
                $table->unsignedSmallInteger('sort_order')->default(1);
                $table->timestamps();

                $table->index(['class_session_id', 'sort_order'], 'lcd_session_activities_order_idx');
            });
        }

        if (! Schema::hasTable('lcd_session_resources')) {
            Schema::create('lcd_session_resources', function (Blueprint $table) {
                $table->id();
                $table->foreignId('class_session_id')->constrained('lcd_class_sessions')->restrictOnDelete();
                $table->string('resource_type', 60);
                $table->string('name');
                $table->text('description')->nullable();
                $table->text('external_url')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(1);
                $table->timestamps();

                $table->index(['class_session_id', 'sort_order'], 'lcd_session_resources_order_idx');
            });
        }

        if (! Schema::hasTable('lcd_session_observations')) {
            Schema::create('lcd_session_observations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('class_session_id')->constrained('lcd_class_sessions')->restrictOnDelete();
                $table->string('visibility', 40)->default('internal');
                $table->longText('observation');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['class_session_id', 'visibility'], 'lcd_session_observations_visibility_idx');
            });
        }

        if (! Schema::hasTable('lcd_session_cancellations')) {
            Schema::create('lcd_session_cancellations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('class_session_id')->constrained('lcd_class_sessions')->restrictOnDelete();
                $table->string('reason_code', 80);
                $table->text('reason');
                $table->string('authorization_reference')->nullable();
                $table->json('evidence')->nullable();
                $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('cancelled_at');
                $table->timestamps();

                $table->unique('class_session_id', 'lcd_session_cancellations_session_uq');
            });
        }

        if (! Schema::hasTable('lcd_attendance_justifications')) {
            Schema::create('lcd_attendance_justifications', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('book_id')->constrained('lcd_books')->restrictOnDelete();
                $table->foreignId('student_profile_id')->constrained('student_profiles')->restrictOnDelete();
                $table->foreignId('student_enrollment_id')->constrained('student_enrollments')->restrictOnDelete();
                $table->string('justification_type', 60);
                $table->date('starts_on');
                $table->date('ends_on');
                $table->string('status', 40)->default('pending');
                $table->text('observation')->nullable();
                $table->json('document_metadata')->nullable();
                $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('validated_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['student_profile_id', 'starts_on', 'ends_on'], 'lcd_att_just_student_dates_idx');
                $table->index(['book_id', 'status'], 'lcd_att_just_book_status_idx');
            });
        }

        if (! Schema::hasTable('lcd_session_attendance')) {
            Schema::create('lcd_session_attendance', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('class_session_id')->constrained('lcd_class_sessions')->restrictOnDelete();
                $table->foreignId('roster_snapshot_item_id')->constrained('lcd_roster_snapshot_items')->restrictOnDelete();
                $table->foreignId('student_profile_id')->constrained('student_profiles')->restrictOnDelete();
                $table->foreignId('student_enrollment_id')->constrained('student_enrollments')->restrictOnDelete();
                $table->foreignId('attendance_justification_id')->nullable()->constrained('lcd_attendance_justifications')->restrictOnDelete();
                $table->string('status', 30);
                $table->timestamp('arrival_at')->nullable();
                $table->timestamp('departure_at')->nullable();
                $table->string('justification_status', 30)->default('not_required');
                $table->string('source', 40)->default('manual');
                $table->text('notes')->nullable();
                $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('recorded_at');
                $table->unsignedInteger('revision')->default(1);
                $table->char('record_hash', 64)->nullable();
                $table->timestamps();

                $table->unique(['class_session_id', 'student_profile_id'], 'lcd_session_attendance_session_student_uq');
                $table->index(['student_profile_id', 'status'], 'lcd_session_attendance_student_status_idx');
                $table->index(['class_session_id', 'status'], 'lcd_session_attendance_session_status_idx');
            });
        }

        if (! Schema::hasTable('lcd_daily_attendance_closures')) {
            Schema::create('lcd_daily_attendance_closures', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('book_id')->constrained('lcd_books')->restrictOnDelete();
                $table->foreignId('teaching_group_id')->constrained('lcd_teaching_groups')->restrictOnDelete();
                $table->foreignId('roster_snapshot_id')->constrained('lcd_roster_snapshots')->restrictOnDelete();
                $table->foreignId('school_day_id')->nullable()->constrained('school_days')->nullOnDelete();
                $table->date('closure_date');
                $table->string('status', 30)->default('closed');
                $table->unsignedInteger('expected_count')->default(0);
                $table->unsignedInteger('present_count')->default(0);
                $table->unsignedInteger('absent_count')->default(0);
                $table->unsignedInteger('late_count')->default(0);
                $table->unsignedInteger('not_applicable_count')->default(0);
                $table->json('anomalies')->nullable();
                $table->json('snapshot');
                $table->char('snapshot_hash', 64);
                $table->unsignedInteger('revision')->default(1);
                $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('closed_at');
                $table->timestamps();

                $table->unique(['teaching_group_id', 'closure_date', 'revision'], 'lcd_daily_closures_group_date_rev_uq');
                $table->index(['book_id', 'closure_date', 'status'], 'lcd_daily_closures_book_date_idx');
            });
        }

        if (! Schema::hasTable('lcd_monthly_attendance_closures')) {
            Schema::create('lcd_monthly_attendance_closures', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('book_id')->constrained('lcd_books')->restrictOnDelete();
                $table->foreignId('teaching_group_id')->constrained('lcd_teaching_groups')->restrictOnDelete();
                $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
                $table->unsignedTinyInteger('month');
                $table->string('status', 30)->default('closed');
                $table->unsignedInteger('school_days_count')->default(0);
                $table->unsignedBigInteger('expected_total')->default(0);
                $table->unsignedBigInteger('present_total')->default(0);
                $table->unsignedBigInteger('absent_total')->default(0);
                $table->decimal('attendance_percentage', 6, 3)->nullable();
                $table->json('snapshot');
                $table->char('snapshot_hash', 64);
                $table->unsignedInteger('revision')->default(1);
                $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('closed_at');
                $table->timestamps();

                $table->unique(['teaching_group_id', 'academic_year_id', 'month', 'revision'], 'lcd_monthly_closures_scope_rev_uq');
                $table->index(['book_id', 'academic_year_id', 'month', 'status'], 'lcd_monthly_closures_book_month_idx');
            });
        }

        if (! Schema::hasTable('lcd_attendance_reconciliations')) {
            Schema::create('lcd_attendance_reconciliations', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('book_id')->constrained('lcd_books')->restrictOnDelete();
                $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
                $table->unsignedTinyInteger('month');
                $table->string('external_source', 80);
                $table->string('status', 30)->default('pending');
                $table->json('discrepancies');
                $table->json('resolution')->nullable();
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();

                $table->index(['book_id', 'academic_year_id', 'month'], 'lcd_att_reconcile_book_month_idx');
                $table->index(['status', 'created_at'], 'lcd_att_reconcile_status_idx');
            });
        }

        if (! Schema::hasTable('lcd_teacher_signatures')) {
            Schema::create('lcd_teacher_signatures', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('regulatory_profile_id')->constrained('lcd_regulatory_profiles')->restrictOnDelete();
                $table->foreignId('teacher_assignment_id')->constrained('lcd_teacher_assignments')->restrictOnDelete();
                $table->foreignId('staff_id')->constrained('staff')->restrictOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('signable_type', 120);
                $table->unsignedBigInteger('signable_id');
                $table->unsignedInteger('signable_revision');
                $table->string('verifier_provider', 80);
                $table->string('verifier_transaction_id', 160)->nullable();
                $table->string('correlation_id', 100)->nullable();
                $table->string('status', 40)->default('pending');
                $table->timestamp('verified_at')->nullable();
                $table->char('payload_hash', 64);
                $table->char('signature_hash', 64)->nullable();
                $table->string('signature_algorithm', 80)->nullable();
                $table->string('verifier_response_code', 80)->nullable();
                $table->char('verifier_response_hash', 64)->nullable();
                $table->text('signer_identifier_encrypted')->nullable();
                $table->text('ip_address_encrypted')->nullable();
                $table->char('user_agent_hash', 64)->nullable();
                $table->timestamps();

                $table->unique(['signable_type', 'signable_id', 'signable_revision', 'staff_id'], 'lcd_teacher_signatures_record_staff_uq');
                $table->index(['school_id', 'status', 'created_at'], 'lcd_teacher_signatures_school_status_idx');
            });
        }

        if (! Schema::hasTable('lcd_signature_attempts')) {
            Schema::create('lcd_signature_attempts', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('teacher_signature_id')->nullable()->constrained('lcd_teacher_signatures')->restrictOnDelete();
                $table->foreignId('staff_id')->constrained('staff')->restrictOnDelete();
                $table->string('signable_type', 120);
                $table->unsignedBigInteger('signable_id');
                $table->string('verifier_provider', 80);
                $table->string('correlation_id', 100)->nullable();
                $table->string('result', 40);
                $table->string('failure_code', 100)->nullable();
                $table->text('failure_detail_sanitized')->nullable();
                $table->text('ip_address_encrypted')->nullable();
                $table->char('user_agent_hash', 64)->nullable();
                $table->dateTime('attempted_at');
                $table->timestamp('created_at')->nullable();

                $table->index(['signable_type', 'signable_id', 'attempted_at'], 'lcd_signature_attempts_record_idx');
                $table->index(['staff_id', 'result', 'attempted_at'], 'lcd_signature_attempts_staff_idx');
            });
        }
    }

    public function down(): void
    {
        // Forward-only migration: production records must never be removed by rollback.
    }
};
