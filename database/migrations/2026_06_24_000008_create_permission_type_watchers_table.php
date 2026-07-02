<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_type_watchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_type_id')->constrained('permission_types')->cascadeOnDelete();
            $table->string('target_type', 40);
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('notify')->default(true);
            $table->boolean('can_view')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['permission_type_id', 'target_type'], 'ptw_type_target_idx');
            $table->index(['role_id', 'active'], 'ptw_role_active_idx');
            $table->index(['user_id', 'active'], 'ptw_user_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_type_watchers');
    }
};
