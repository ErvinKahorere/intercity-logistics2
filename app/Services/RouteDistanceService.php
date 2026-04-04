<?php

namespace App\Services;

use App\Models\CityRoute;
use App\Models\Location;
use Illuminate\Support\Str;

class RouteDistanceService
{
    public function resolve(int $pickupLocationId, int $dropoffLocationId): array
    {
        $directRoute = CityRoute::query()
            ->where('origin_location_id', $pickupLocationId)
            ->where('destination_location_id', $dropoffLocationId)
            ->where('is_active', true)
            ->first();

        if ($directRoute) {
            return $this->payloadFromRoute($directRoute, false, 'direct');
        }

        $reverseRoute = CityRoute::query()
            ->where('origin_location_id', $dropoffLocationId)
            ->where('destination_location_id', $pickupLocationId)
            ->where('is_active', true)
            ->where('reverse_route_enabled', true)
            ->first();

        if ($reverseRoute) {
            return $this->payloadFromRoute($reverseRoute, true, 'reverse');
        }

        $pickup = Location::query()->find($pickupLocationId);
        $dropoff = Location::query()->find($dropoffLocationId);

        if ($pickup && $dropoff && $this->hasCoordinates($pickup) && $this->hasCoordinates($dropoff)) {
            $estimatedRoute = $this->ensureEstimatedRoute($pickup, $dropoff);

            return $this->payloadFromRoute($estimatedRoute, false, 'estimated');
        }

        $fallbackDistance = $this->approximateDistance($pickup, $dropoff);
        $estimatedHours = $this->estimateHours($fallbackDistance, 'approximate');

        return [
            'city_route_id' => null,
            'route' => null,
            'distance_km' => $fallbackDistance,
            'estimated_hours' => $estimatedHours,
            'distance_source' => 'approximate',
            'resolution' => 'fallback',
            'is_reverse_route' => false,
            'base_fare' => round(max(105, 45 + ($fallbackDistance * 0.28)), 2),
            'per_km_rate' => round($this->fallbackPerKmRate($fallbackDistance), 2),
            'minimum_price' => round(max(120, 75 + ($fallbackDistance * 0.22)), 2),
        ];
    }

    private function ensureEstimatedRoute(Location $pickup, Location $dropoff): CityRoute
    {
        $distanceKm = $this->approximateDistance($pickup, $dropoff);

        return CityRoute::query()->firstOrCreate(
            [
                'origin_location_id' => $pickup->id,
                'destination_location_id' => $dropoff->id,
            ],
            [
                'route_code' => $this->routeCode($pickup->name, $dropoff->name),
                'distance_km' => round($distanceKm, 2),
                'distance_source' => 'estimated',
                'road_adjustment_factor' => 1.18,
                'estimated_hours' => $this->estimateHours($distanceKm, 'estimated'),
                'base_fare' => round(max(105, 45 + ($distanceKm * 0.28)), 2),
                'per_km_rate' => round($this->fallbackPerKmRate($distanceKm), 2),
                'minimum_price' => round(max(120, 75 + ($distanceKm * 0.22)), 2),
                'reverse_route_enabled' => true,
                'operational_notes' => 'Auto-generated estimated lane from location coordinates.',
                'is_featured' => false,
                'is_active' => true,
            ]
        );
    }

    private function payloadFromRoute(CityRoute $route, bool $isReverse, string $resolution): array
    {
        $distanceKm = (float) $route->distance_km;
        $estimatedHours = (float) $route->estimated_hours;

        return [
            'city_route_id' => $route->id,
            'route' => $route,
            'distance_km' => $distanceKm,
            'estimated_hours' => $estimatedHours > 0 ? $estimatedHours : $this->estimateHours($distanceKm, (string) $route->distance_source),
            'distance_source' => $route->distance_source ?: 'operational',
            'resolution' => $resolution,
            'is_reverse_route' => $isReverse,
            'base_fare' => (float) $route->base_fare,
            'per_km_rate' => (float) $route->per_km_rate,
            'minimum_price' => (float) $route->minimum_price,
        ];
    }

    private function approximateDistance(?Location $pickup, ?Location $dropoff): float
    {
        if (! $pickup?->latitude || ! $pickup?->longitude || ! $dropoff?->latitude || ! $dropoff?->longitude) {
            return 320;
        }

        $earthRadius = 6371;
        $latFrom = deg2rad((float) $pickup->latitude);
        $lonFrom = deg2rad((float) $pickup->longitude);
        $latTo = deg2rad((float) $dropoff->latitude);
        $lonTo = deg2rad((float) $dropoff->longitude);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(
            pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)
        ));

        $straightDistance = $angle * $earthRadius;

        return round(max(30, $straightDistance * 1.18), 2);
    }

    private function hasCoordinates(Location $location): bool
    {
        return filled($location->latitude) && filled($location->longitude);
    }

    private function estimateHours(float $distanceKm, string $source = 'operational'): float
    {
        $distanceKm = max(20, $distanceKm);
        $baseSpeed = match ($source) {
            'manual', 'operational', 'direct', 'reverse' => 78,
            default => 72,
        };

        $driveHours = $distanceKm / $baseSpeed;
        $handlingBuffer = $distanceKm <= 120 ? 0.5 : ($distanceKm <= 320 ? 0.8 : ($distanceKm <= 600 ? 1.15 : 1.6));
        $restBuffer = $distanceKm >= 420 ? floor($distanceKm / 300) * 0.35 : 0;

        return round(max(0.9, $driveHours + $handlingBuffer + $restBuffer), 1);
    }

    private function fallbackPerKmRate(float $distanceKm): float
    {
        return match (true) {
            $distanceKm <= 80 => 2.1,
            $distanceKm <= 250 => 2.2,
            $distanceKm <= 500 => 2.3,
            default => 2.45,
        };
    }

    private function routeCode(string $origin, string $destination): string
    {
        return Str::upper(Str::substr(Str::ascii($origin), 0, 3) . '-' . Str::substr(Str::ascii($destination), 0, 3));
    }
}
