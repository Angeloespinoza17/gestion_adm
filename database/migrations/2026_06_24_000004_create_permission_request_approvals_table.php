<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_request_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_request_id')->constrained('permission_requests')->cascadeOnDelete();
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('role_or_step', 60);
            $table->string('status', 60)->default('pendiente');
            $table->text('comments')->nullable();
            $table->text('internal_comments')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();

            $table->index(['permission_request_id', 'role_or_step'], 'pra_request_step_idx');
            $table->index(['approver_user_id', 'status'], 'pra_approver_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_request_approvals');
    }
};
