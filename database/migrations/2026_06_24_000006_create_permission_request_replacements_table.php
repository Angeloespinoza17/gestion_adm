<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_request_replacements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_request_id')->constrained('permission_requests')->cascadeOnDelete();
            $table->foreignId('replaced_staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('replacement_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->string('course_name')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_name')->nullable();
            $table->string('dependency_name')->nullable();
            $table->text('schedule_detail')->nullable();
            $table->dateTime('start_datetime');
            $table->dateTime('end_datetime');
            $table->string('status', 60)->default('pendiente');
            $table->text('observations')->nullable();
            $table->timestamps();

            $table->index(['permission_request_id', 'status'], 'prr_request_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_request_replacements');
    }
};
