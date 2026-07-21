<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('maintenance_dependency_approvers')) {
            Schema::create('maintenance_dependency_approvers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('maintenance_dependency_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamps();

                $table->unique(
                    ['maintenance_dependency_id', 'user_id'],
                    'maintenance_dependency_approvers_unique'
                );
                $table->foreign('maintenance_dependency_id', 'md_approver_dep_fk')
                    ->references('id')
                    ->on('maintenance_dependencies')
                    ->cascadeOnDelete();
                $table->foreign('user_id', 'md_approver_user_fk')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('dependency_reservation_collaborators')) {
            Schema::create('dependency_reservation_collaborators', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('dependency_reservation_id');
                $table->unsignedBigInteger('staff_id')->nullable();
                $table->string('external_email')->nullable();
                $table->timestamps();

                $table->index(
                    ['dependency_reservation_id', 'staff_id'],
                    'dependency_reservation_collaborators_staff_idx'
                );
                $table->foreign('dependency_reservation_id', 'drc_reservation_fk')
                    ->references('id')
                    ->on('dependency_reservations')
                    ->cascadeOnDelete();
                $table->foreign('staff_id', 'drc_staff_fk')
                    ->references('id')
                    ->on('staff')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dependency_reservation_collaborators');
        Schema::dropIfExists('maintenance_dependency_approvers');
    }
};
