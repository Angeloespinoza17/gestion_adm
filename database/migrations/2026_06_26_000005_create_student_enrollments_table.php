<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('course_section_id')->constrained('course_sections')->restrictOnDelete();
            $table->string('enrollment_status', 50)->default('matriculada');
            $table->date('enrolled_at')->nullable();
            $table->date('withdrawn_at')->nullable();
            $table->text('observations')->nullable();
            $table->string('snapshot_year_name');
            $table->string('snapshot_level_name');
            $table->string('snapshot_section_name', 20);
            $table->string('snapshot_course_display_name');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_profile_id', 'academic_year_id'], 'student_enrollments_unique_student_year');
            $table->index(['academic_year_id', 'course_section_id']);
            $table->index(['enrollment_status', 'enrolled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_enrollments');
    }
};
