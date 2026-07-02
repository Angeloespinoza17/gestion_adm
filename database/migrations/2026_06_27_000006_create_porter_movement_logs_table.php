<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('porter_movement_logs', function (Blueprint $table) {
            $table->id();
            $table->morphs('loggable', 'pml_loggable_idx');
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 60);
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40)->nullable();
            $table->text('description')->nullable();
            $table->dateTime('performed_at');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['action', 'performed_at'], 'pml_action_performed_idx');
            $table->index(['to_status', 'performed_at'], 'pml_status_performed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('porter_movement_logs');
    }
};
