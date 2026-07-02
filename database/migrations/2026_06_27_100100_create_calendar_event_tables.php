<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->foreignId('process_type_id')->nullable()->constrained('calendar_process_types')->nullOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained('calendar_institutions')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('priority', 20)->default('media');
            $table->string('status', 30)->default('pendiente');
            $table->boolean('requires_submission')->default(false);
            $table->boolean('requires_payment')->default(false);
            $table->boolean('requires_signature')->default(false);
            $table->boolean('requires_review')->default(false);
            $table->boolean('requires_approval')->default(false);
            $table->boolean('is_recurring')->default(false);
            $table->boolean('auto_generate_occurrences')->default(false);
            $table->json('recurrence_rule')->nullable();
            $table->uuid('recurrence_group_id')->nullable();
            $table->foreignId('parent_event_id')->nullable()->constrained('calendar_events')->nullOnDelete();
            $table->string('event_kind', 30)->default('single');
            $table->string('stage_key', 80)->nullable();
            $table->unsignedInteger('stage_order')->nullable();
            $table->boolean('is_exception')->default(false);
            $table->string('external_url')->nullable();
            $table->longText('internal_observations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['start_date', 'end_date'], 'calendar_events_dates_idx');
            $table->index(['status', 'priority'], 'calendar_events_status_priority_idx');
            $table->index(['event_kind', 'is_recurring'], 'calendar_events_kind_recurring_idx');
            $table->index(['parent_event_id', 'start_date'], 'calendar_events_parent_start_idx');
            $table->index(['department_id', 'responsible_user_id'], 'calendar_events_department_responsible_idx');
        });

        Schema::create('calendar_event_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_event_id')->constrained('calendar_events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role_in_event', 30);
            $table->timestamps();

            $table->unique(['calendar_event_id', 'user_id', 'role_in_event'], 'calendar_event_users_unique_idx');
        });

        Schema::create('calendar_event_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_event_id')->constrained('calendar_events')->cascadeOnDelete();
            $table->string('reminder_type', 30);
            $table->integer('days_before')->nullable();
            $table->date('reminder_date')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['calendar_event_id', 'is_active'], 'calendar_event_reminders_active_idx');
        });

        Schema::create('calendar_event_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_event_id')->constrained('calendar_events')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('calendar_event_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_event_id')->nullable()->constrained('calendar_events')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 60);
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['calendar_event_id', 'created_at'], 'calendar_event_logs_event_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_event_logs');
        Schema::dropIfExists('calendar_event_attachments');
        Schema::dropIfExists('calendar_event_reminders');
        Schema::dropIfExists('calendar_event_users');
        Schema::dropIfExists('calendar_events');
    }
};
