<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_visit_planning_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('idempotency_key')->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 24)->default('confirmed');
            $table->unsignedInteger('proposed_count')->default(0);
            $table->unsignedInteger('confirmed_count')->default(0);
            $table->json('configuration');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['period_start', 'period_end'], 'maintenance_visit_batches_period_idx');
            $table->index(['created_by_user_id', 'created_at'], 'maintenance_visit_batches_actor_idx');
        });

        Schema::table('maintenance_visits', function (Blueprint $table) {
            $table->foreignId('responsible_staff_id')
                ->nullable()
                ->after('responsible')
                ->constrained('staff')
                ->nullOnDelete();
            $table->foreignId('planning_batch_id')
                ->nullable()
                ->after('responsible_staff_id')
                ->constrained('maintenance_visit_planning_batches')
                ->nullOnDelete();

            $table->index(
                ['responsible_staff_id', 'visit_date', 'visit_time'],
                'maintenance_visits_staff_schedule_idx'
            );
            $table->index(
                ['planning_batch_id', 'visit_date'],
                'maintenance_visits_batch_date_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_visits', function (Blueprint $table) {
            $table->dropIndex('maintenance_visits_staff_schedule_idx');
            $table->dropIndex('maintenance_visits_batch_date_idx');
            $table->dropConstrainedForeignId('planning_batch_id');
            $table->dropConstrainedForeignId('responsible_staff_id');
        });

        Schema::dropIfExists('maintenance_visit_planning_batches');
    }
};
