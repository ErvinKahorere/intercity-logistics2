<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            LocationSeeder::class,
            PackageTypeSeeder::class,
            CityRouteSeeder::class,
            AdminAndDriverSeeder::class,
            DemoLogisticsSeeder::class,
        ]);
    }
}
