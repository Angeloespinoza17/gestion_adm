<?php

namespace Database\Factories\RiskPrevention;

use App\Enums\RiskPrevention\RiskMatrixStatus;
use App\Models\RiskPrevention\RiskMatrix;
use App\Models\RiskPrevention\RiskMatrixVersion;
use App\Models\RiskPrevention\RiskMethodology;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RiskMatrixVersion> */
class RiskMatrixVersionFactory extends Factory
{
    protected $model = RiskMatrixVersion::class;

    public function definition(): array
    {
        return [
            'risk_matrix_id' => RiskMatrix::factory(),
            'methodology_id' => RiskMethodology::factory(),
            'version_number' => 1,
            'status' => RiskMatrixStatus::Draft,
            'company_name_snapshot' => 'Organización ficticia de prueba',
            'work_center_name_snapshot' => 'Centro de prueba',
            'prepared_on' => now()->toDateString(),
            'updated_on' => now()->toDateString(),
            'program_responsible_name_snapshot' => 'Responsable ficticio',
            'prepared_by' => User::factory(),
            'lock_version' => 1,
        ];
    }
}
