<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

class AcademicYearFactory extends Factory
{
    protected $model = AcademicYear::class;

    public function definition(): array
    {
        $year = $this->faker->unique()->numberBetween(2030, 2090);

        return [
            'name' => (string) $year, 'year' => $year, 'starts_at' => "{$year}-03-01",
            'ends_at' => "{$year}-12-20", 'is_active' => false, 'is_closed' => false,
        ];
    }
}
