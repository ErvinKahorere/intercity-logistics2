<?php

namespace Tests\Unit;

use App\Models\Location;
use App\Services\RouteDistanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteDistanceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_estimated_route_when_coordinates_exist(): void
    {
        $pickup = Location::create([
            'name' => 'Alpha',
            'latitude' => -22.5609,
            'longitude' => 17.0658,
        ]);

        $dropoff = Location::create([
            'name' => 'Beta',
            'latitude' => -21.9847,
            'longitude' => 16.9175,
        ]);

        $payload = app(RouteDistanceService::class)->resolve($pickup->id, $dropoff->id);

        $this->assertNotNull($payload['city_route_id']);
        $this->assertSame('estimated', $payload['distance_source']);
        $this->assertGreaterThan(0, $payload['distance_km']);
        $this->assertDatabaseHas('city_routes', [
            'origin_location_id' => $pickup->id,
            'destination_location_id' => $dropoff->id,
            'distance_source' => 'estimated',
            'is_active' => 1,
        ]);
    }
}
