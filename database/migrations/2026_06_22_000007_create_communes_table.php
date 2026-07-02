<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained('regions')->cascadeOnDelete();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->timestamps();

            $table->index(['region_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communes');
    }
};
