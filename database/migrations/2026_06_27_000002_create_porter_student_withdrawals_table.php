<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('porter_student_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('course_section_id')->constrained('course_sections')->restrictOnDelete();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 40)->default('registrado');
            $table->dateTime('withdrawn_at');
            $table->string('student_full_name_snapshot');
            $table->string('student_rut_snapshot', 20)->nullable();
            $table->string('academic_year_name_snapshot');
            $table->string('course_name_snapshot');
            $table->string('person_name');
            $table->string('person_rut', 20)->nullable();
            $table->string('person_relationship', 60)->nullable();
            $table->string('person_phone', 50)->nullable();
            $table->string('reason', 50);
            $table->text('observations')->nullable();
            $table->boolean('person_authorized')->default(false);
            $table->string('authorization_source', 60)->nullable();
            $table->boolean('requires_special_authorization')->default(false);
            $table->text('authorization_notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_original_name')->nullable();
            $table->string('attachment_mime_type', 120)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'withdrawn_at'], 'psw_status_withdrawn_idx');
            $table->index(['student_profile_id', 'academic_year_id'], 'psw_student_year_idx');
            $table->index(['course_section_id', 'withdrawn_at'], 'psw_course_withdrawn_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('porter_student_withdrawals');
    }
};
