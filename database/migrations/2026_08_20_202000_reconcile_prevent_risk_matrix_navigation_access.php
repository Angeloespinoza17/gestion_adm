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
        // Reconciliación aditiva: no se eliminan accesos, módulos ni datos operacionales.
    }
};
