<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority', 40);
            $table->string('status', 40);
            $table->string('stakeholder')->nullable();
            $table->date('due_date')->nullable();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('parent_task_id')->nullable()->constrained('tasks')->cascadeOnDelete();
            $table->boolean('auto_complete_parent_on_subtasks_done')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_user_id', 'status', 'due_date']);
            $table->index(['created_by_user_id', 'owner_user_id']);
            $table->index(['parent_task_id', 'sort_order']);
        });

        Schema::create('task_assigners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigner_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['target_user_id', 'assigner_user_id']);
            $table->index(['assigner_user_id', 'active']);
            $table->index(['target_user_id', 'active']);
        });

        Schema::create('task_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->timestamps();

            $table->index(['task_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_activity_logs');
        Schema::dropIfExists('task_assigners');
        Schema::dropIfExists('tasks');
    }
};
