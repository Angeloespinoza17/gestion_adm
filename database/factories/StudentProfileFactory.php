<?php

namespace Database\Factories;

use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentProfileFactory extends Factory
{
    protected $model = StudentProfile::class;

    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName(), 'last_name' => $this->faker->lastName(),
            'rut' => $this->faker->unique()->numerify('########-#'), 'birthdate' => $this->faker->dateTimeBetween('-18 years', '-5 years'),
            'general_status' => 'activo',
        ];
    }
}
