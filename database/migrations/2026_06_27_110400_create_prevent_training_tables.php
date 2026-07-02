<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prevent_trainings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('training_type', 30);
            $table->date('training_date');
            $table->string('modality');
            $table->string('evidence_path')->nullable();
            $table->string('evidence_name')->nullable();
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['training_type', 'training_date'], 'prt_trainings_type_date_idx');
        });

        Schema::create('prevent_training_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_id')->constrained('prevent_trainings')->cascadeOnDelete();
            $table->string('employee_name');
            $table->string('compliance_status', 30)->default('pendiente');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['training_id', 'compliance_status'], 'prt_train_part_status_idx');
            $table->index('employee_name', 'prt_train_part_employee_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prevent_training_participants');
        Schema::dropIfExists('prevent_trainings');
    }
};
