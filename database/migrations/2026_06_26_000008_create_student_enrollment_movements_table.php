<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_enrollment_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_enrollment_id')->constrained('student_enrollments')->cascadeOnDelete();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('from_course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
            $table->foreignId('to_course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
            $table->string('movement_type', 50);
            $table->date('effective_date')->nullable();
            $table->string('from_status', 50)->nullable();
            $table->string('to_status', 50)->nullable();
            $table->text('notes')->nullable();
            $table->string('snapshot_year_name');
            $table->string('snapshot_from_course_display_name')->nullable();
            $table->string('snapshot_to_course_display_name')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['academic_year_id', 'movement_type'], 'stud_enroll_mov_year_type_idx');
            $table->index(['student_profile_id', 'effective_date'], 'stud_enroll_mov_student_date_idx');
            $table->index(['student_enrollment_id', 'created_at'], 'stud_enroll_mov_enroll_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_enrollment_movements');
    }
};
