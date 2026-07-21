<?php

namespace Database\Seeders;

use Database\Seeders\Support\PreventsProductionSeeding;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use PreventsProductionSeeding;

    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->preventProductionSeeding();
        $this->call(CompleteSoftwareSeeder::class);
    }
}
