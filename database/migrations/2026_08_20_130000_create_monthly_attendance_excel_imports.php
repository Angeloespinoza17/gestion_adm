<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_attendance_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->unsignedSmallInteger('school_year')->index();
            $table->unsignedTinyInteger('month')->index();
            $table->unsignedSmallInteger('version')->default(1);
            $table->boolean('is_active')->default(true)->index();
            $table->string('status', 40)->default('processing')->index();
            $table->string('source', 40)->default('monthly_excel');
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->char('checksum', 64)->index();
            $table->unsignedSmallInteger('sheet_count')->default(0);
            $table->unsignedInteger('parsed_rows')->default(0);
            $table->unsignedInteger('matched_rows')->default(0);
            $table->unsignedInteger('unmatched_rows')->default(0);
            $table->unsignedInteger('imported_records')->default(0);
            $table->unsignedInteger('preserved_manual_records')->default(0);
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('superseded_by_id')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['academic_year_id', 'month', 'version'], 'monthly_attendance_period_version_unique');
            $table->index(['academic_year_id', 'month', 'is_active'], 'monthly_attendance_active_period_idx');
        });

        Schema::create('monthly_attendance_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monthly_attendance_import_id');
            $table->foreignId('student_profile_id')->nullable()->constrained('student_profiles')->nullOnDelete();
            $table->foreignId('student_enrollment_id')->nullable()->constrained('student_enrollments')->nullOnDelete();
            $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
            $table->string('source_sheet', 120);
            $table->unsignedInteger('source_row');
            $table->string('source_course_name', 160)->nullable();
            $table->unsignedSmallInteger('list_number')->nullable();
            $table->string('given_names')->nullable();
            $table->string('paternal_surname')->nullable();
            $table->string('maternal_surname')->nullable();
            $table->string('source_name');
            $table->string('source_rut', 24)->nullable();
            $table->string('normalized_rut', 16)->nullable()->index();
            $table->unsignedSmallInteger('present_days')->default(0);
            $table->unsignedSmallInteger('absent_days')->default(0);
            $table->unsignedSmallInteger('class_days')->default(0);
            $table->decimal('attendance_rate', 6, 2)->nullable();
            $table->boolean('is_sep_priority')->default(false)->index();
            $table->boolean('is_sep_preferential')->default(false)->index();
            $table->boolean('is_pie')->default(false)->index();
            $table->json('daily_records')->nullable();
            $table->string('match_status', 40)->default('unmatched')->index();
            $table->decimal('match_confidence', 5, 2)->nullable();
            $table->json('candidates')->nullable();
            $table->text('resolution_note')->nullable();
            $table->foreignId('matched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('matched_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->unique(['monthly_attendance_import_id', 'source_sheet', 'source_row'], 'monthly_attendance_source_row_unique');
            $table->index(['monthly_attendance_import_id', 'match_status'], 'monthly_attendance_unmatched_idx');
            $table->index(['student_profile_id', 'monthly_attendance_import_id'], 'monthly_attendance_student_import_idx');
            $table->foreign('monthly_attendance_import_id', 'monthly_attendance_rows_import_fk')
                ->references('id')
                ->on('monthly_attendance_imports')
                ->restrictOnDelete();
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->foreignId('monthly_attendance_import_id')->nullable()->after('attendance_import_id')->constrained('monthly_attendance_imports')->nullOnDelete();
            $table->foreignId('monthly_attendance_import_row_id')->nullable()->after('monthly_attendance_import_id')->constrained('monthly_attendance_import_rows')->nullOnDelete();
            $table->index(['monthly_attendance_import_id', 'attendance_date'], 'attendance_record_monthly_import_idx');
        });
    }

    public function down(): void
    {
        // Intencionalmente no destructiva: los archivos, conciliaciones y asistencias importadas son trazabilidad institucional.
    }
};
