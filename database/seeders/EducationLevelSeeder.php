<?php

namespace Database\Seeders;

use App\Models\EducationLevel;
use Illuminate\Database\Seeder;

class EducationLevelSeeder extends Seeder
{
    public static function levels(): array
    {
        return [
            ['name' => 'NT1', 'order' => 1, 'type' => 'parvularia'],
            ['name' => 'NT2', 'order' => 2, 'type' => 'parvularia'],
            ['name' => '1° básico', 'order' => 3, 'type' => 'basica'],
            ['name' => '2° básico', 'order' => 4, 'type' => 'basica'],
            ['name' => '3° básico', 'order' => 5, 'type' => 'basica'],
            ['name' => '4° básico', 'order' => 6, 'type' => 'basica'],
            ['name' => '5° básico', 'order' => 7, 'type' => 'basica'],
            ['name' => '6° básico', 'order' => 8, 'type' => 'basica'],
            ['name' => '7° básico', 'order' => 9, 'type' => 'basica'],
            ['name' => '8° básico', 'order' => 10, 'type' => 'basica'],
            ['name' => '1° medio', 'order' => 11, 'type' => 'media'],
            ['name' => '2° medio', 'order' => 12, 'type' => 'media'],
            ['name' => '3° medio', 'order' => 13, 'type' => 'media'],
            ['name' => '4° medio', 'order' => 14, 'type' => 'media'],
        ];
    }

    public function run(): void
    {
        foreach (self::levels() as $level) {
            EducationLevel::query()->updateOrCreate(
                ['order' => $level['order']],
                $level,
            );
        }
    }
}
