<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->restrictOnDelete();
            $table->foreignId('from_academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('to_academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->foreignId('from_course_section_id')->constrained('course_sections')->restrictOnDelete();
            $table->foreignId('to_course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
            $table->string('promotion_status', 50);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['from_academic_year_id', 'to_academic_year_id'], 'student_promotions_years_idx');
            $table->index(['promotion_status', 'student_profile_id'], 'student_promotions_status_student_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_promotions');
    }
};
