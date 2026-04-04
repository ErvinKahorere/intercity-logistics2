<?php

namespace Database\Seeders;

use App\Models\CityRoute;
use App\Models\Location;
use Illuminate\Database\Seeder;

class CityRouteSeeder extends Seeder
{
    public function run(): void
    {
        $routes = [
            ['Windhoek', 'Walvis Bay', 395, 4.8, 780, true],
            ['Windhoek', 'Oshakati', 715, 8.9, 1260, true],
            ['Windhoek', 'Swakopmund', 360, 4.5, 720, true],
            ['Windhoek', 'Rundu', 695, 8.2, 1215, true],
            ['Windhoek', 'Keetmanshoop', 500, 6.3, 940, false],
            ['Walvis Bay', 'Swakopmund', 35, 0.6, 180, false],
            ['Oshakati', 'Ondangwa', 33, 0.5, 165, false],
            ['Oshakati', 'Rundu', 435, 5.6, 840, false],
            ['Windhoek', 'Otjiwarongo', 250, 3.0, 540, false],
            ['Windhoek', 'Gobabis', 205, 2.8, 460, false],
        ];

        foreach ($routes as [$origin, $destination, $distanceKm, $estimatedHours, $baseFare, $featured]) {
            $originLocation = Location::where('name', $origin)->first();
            $destinationLocation = Location::where('name', $destination)->first();

            if (! $originLocation || ! $destinationLocation) {
                continue;
            }

            CityRoute::updateOrCreate(
                [
                    'origin_location_id' => $originLocation->id,
                    'destination_location_id' => $destinationLocation->id,
                ],
                [
                    'distance_km' => $distanceKm,
                    'estimated_hours' => $estimatedHours,
                    'base_fare' => $baseFare,
                    'is_featured' => $featured,
                    'is_active' => true,
                ]
            );
        }
    }
}
