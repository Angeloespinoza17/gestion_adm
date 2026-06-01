<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('maintenance_annual_plans')) {
            return;
        }

        try {
            Schema::create('maintenance_annual_plans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('maintenance_dependency_id')->constrained('maintenance_dependencies');

                $table->unsignedSmallInteger('planned_year');
                $table->unsignedTinyInteger('planned_month'); // 1..12

                $table->string('category');
                $table->string('responsible');
                $table->string('frequency'); // Diaria | Semanal | Mensual | Semestral | Anual
                $table->string('status'); // Programada | En ejecución | Cumplida | Vencida | Cancelada

                $table->string('title');
                $table->text('description')->nullable();
                $table->date('scheduled_date')->nullable();
                $table->date('completed_date')->nullable();
                $table->text('notes')->nullable();

                $table->timestamps();

                $table->index(['planned_year', 'planned_month']);
                $table->index(['maintenance_dependency_id', 'planned_year', 'planned_month']);
                $table->index(['responsible', 'planned_year']);
                $table->index('status');
                $table->index('frequency');
            });
        } catch (QueryException $e) {
            // Si se alcanzó a crear la tabla antes de fallar la migración, evitamos romper el deploy.
            if (($e->errorInfo[1] ?? null) === 1050 || str_contains($e->getMessage(), 'already exists')) {
                return;
            }

            throw $e;
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_annual_plans');
    }
};
