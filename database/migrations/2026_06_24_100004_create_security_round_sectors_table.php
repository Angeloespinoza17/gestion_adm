<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_round_sectors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('security_round_id')->constrained('security_rounds')->cascadeOnDelete();
            $table->foreignId('maintenance_dependency_id')->nullable()->constrained('maintenance_dependencies')->nullOnDelete();
            $table->string('sector_name');
            $table->string('sector_state', 30)->default('sin_novedad');
            $table->text('observations')->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_round_sectors');
    }
};
