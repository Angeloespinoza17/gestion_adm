<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('direct_manager_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('permission_type_id')->constrained('permission_types');
            $table->string('cargo_name')->nullable();
            $table->string('direct_manager_name')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->decimal('duration_hours', 8, 2)->nullable();
            $table->decimal('duration_days', 8, 2)->nullable();
            $table->string('duration_label', 191)->nullable();
            $table->boolean('is_full_day')->default(true);
            $table->boolean('is_half_day')->default(false);
            $table->boolean('with_pay')->nullable();
            $table->boolean('affects_salary')->default(false);
            $table->boolean('affects_attendance')->default(true);
            $table->boolean('requires_replacement')->default(false);
            $table->string('reason');
            $table->text('description')->nullable();
            $table->text('employee_observations')->nullable();
            $table->text('visible_observations')->nullable();
            $table->text('internal_observations')->nullable();
            $table->string('status', 60)->default('borrador');
            $table->string('current_step', 60)->nullable();
            $table->boolean('urgency')->default(false);
            $table->boolean('retroactive')->default(false);
            $table->string('attendance_status', 60)->default('pendiente');
            $table->string('payroll_status', 60)->default('no_aplica');
            $table->decimal('salary_discount_hours', 8, 2)->nullable();
            $table->decimal('salary_discount_days', 8, 2)->nullable();
            $table->boolean('requires_regularization')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->index(['staff_id', 'status']);
            $table->index(['permission_type_id', 'status']);
            $table->index(['start_date', 'end_date']);
            $table->index(['status', 'current_step']);
            $table->index(['with_pay', 'payroll_status']);
            $table->index(['affects_salary', 'affects_attendance']);
            $table->index(['direct_manager_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_requests');
    }
};
