<?php

namespace Database\Seeders;

use App\Models\Commune;
use App\Models\Region;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ChileLocationSeeder extends Seeder
{
    public function run(): void
    {
        $regions = json_decode(
            file_get_contents(database_path('seeders/data/chile_locations.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        DB::transaction(function () use ($regions) {
            foreach ($regions as $regionData) {
                $region = Region::query()->updateOrCreate(
                    ['code' => $regionData['code']],
                    [
                        'name' => $regionData['name'],
                        'short_name' => $regionData['short_name'] ?? null,
                        'abbreviation' => $regionData['abbreviation'] ?? null,
                        'iso_code' => $regionData['iso_code'] ?? null,
                        'sort_order' => $regionData['sort_order'] ?? 0,
                    ],
                );

                foreach ($regionData['communes'] ?? [] as $communeData) {
                    Commune::query()->updateOrCreate(
                        ['code' => $communeData['code']],
                        [
                            'region_id' => $region->id,
                            'name' => $communeData['name'],
                        ],
                    );
                }
            }
        });
    }
}
