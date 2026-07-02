<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_request_watchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_request_id')->constrained('permission_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('permission_type_watcher_id')->nullable()->constrained('permission_type_watchers')->nullOnDelete();
            $table->string('source_type', 40);
            $table->string('source_label')->nullable();
            $table->boolean('notify')->default(true);
            $table->boolean('can_view')->default(true);
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['permission_request_id', 'user_id'], 'prw_request_user_unique');
            $table->index(['user_id', 'can_view'], 'prw_user_view_idx');
            $table->index(['permission_request_id', 'notify'], 'prw_request_notify_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_request_watchers');
    }
};
