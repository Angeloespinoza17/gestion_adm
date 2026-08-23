<?php

namespace Database\Seeders;

use App\Services\RiskPrevention\RiskMatrixConfigurationInstaller;
use Illuminate\Database\Seeder;

class RiskMatrixModuleSeeder extends Seeder
{
    public function run(): void
    {
        app(RiskMatrixConfigurationInstaller::class)->install();
    }
}
