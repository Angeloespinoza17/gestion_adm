<?php

namespace Database\Factories\RiskPrevention;

use App\Models\RiskPrevention\RiskMethodology;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RiskMethodology> */
class RiskMethodologyFactory extends Factory
{
    protected $model = RiskMethodology::class;

    public function definition(): array
    {
        return [
            'code' => 'TEST-VEP-'.$this->faker->unique()->numerify('####'),
            'version_number' => 1,
            'name' => 'Metodología ficticia de prueba',
            'description' => 'Configuración anonimizada para pruebas automatizadas.',
            'valid_from' => now()->toDateString(),
            'active' => true,
            'configuration' => [
                'formula' => 'probability * consequence',
                'probability_values' => [1, 2, 4],
                'consequence_values' => [1, 2, 4],
            ],
        ];
    }
}
