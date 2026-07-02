<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('education_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('order')->unique();
            $table->string('type', 50);
            $table->timestamps();

            $table->index(['type', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('education_levels');
    }
};
