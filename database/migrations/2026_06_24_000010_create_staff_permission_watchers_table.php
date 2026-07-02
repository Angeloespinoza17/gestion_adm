<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_permission_watchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->string('target_type', 40);
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('notify')->default(true);
            $table->boolean('can_view')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['staff_id', 'target_type'], 'spw_staff_target_idx');
            $table->index(['role_id', 'active'], 'spw_role_active_idx');
            $table->index(['user_id', 'active'], 'spw_user_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_permission_watchers');
    }
};
