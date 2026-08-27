<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_work_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('maintenance_work_orders', 'created_by_user_id')) {
                $table->foreignId('created_by_user_id')
                    ->nullable()
                    ->after('requested_by')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });

        if (! Schema::hasTable('maintenance_work_order_assignees')) {
            Schema::create('maintenance_work_order_assignees', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('maintenance_work_order_id');
                $table->unsignedBigInteger('user_id');
                $table->string('assignee_name_snapshot');
                $table->timestamps();

                $table->foreign('maintenance_work_order_id', 'mwo_assignees_work_order_fk')
                    ->references('id')
                    ->on('maintenance_work_orders')
                    ->cascadeOnDelete();
                $table->foreign('user_id', 'mwo_assignees_user_fk')
                    ->references('id')
                    ->on('users')
                    ->restrictOnDelete();

                $table->unique(
                    ['maintenance_work_order_id', 'user_id'],
                    'maintenance_work_order_assignees_unique'
                );
                $table->index(['user_id', 'created_at'], 'maintenance_work_order_assignees_user_idx');
            });
        }
    }

    public function down(): void
    {
        // Migración productiva forward-only: conserva asignaciones y auditoría de creador.
    }
};
