<?php

namespace Database\Seeders;

use App\Models\CalendarProcessType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CalendarProcessTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Declaración jurada', 'color' => '#0d6efd'],
            ['name' => 'Vencimiento tributario', 'color' => '#dc3545'],
            ['name' => 'Proceso interno', 'color' => '#6f42c1'],
            ['name' => 'Proceso laboral', 'color' => '#198754'],
            ['name' => 'Proceso previsional', 'color' => '#20c997'],
            ['name' => 'Proceso educacional', 'color' => '#fd7e14'],
            ['name' => 'Proceso administrativo', 'color' => '#495057'],
            ['name' => 'Reunión', 'color' => '#0dcaf0'],
            ['name' => 'Otro', 'color' => '#6c757d'],
        ];

        foreach ($types as $type) {
            CalendarProcessType::query()->updateOrCreate(
                ['slug' => Str::slug($type['name'])],
                [
                    'name' => $type['name'],
                    'description' => null,
                    'color' => $type['color'],
                    'is_active' => true,
                ],
            );
        }
    }
}
