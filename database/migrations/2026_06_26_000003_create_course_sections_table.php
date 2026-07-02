<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('education_level_id')->constrained('education_levels')->restrictOnDelete();
            $table->string('section_name', 20);
            $table->string('display_name');
            $table->unsignedInteger('capacity')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['academic_year_id', 'education_level_id', 'section_name'], 'course_sections_unique_section_per_year');
            $table->index(['academic_year_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_sections');
    }
};
