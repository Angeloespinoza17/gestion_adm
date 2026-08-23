<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lcd_assessments') || ! Schema::hasColumn('lcd_assessments', 'teacher_assignment_id')) {
            return;
        }

        Schema::table('lcd_assessments', function (Blueprint $table): void {
            $table->unsignedBigInteger('teacher_assignment_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // No se vuelve a NOT NULL: podrían existir evaluaciones anuales
        // legítimas sin docente y endurecer la columna rompería el rollback.
    }
};
