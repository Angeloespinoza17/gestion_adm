<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('porter_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visited_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('visited_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('en_curso');
            $table->dateTime('entered_at');
            $table->dateTime('exited_at')->nullable();
            $table->string('visitor_name');
            $table->string('visitor_rut', 20)->nullable();
            $table->string('purpose');
            $table->string('visited_person_label')->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->text('observations')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'entered_at'], 'pv_status_entered_idx');
            $table->index(['visited_staff_id', 'status'], 'pv_staff_status_idx');
            $table->index(['visited_department_id', 'status'], 'pv_dept_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('porter_visits');
    }
};
