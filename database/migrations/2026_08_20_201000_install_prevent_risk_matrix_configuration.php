<?php

use App\Services\RiskPrevention\RiskMatrixConfigurationInstaller;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(RiskMatrixConfigurationInstaller::class)->install();
    }

    public function down(): void
    {
        // Instalación aditiva: no se eliminan permisos, catálogos ni accesos que puedan estar en uso.
    }
};
