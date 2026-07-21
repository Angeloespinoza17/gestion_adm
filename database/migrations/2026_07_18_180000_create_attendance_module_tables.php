<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('course_section_id')->constrained('course_sections')->restrictOnDelete();
            $table->string('source', 40)->default('lirmi_pdf');
            $table->string('status', 40)->default('preview');
            $table->string('conflict_strategy', 30)->nullable();
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->char('checksum', 64);
            $table->unsignedInteger('parsed_students')->default(0);
            $table->unsignedInteger('matched_students')->default(0);
            $table->unsignedInteger('unmatched_students')->default(0);
            $table->unsignedInteger('imported_records')->default(0);
            $table->unsignedInteger('conflict_records')->default(0);
            $table->json('preview_payload')->nullable();
            $table->json('validation_payload')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['course_section_id', 'checksum'], 'attendance_import_course_checksum_unique');
            $table->index(['academic_year_id', 'course_section_id', 'status'], 'attendance_import_scope_idx');
        });

        Schema::create('school_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->date('date');
            $table->boolean('is_school_day')->default(true);
            $table->string('status', 40)->default('confirmed');
            $table->string('source', 40)->default('import');
            $table->string('label')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['academic_year_id', 'date'], 'school_days_year_date_unique');
            $table->index(['academic_year_id', 'is_school_day', 'date'], 'school_days_scope_idx');
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_import_id')->nullable()->constrained('attendance_imports')->nullOnDelete();
            $table->foreignId('school_day_id')->constrained('school_days')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('course_section_id')->constrained('course_sections')->restrictOnDelete();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->restrictOnDelete();
            $table->foreignId('student_enrollment_id')->nullable()->constrained('student_enrollments')->nullOnDelete();
            $table->date('attendance_date');
            $table->string('status', 20);
            $table->string('origin', 40)->default('import');
            $table->string('source_symbol', 10)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['course_section_id', 'student_profile_id', 'attendance_date'],
                'attendance_record_student_course_date_unique',
            );
            $table->index(['academic_year_id', 'course_section_id', 'attendance_date', 'status'], 'attendance_record_course_date_idx');
            $table->index(['student_profile_id', 'academic_year_id', 'attendance_date'], 'attendance_record_student_date_idx');
        });

        Schema::create('attendance_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
            $table->foreignId('student_profile_id')->nullable()->constrained('student_profiles')->nullOnDelete();
            $table->string('type', 60);
            $table->string('severity', 20)->default('warning');
            $table->string('status', 30)->default('open');
            $table->date('detected_on');
            $table->decimal('metric_value', 8, 2)->nullable();
            $table->decimal('threshold_value', 8, 2)->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['academic_year_id', 'status', 'severity'], 'attendance_alert_status_idx');
            $table->index(['student_profile_id', 'type', 'status'], 'attendance_alert_student_idx');
            $table->index(['course_section_id', 'detected_on'], 'attendance_alert_course_date_idx');
        });

        Schema::create('attendance_followups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_alert_id')->constrained('attendance_alerts')->cascadeOnDelete();
            $table->string('action_type', 60);
            $table->date('action_date');
            $table->string('status', 30)->default('completed');
            $table->text('notes');
            $table->date('next_action_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['attendance_alert_id', 'action_date'], 'attendance_followup_alert_date_idx');
        });

        Schema::create('attendance_projection_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->unique()->constrained('academic_years')->cascadeOnDelete();
            $table->decimal('monthly_unit_value', 16, 4)->default(0);
            $table->decimal('attendance_factor', 10, 6)->default(1);
            $table->unsignedSmallInteger('annual_school_days')->default(190);
            $table->char('currency', 3)->default('CLP');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_followups');
        Schema::dropIfExists('attendance_alerts');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('school_days');
        Schema::dropIfExists('attendance_projection_settings');
        Schema::dropIfExists('attendance_imports');
    }
};
