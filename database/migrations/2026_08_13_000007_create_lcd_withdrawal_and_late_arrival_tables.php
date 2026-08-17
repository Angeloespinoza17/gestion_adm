<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lcd_early_withdrawals')) {
            Schema::create('lcd_early_withdrawals', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('book_id')->constrained('lcd_books')->restrictOnDelete();
                $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
                $table->foreignId('teaching_group_id')->constrained('lcd_teaching_groups')->restrictOnDelete();
                $table->foreignId('porter_student_withdrawal_id')->nullable()->constrained('porter_student_withdrawals')->restrictOnDelete();
                $table->foreignId('student_profile_id')->constrained('student_profiles')->restrictOnDelete();
                $table->foreignId('student_enrollment_id')->constrained('student_enrollments')->restrictOnDelete();
                $table->string('status', 40)->default('recorded');
                $table->dateTime('occurred_at');
                $table->timestamp('returned_at')->nullable();
                $table->string('student_name_snapshot');
                $table->string('course_snapshot');
                $table->longText('withdrawal_snapshot_encrypted');
                $table->char('withdrawal_snapshot_hash', 64);
                $table->longText('return_snapshot_encrypted')->nullable();
                $table->char('return_snapshot_hash', 64)->nullable();
                $table->unsignedInteger('revision')->default(1);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique('porter_student_withdrawal_id', 'lcd_early_withdrawals_porter_uq');
                $table->index(['book_id', 'occurred_at', 'status'], 'lcd_early_withdrawals_book_date_idx');
                $table->index(['student_profile_id', 'occurred_at'], 'lcd_early_withdrawals_student_idx');
            });
        }

        if (! Schema::hasTable('lcd_late_arrival_periods')) {
            Schema::create('lcd_late_arrival_periods', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('book_id')->constrained('lcd_books')->restrictOnDelete();
                $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
                $table->foreignId('teaching_group_id')->constrained('lcd_teaching_groups')->restrictOnDelete();
                $table->string('name');
                $table->date('starts_on');
                $table->date('ends_on');
                $table->json('policy_snapshot');
                $table->char('policy_snapshot_hash', 64);
                $table->string('status', 30)->default('open');
                $table->unsignedInteger('revision')->default(1);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['book_id', 'starts_on', 'ends_on'], 'lcd_late_periods_book_dates_uq');
                $table->index(['teaching_group_id', 'status'], 'lcd_late_periods_group_status_idx');
            });
        }

        if (! Schema::hasTable('lcd_late_arrivals')) {
            Schema::create('lcd_late_arrivals', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('school_id')->constrained('lcd_schools')->restrictOnDelete();
                $table->foreignId('book_id')->constrained('lcd_books')->restrictOnDelete();
                $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
                $table->foreignId('teaching_group_id')->constrained('lcd_teaching_groups')->restrictOnDelete();
                $table->foreignId('late_arrival_period_id')->nullable()->constrained('lcd_late_arrival_periods')->restrictOnDelete();
                $table->foreignId('class_session_id')->nullable()->constrained('lcd_class_sessions')->restrictOnDelete();
                $table->foreignId('session_attendance_id')->nullable()->constrained('lcd_session_attendance')->restrictOnDelete();
                $table->foreignId('student_profile_id')->constrained('student_profiles')->restrictOnDelete();
                $table->foreignId('student_enrollment_id')->constrained('student_enrollments')->restrictOnDelete();
                $table->dateTime('arrival_at');
                $table->unsignedSmallInteger('minutes_late')->nullable();
                $table->string('status', 40)->default('recorded');
                $table->string('source', 60)->default('manual');
                $table->string('student_name_snapshot');
                $table->string('course_snapshot');
                $table->longText('justification_encrypted')->nullable();
                $table->json('evidence')->nullable();
                $table->char('record_hash', 64);
                $table->unsignedInteger('revision')->default(1);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['book_id', 'student_profile_id', 'arrival_at'], 'lcd_late_arrivals_book_student_time_uq');
                $table->index(['teaching_group_id', 'arrival_at'], 'lcd_late_arrivals_group_time_idx');
                $table->index(['student_profile_id', 'arrival_at'], 'lcd_late_arrivals_student_time_idx');
            });
        }
    }

    public function down(): void
    {
        // Forward-only migration: production records must never be removed by rollback.
    }
};
