<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $coordinates = [
            'windhoek' => [-22.5609, 17.0658, 'Khomas'],
            'swakopmund' => [-22.6784, 14.5266, 'Erongo'],
            'walvis bay' => [-22.9576, 14.5053, 'Erongo'],
            'gobabis' => [-22.4556, 18.9631, 'Omaheke'],
            'rundu' => [-17.9332, 19.7668, 'Kavango East'],
            'oshakati' => [-17.7883, 15.7044, 'Oshana'],
            'ondangwa' => [-17.9117, 15.9526, 'Oshana'],
            'otjiwarongo' => [-20.4637, 16.6477, 'Otjozondjupa'],
            'keetmanshoop' => [-26.5737, 18.1290, 'Karas'],
            'rehoboth' => [-23.3190, 17.0800, 'Hardap'],
            'mariental' => [-24.6333, 17.9667, 'Hardap'],
            'okahandja' => [-21.9847, 16.9175, 'Otjozondjupa'],
        ];

        foreach ($coordinates as $city => [$latitude, $longitude, $region]) {
            DB::table('locations')
                ->whereRaw('LOWER(name) = ?', [$city])
                ->update([
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'region' => $region,
                ]);
        }

        $routes = [
            ['windhoek', 'swakopmund', 360, 4.8, 180, 2.35, 220],
            ['windhoek', 'walvis bay', 395, 5.2, 195, 2.35, 235],
            ['windhoek', 'gobabis', 205, 3.0, 120, 2.20, 150],
            ['windhoek', 'rundu', 700, 8.8, 320, 2.55, 350],
            ['windhoek', 'oshakati', 710, 9.0, 325, 2.55, 360],
            ['windhoek', 'ondangwa', 730, 9.2, 330, 2.55, 365],
            ['windhoek', 'otjiwarongo', 250, 3.2, 145, 2.25, 170],
            ['windhoek', 'keetmanshoop', 510, 6.4, 255, 2.40, 290],
            ['windhoek', 'rehoboth', 90, 1.4, 85, 2.10, 110],
            ['windhoek', 'mariental', 268, 3.4, 150, 2.30, 175],
            ['okahandja', 'swakopmund', 290, 3.8, 150, 2.25, 180],
            ['swakopmund', 'walvis bay', 35, 0.7, 60, 1.95, 80],
        ];

        foreach ($routes as [$origin, $destination, $distanceKm, $eta, $baseFare, $perKmRate, $minimumPrice]) {
            $originId = DB::table('locations')->whereRaw('LOWER(name) = ?', [$origin])->value('id');
            $destinationId = DB::table('locations')->whereRaw('LOWER(name) = ?', [$destination])->value('id');

            if (! $originId || ! $destinationId) {
                continue;
            }

            DB::table('city_routes')
                ->where('origin_location_id', $originId)
                ->where('destination_location_id', $destinationId)
                ->update([
                    'distance_km' => $distanceKm,
                    'estimated_hours' => $eta,
                    'base_fare' => $baseFare,
                    'per_km_rate' => $perKmRate,
                    'minimum_price' => $minimumPrice,
                    'distance_source' => 'operational',
                    'route_code' => strtoupper(substr($origin, 0, 3) . '-' . substr($destination, 0, 3)),
                ]);
        }
    }

    public function down(): void
    {
    }
};
