<?php

namespace Database\Seeders;

use App\Models\CalendarInstitution;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CalendarInstitutionSeeder extends Seeder
{
    public function run(): void
    {
        $institutions = [
            ['name' => 'SII', 'website_url' => 'https://www.sii.cl', 'color' => '#0d6efd'],
            ['name' => 'Dirección del Trabajo', 'website_url' => 'https://www.dt.gob.cl', 'color' => '#198754'],
            ['name' => 'Superintendencia de Educación', 'website_url' => 'https://www.supereduc.cl', 'color' => '#6f42c1'],
            ['name' => 'MINEDUC', 'website_url' => 'https://www.mineduc.cl', 'color' => '#20c997'],
            ['name' => 'Previred', 'website_url' => 'https://www.previred.com', 'color' => '#fd7e14'],
            ['name' => 'AFC', 'website_url' => 'https://www.afc.cl', 'color' => '#dc3545'],
            ['name' => 'Municipalidad', 'website_url' => null, 'color' => '#495057'],
            ['name' => 'Entidad interna', 'website_url' => null, 'color' => '#0dcaf0'],
            ['name' => 'Otra', 'website_url' => null, 'color' => '#6c757d'],
        ];

        foreach ($institutions as $institution) {
            CalendarInstitution::query()->updateOrCreate(
                ['slug' => Str::slug($institution['name'])],
                [
                    'name' => $institution['name'],
                    'description' => null,
                    'website_url' => $institution['website_url'],
                    'color' => $institution['color'],
                    'is_active' => true,
                ],
            );
        }
    }
}
