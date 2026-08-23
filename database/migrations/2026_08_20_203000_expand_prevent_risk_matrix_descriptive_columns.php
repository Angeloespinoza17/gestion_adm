<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prevent_risk_matrix_tasks', function (Blueprint $table) {
            $table->text('activity_name')->change();
            $table->text('task_name')->change();
            $table->text('job_position_text')->nullable()->change();
            $table->text('specific_location')->nullable()->change();
        });

        Schema::table('prevent_risk_entries', function (Blueprint $table) {
            $table->text('specific_risk_name')->change();
        });

        Schema::table('prevent_risk_controls', function (Blueprint $table) {
            $table->text('responsible_text')->nullable()->change();
        });
    }

    public function down(): void
    {
        // No se reducen columnas: volver a VARCHAR podría truncar matrices reales.
    }
};
