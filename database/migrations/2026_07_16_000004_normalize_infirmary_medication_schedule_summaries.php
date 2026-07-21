<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // frequency y schedule_text contienen antecedentes clínicos históricos.
        // Los nuevos campos estructurados se completan en la migración anterior,
        // pero conservamos intactos los textos registrados en producción.
    }

    public function down(): void
    {
        // No hay cambios destructivos que revertir.
    }
};
