<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_module_id')
                ->nullable()
                ->constrained('system_modules')
                ->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['active', 'sort_order']);
            $table->index(['system_module_id', 'sort_order']);
        });

        Schema::create('permission_group_permission', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_group_id')->constrained('permission_groups')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['permission_group_id', 'permission_id'], 'permission_group_permission_unique');
            $table->index(['permission_id', 'permission_group_id'], 'permission_group_permission_permission_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_group_permission');
        Schema::dropIfExists('permission_groups');
    }
};
