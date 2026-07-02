<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_request_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_request_id')->constrained('permission_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100);
            $table->string('old_status', 60)->nullable();
            $table->string('new_status', 60)->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['permission_request_id', 'created_at'], 'prl_request_created_idx');
            $table->index(['action', 'created_at'], 'prl_action_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_request_logs');
    }
};
