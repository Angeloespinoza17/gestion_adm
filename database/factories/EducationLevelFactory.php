<?php

namespace Database\Factories;

use App\Models\EducationLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

class EducationLevelFactory extends Factory
{
    protected $model = EducationLevel::class;

    public function definition(): array
    {
        $number = $this->faker->unique()->numberBetween(100, 999);

        return ['name' => 'Nivel prueba '.$number, 'order' => $number, 'type' => 'basica'];
    }
}
