<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('psychology_coordination_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('psychology_cases')->restrictOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained('psychology_activities')->nullOnDelete();
            $table->foreignId('requester_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('recipient_user_id')->constrained('users')->restrictOnDelete();
            $table->string('coordination_type', 60);
            $table->string('subject', 191);
            $table->text('request_message');
            $table->date('requested_for')->nullable();
            $table->string('status', 30)->default('pending');
            $table->text('response_message')->nullable();
            $table->dateTime('responded_at')->nullable();
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['recipient_user_id', 'status', 'requested_for'], 'psychology_coordination_recipient_status_idx');
            $table->index(['requester_user_id', 'status'], 'psychology_coordination_requester_status_idx');
            $table->index(['case_id', 'created_at'], 'psychology_coordination_case_created_idx');
        });
    }

    public function down(): void
    {
        // Se conserva la tabla para no eliminar solicitudes ni respuestas históricas.
    }
};
