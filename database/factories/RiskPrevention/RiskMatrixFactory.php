<?php

namespace Database\Factories\RiskPrevention;

use App\Models\RiskPrevention\RiskMatrix;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RiskMatrix> */
class RiskMatrixFactory extends Factory
{
    protected $model = RiskMatrix::class;

    public function definition(): array
    {
        return [
            'company_key' => config('risk_matrix.company.key', 'institution'),
            'company_name' => 'Organización ficticia '.$this->faker->unique()->numberBetween(1000, 9999),
            'company_tax_id' => null,
            'company_address' => $this->faker->streetAddress(),
            'work_center_name_snapshot' => 'Centro de prueba',
            'code' => 'IPER-TEST-'.$this->faker->unique()->numerify('#####'),
            'folio' => $this->faker->optional()->numerify('F-####'),
            'name' => 'Matriz IPER ficticia',
            'description' => 'Registro completamente anonimizado para pruebas.',
            'created_by' => User::factory(),
        ];
    }
}
