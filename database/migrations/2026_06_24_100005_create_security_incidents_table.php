<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('security_shift_id')->constrained('security_shifts')->cascadeOnDelete();
            $table->foreignId('security_round_id')->nullable()->constrained('security_rounds')->nullOnDelete();
            $table->foreignId('security_round_sector_id')->nullable()->constrained('security_round_sectors')->nullOnDelete();
            $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('status_id')->constrained('security_incident_statuses');
            $table->foreignId('maintenance_dependency_id')->nullable()->constrained('maintenance_dependencies')->nullOnDelete();
            $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items')->nullOnDelete();
            $table->foreignId('current_responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority', 20)->default('baja');
            $table->string('title');
            $table->text('description');
            $table->string('sector_name')->nullable();
            $table->boolean('requires_immediate_attention')->default(false);
            $table->dateTime('response_due_at')->nullable();
            $table->dateTime('responded_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->dateTime('alert_sent_at')->nullable();
            $table->text('response_summary')->nullable();
            $table->text('closure_evidence_notes')->nullable();
            $table->timestamps();

            $table->index(['priority', 'status_id']);
            $table->index(['current_responsible_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_incidents');
    }
};
