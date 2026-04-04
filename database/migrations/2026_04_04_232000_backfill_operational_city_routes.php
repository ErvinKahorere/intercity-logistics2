<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = Carbon::now();

        $routes = [
            ['windhoek', 'okahandja', 70, 1.1, 80, 2.05, 115, true],
            ['okahandja', 'windhoek', 70, 1.1, 80, 2.05, 115, true],
            ['windhoek', 'swakopmund', 360, 4.8, 180, 2.35, 220, true],
            ['swakopmund', 'windhoek', 360, 4.8, 180, 2.35, 220, true],
            ['windhoek', 'walvis bay', 395, 5.2, 195, 2.35, 235, true],
            ['walvis bay', 'windhoek', 395, 5.2, 195, 2.35, 235, true],
            ['windhoek', 'gobabis', 205, 3.0, 120, 2.20, 150, true],
            ['gobabis', 'windhoek', 205, 3.0, 120, 2.20, 150, true],
            ['windhoek', 'rundu', 700, 8.8, 320, 2.55, 350, true],
            ['rundu', 'windhoek', 700, 8.8, 320, 2.55, 350, true],
            ['windhoek', 'oshakati', 710, 9.0, 325, 2.55, 360, true],
            ['oshakati', 'windhoek', 710, 9.0, 325, 2.55, 360, true],
            ['windhoek', 'ondangwa', 730, 9.2, 330, 2.55, 365, true],
            ['ondangwa', 'windhoek', 730, 9.2, 330, 2.55, 365, true],
            ['windhoek', 'otjiwarongo', 250, 3.2, 145, 2.25, 170, true],
            ['otjiwarongo', 'windhoek', 250, 3.2, 145, 2.25, 170, true],
            ['windhoek', 'keetmanshoop', 510, 6.4, 255, 2.40, 290, true],
            ['keetmanshoop', 'windhoek', 510, 6.4, 255, 2.40, 290, true],
            ['windhoek', 'rehoboth', 90, 1.4, 85, 2.10, 110, true],
            ['rehoboth', 'windhoek', 90, 1.4, 85, 2.10, 110, true],
            ['windhoek', 'mariental', 268, 3.4, 150, 2.30, 175, true],
            ['mariental', 'windhoek', 268, 3.4, 150, 2.30, 175, true],
            ['okahandja', 'swakopmund', 290, 3.8, 150, 2.25, 180, false],
            ['swakopmund', 'okahandja', 290, 3.8, 150, 2.25, 180, false],
            ['swakopmund', 'walvis bay', 35, 0.7, 60, 1.95, 80, false],
            ['walvis bay', 'swakopmund', 35, 0.7, 60, 1.95, 80, false],
        ];

        foreach ($routes as [$origin, $destination, $distanceKm, $eta, $baseFare, $perKmRate, $minimumPrice, $featured]) {
            $originId = DB::table('locations')->whereRaw('LOWER(name) = ?', [$origin])->value('id');
            $destinationId = DB::table('locations')->whereRaw('LOWER(name) = ?', [$destination])->value('id');

            if (! $originId || ! $destinationId) {
                continue;
            }

            DB::table('city_routes')->updateOrInsert(
                [
                    'origin_location_id' => $originId,
                    'destination_location_id' => $destinationId,
                ],
                [
                    'route_code' => Str::upper(Str::substr($origin, 0, 3) . '-' . Str::substr($destination, 0, 3)),
                    'distance_km' => $distanceKm,
                    'estimated_hours' => $eta,
                    'base_fare' => $baseFare,
                    'per_km_rate' => $perKmRate,
                    'minimum_price' => $minimumPrice,
                    'distance_source' => 'operational',
                    'reverse_route_enabled' => true,
                    'is_featured' => $featured,
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        $pairs = [
            ['windhoek', 'okahandja'],
            ['okahandja', 'windhoek'],
            ['swakopmund', 'okahandja'],
            ['walvis bay', 'swakopmund'],
        ];

        foreach ($pairs as [$origin, $destination]) {
            $originId = DB::table('locations')->whereRaw('LOWER(name) = ?', [$origin])->value('id');
            $destinationId = DB::table('locations')->whereRaw('LOWER(name) = ?', [$destination])->value('id');

            if (! $originId || ! $destinationId) {
                continue;
            }

            DB::table('city_routes')
                ->where('origin_location_id', $originId)
                ->where('destination_location_id', $destinationId)
                ->delete();
        }
    }
};
