<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('porter_received_items', function (Blueprint $table) {
            $table->id();
            $table->string('recipient_type', 30)->default('student');
            $table->string('recipient_label')->nullable();
            $table->foreignId('student_profile_id')->nullable()->constrained('student_profiles')->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 40)->default('recibido_en_porteria');
            $table->dateTime('received_at');
            $table->dateTime('delivered_at')->nullable();
            $table->string('received_from_name');
            $table->string('received_from_rut', 20)->nullable();
            $table->string('received_from_phone', 50)->nullable();
            $table->string('item_type', 40);
            $table->text('description');
            $table->text('observations')->nullable();
            $table->string('delivered_to_name')->nullable();
            $table->string('delivered_to_rut', 20)->nullable();
            $table->text('delivery_observations')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_original_name')->nullable();
            $table->string('attachment_mime_type', 120)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'received_at'], 'pri_status_received_idx');
            $table->index(['recipient_type', 'student_profile_id'], 'pri_recipient_student_idx');
            $table->index(['department_id', 'status'], 'pri_department_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('porter_received_items');
    }
};
