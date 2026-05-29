<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_dependencies', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('distribution')->nullable();
            $table->string('sector')->nullable();
            $table->string('zone')->nullable();
            $table->string('usage')->nullable();
            $table->string('distribution_code')->nullable();
            $table->string('floor_code')->nullable();
            $table->string('dependency_code')->nullable();
            $table->unsignedInteger('numbering')->nullable();
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_dependencies');
    }
};
