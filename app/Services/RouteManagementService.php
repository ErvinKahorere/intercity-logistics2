<?php

namespace App\Services;

use App\Models\CityRoute;
use App\Models\Location;
use App\Models\ParcelRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RouteManagementService
{
    public function list(): array
    {
        $highTrafficPairs = ParcelRequest::query()
            ->selectRaw('pickup_location_id, dropoff_location_id, count(*) as total')
            ->groupBy('pickup_location_id', 'dropoff_location_id')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $this->trafficKey((int) $row->pickup_location_id, (int) $row->dropoff_location_id) => (int) $row->total,
            ])
            ->all();

        return CityRoute::query()
            ->with(['originLocation:id,name', 'destinationLocation:id,name'])
            ->orderByDesc('is_active')
            ->orderBy('origin_location_id')
            ->orderBy('destination_location_id')
            ->get()
            ->map(function (CityRoute $route) use ($highTrafficPairs) {
                $missingDistance = blank($route->distance_km) || (float) $route->distance_km <= 0;
                $trafficCount = $highTrafficPairs[$this->trafficKey($route->origin_location_id, $route->destination_location_id)] ?? 0;

                return [
                    'id' => $route->id,
                    'route_code' => $route->route_code,
                    'route_key' => ($route->originLocation?->name ?? 'Origin') . ' -> ' . ($route->destinationLocation?->name ?? 'Destination'),
                    'origin_location_id' => $route->origin_location_id,
                    'destination_location_id' => $route->destination_location_id,
                    'origin_name' => $route->originLocation?->name,
                    'destination_name' => $route->destinationLocation?->name,
                    'distance_km' => $route->distance_km,
                    'estimated_hours' => $route->estimated_hours,
                    'base_fare' => $route->base_fare,
                    'per_km_rate' => $route->per_km_rate,
                    'minimum_price' => $route->minimum_price,
                    'distance_source' => $route->distance_source,
                    'reverse_route_enabled' => (bool) $route->reverse_route_enabled,
                    'is_active' => (bool) $route->is_active,
                    'operational_notes' => $route->operational_notes,
                    'updated_at' => optional($route->updated_at)->toIso8601String(),
                    'status' => $missingDistance ? 'missing_distance' : ($route->is_active ? 'active' : 'inactive'),
                    'source_type' => in_array($route->distance_source, ['manual', 'operational'], true) ? 'manual' : 'fallback',
                    'traffic_count' => $trafficCount,
                    'warnings' => collect([
                        $missingDistance ? 'Distance missing' : null,
                        $trafficCount >= 3 && $missingDistance ? 'High-traffic lane missing distance data' : null,
                    ])->filter()->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    public function save(array $validated, ?CityRoute $route = null): CityRoute
    {
        $validated = $this->normalizePayload($validated);
        $this->assertUniqueLane($validated, $route);

        $route ??= new CityRoute();
        $route->fill($validated);
        $route->route_code = $validated['route_code'] ?? $this->makeRouteCode(
            (int) $validated['origin_location_id'],
            (int) $validated['destination_location_id']
        );
        $route->save();

        return $route->fresh(['originLocation', 'destinationLocation']);
    }

    public function createReverse(CityRoute $route): CityRoute
    {
        if ((int) $route->origin_location_id === (int) $route->destination_location_id) {
            throw ValidationException::withMessages([
                'destination_location_id' => 'A reverse route cannot be created for the same origin and destination.',
            ]);
        }

        $reverse = CityRoute::query()->firstOrNew([
            'origin_location_id' => $route->destination_location_id,
            'destination_location_id' => $route->origin_location_id,
        ]);

        $reverse->fill([
            'route_code' => $this->makeRouteCode($route->destination_location_id, $route->origin_location_id),
            'distance_km' => $route->distance_km,
            'distance_source' => $route->distance_source,
            'road_adjustment_factor' => $route->road_adjustment_factor,
            'estimated_hours' => $route->estimated_hours,
            'base_fare' => $route->base_fare,
            'per_km_rate' => $route->per_km_rate,
            'minimum_price' => $route->minimum_price,
            'reverse_route_enabled' => true,
            'operational_notes' => $route->operational_notes,
            'is_active' => $route->is_active,
            'is_featured' => false,
        ]);
        $reverse->save();

        return $reverse->fresh(['originLocation', 'destinationLocation']);
    }

    public function locations(): array
    {
        return Location::query()->orderBy('name')->get(['id', 'name'])->toArray();
    }

    private function makeRouteCode(int $originId, int $destinationId): string
    {
        $origin = Location::query()->find($originId);
        $destination = Location::query()->find($destinationId);

        return Str::upper(Str::slug(Str::limit((string) $origin?->name, 3, ''), '')) . '-' .
            Str::upper(Str::slug(Str::limit((string) $destination?->name, 3, ''), ''));
    }

    private function normalizePayload(array $validated): array
    {
        $validated['distance_km'] = (float) $validated['distance_km'];
        $validated['estimated_hours'] = (float) $validated['estimated_hours'];
        $validated['base_fare'] = $validated['base_fare'] === '' || $validated['base_fare'] === null ? 0 : (float) $validated['base_fare'];
        $validated['per_km_rate'] = $validated['per_km_rate'] === '' || $validated['per_km_rate'] === null ? 0 : (float) $validated['per_km_rate'];
        $validated['minimum_price'] = $validated['minimum_price'] === '' || $validated['minimum_price'] === null ? 0 : (float) $validated['minimum_price'];

        $validated['reverse_route_enabled'] = (bool) ($validated['reverse_route_enabled'] ?? false);
        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);
        $validated['route_code'] = filled($validated['route_code'] ?? null) ? Str::upper((string) $validated['route_code']) : null;

        return $validated;
    }

    private function assertUniqueLane(array $validated, ?CityRoute $route = null): void
    {
        $query = CityRoute::query()
            ->where('origin_location_id', $validated['origin_location_id'])
            ->where('destination_location_id', $validated['destination_location_id']);

        if ($route?->exists) {
            $query->whereKeyNot($route->id);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'destination_location_id' => 'A route for this city pair already exists. Update the existing lane instead of creating a duplicate.',
            ]);
        }
    }

    private function trafficKey(int $originId, int $destinationId): string
    {
        return $originId . ':' . $destinationId;
    }
}
