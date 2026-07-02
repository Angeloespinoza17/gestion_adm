<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('maintenance_dependency_id')->nullable()->constrained('maintenance_dependencies')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('started_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('scheduled_start_at');
            $table->dateTime('scheduled_end_at');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->string('status', 30)->default('programado');
            $table->string('coverage_label');
            $table->text('general_observations')->nullable();
            $table->text('closing_observations')->nullable();
            $table->timestamps();

            $table->index(['staff_id', 'status']);
            $table->index(['scheduled_start_at', 'scheduled_end_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_shifts');
    }
};
