<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_system_module', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('system_module_id')->constrained('system_modules')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['role_id', 'system_module_id']);
            $table->index(['system_module_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_system_module');
    }
};

