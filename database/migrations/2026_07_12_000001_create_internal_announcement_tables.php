<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('category', 80)->nullable();
            $table->string('priority', 20)->default('normal');
            $table->string('status', 20)->default('draft');
            $table->boolean('pinned')->default(false);
            $table->boolean('audience_all')->default(false);
            $table->boolean('requires_ack')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'published_at', 'expires_at']);
            $table->index(['priority', 'pinned']);
        });

        Schema::create('internal_announcement_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internal_announcement_id')->constrained('internal_announcements')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['internal_announcement_id', 'role_id'], 'internal_announcement_role_unique');
        });

        Schema::create('internal_announcement_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internal_announcement_id')->constrained('internal_announcements')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->unique(['internal_announcement_id', 'user_id'], 'internal_announcement_user_read_unique');
            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'acknowledged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_announcement_reads');
        Schema::dropIfExists('internal_announcement_role');
        Schema::dropIfExists('internal_announcements');
    }
};
