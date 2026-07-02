<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dependency_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_dependency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('activity')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('repetition_type', 20)->default('none');
            $table->date('repetition_until')->nullable();
            $table->uuid('series_uuid')->nullable();
            $table->string('status', 20)->default('pendiente');
            $table->text('observations')->nullable();
            $table->unsignedInteger('estimated_attendees')->nullable();
            $table->text('special_requirements')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['maintenance_dependency_id', 'starts_at', 'ends_at'], 'dependency_reservations_dependency_window_idx');
            $table->index(['staff_id', 'starts_at'], 'dependency_reservations_staff_start_idx');
            $table->index(['status', 'starts_at'], 'dependency_reservations_status_start_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dependency_reservations');
    }
};
