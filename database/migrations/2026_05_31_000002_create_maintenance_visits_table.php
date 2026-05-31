<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_dependency_id')->constrained('maintenance_dependencies');
            $table->string('responsible');
            $table->date('visit_date');
            $table->time('visit_time')->nullable();
            $table->string('visit_type')->default('Inspección');
            $table->string('status')->default('Programada');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['maintenance_dependency_id', 'visit_date']);
            $table->index(['responsible', 'visit_date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_visits');
    }
};

