<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prevent_emergency_plans', function (Blueprint $table) {
            $table->id();
            $table->string('record_type', 30);
            $table->string('title');
            $table->string('emergency_type');
            $table->date('last_updated_at');
            $table->string('responsible_name');
            $table->string('document_path')->nullable();
            $table->string('document_name')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['record_type', 'emergency_type']);
            $table->index(['active', 'last_updated_at']);
        });

        Schema::create('prevent_emergency_drills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emergency_plan_id')->constrained('prevent_emergency_plans')->cascadeOnDelete();
            $table->string('title');
            $table->string('emergency_type');
            $table->date('drill_date');
            $table->string('responsible_name');
            $table->unsignedInteger('participants_count')->default(0);
            $table->text('findings')->nullable();
            $table->text('improvements')->nullable();
            $table->string('document_path')->nullable();
            $table->string('document_name')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['emergency_plan_id', 'drill_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prevent_emergency_drills');
        Schema::dropIfExists('prevent_emergency_plans');
    }
};
