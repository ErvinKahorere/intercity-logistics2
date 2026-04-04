<?php

namespace Tests\Unit;

use App\Models\Driver;
use App\Models\DriverRoute;
use App\Models\Location;
use App\Models\PackageType;
use App\Models\ParcelRequest;
use App\Models\User;
use App\Services\DriverMatchService;
use App\Services\ParcelWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DriverMatchServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_route_match_ranks_above_partial_corridor_match(): void
    {
        [$windhoek, $walvis, $swakopmund] = $this->makeLocations(['Windhoek', 'Walvis Bay', 'Swakopmund']);
        $documents = PackageType::create(['name' => 'Documents']);

        $exactDriver = $this->createDriverWithRoute('Exact Driver', 'van', 'medium', ['Windhoek', 'Walvis Bay'], ['Documents']);
        $partialDriver = $this->createDriverWithRoute('Partial Driver', 'van', 'medium', ['Windhoek', 'Swakopmund'], ['Documents']);

        $parcel = $this->makeParcelRequest($windhoek, $walvis, $documents, [
            'load_size' => 'small',
            'urgency_level' => 'standard',
        ]);

        $matches = app(DriverMatchService::class)->match($parcel);

        $this->assertCount(2, $matches);
        $this->assertSame($exactDriver->id, $matches->first()->id);
        $this->assertGreaterThan($matches[1]->match_score, $matches[0]->match_score);
        $this->assertContains('Exact route match', $matches[0]->match_reasons);
    }

    public function test_unsupported_parcel_type_is_excluded(): void
    {
        [$windhoek, $walvis] = $this->makeLocations(['Windhoek', 'Walvis Bay']);
        $documents = PackageType::create(['name' => 'Documents']);
        PackageType::create(['name' => 'Furniture']);

        $supportedDriver = $this->createDriverWithRoute('Supported Driver', 'van', 'medium', ['Windhoek', 'Walvis Bay'], ['Documents']);
        $this->createDriverWithRoute('Unsupported Driver', 'van', 'medium', ['Windhoek', 'Walvis Bay'], ['Furniture']);

        $parcel = $this->makeParcelRequest($windhoek, $walvis, $documents);

        $matches = app(DriverMatchService::class)->match($parcel);

        $this->assertCount(1, $matches);
        $this->assertSame($supportedDriver->id, $matches->first()->id);
    }

    public function test_lower_workload_scores_higher_than_busy_driver(): void
    {
        [$windhoek, $walvis] = $this->makeLocations(['Windhoek', 'Walvis Bay']);
        $documents = PackageType::create(['name' => 'Documents']);

        $freshDriver = $this->createDriverWithRoute('Fresh Driver', 'van', 'medium', ['Windhoek', 'Walvis Bay'], ['Documents']);
        $busyDriver = $this->createDriverWithRoute('Busy Driver', 'van', 'medium', ['Windhoek', 'Walvis Bay'], ['Documents']);

        $this->makeAssignedParcel($busyDriver, $windhoek, $walvis, $documents, ParcelRequest::STATUS_ACCEPTED, 'ICL-BUSY-001');
        $this->makeAssignedParcel($busyDriver, $windhoek, $walvis, $documents, ParcelRequest::STATUS_IN_TRANSIT, 'ICL-BUSY-002');

        $parcel = $this->makeParcelRequest($windhoek, $walvis, $documents);

        $matches = app(DriverMatchService::class)->match($parcel);

        $this->assertSame($freshDriver->id, $matches->first()->id);
        $this->assertContains('Low current workload', $matches->first()->match_reasons);
    }

    public function test_urgent_request_prioritizes_express_ready_driver(): void
    {
        [$windhoek, $walvis] = $this->makeLocations(['Windhoek', 'Walvis Bay']);
        $documents = PackageType::create(['name' => 'Documents']);

        $fastDriver = $this->createDriverWithRoute('Fast Driver', 'van', 'medium', ['Windhoek', 'Walvis Bay'], ['Documents']);
        $slowDriver = $this->createDriverWithRoute('Slow Driver', 'van', 'medium', ['Windhoek', 'Walvis Bay'], ['Documents']);

        $slowDriver->driverRoutes()->first()->update(['updated_at' => now()->subDays(3)]);
        $this->makeAssignedParcel($slowDriver, $windhoek, $walvis, $documents, ParcelRequest::STATUS_ACCEPTED, 'ICL-SLOW-001');
        $this->makeAssignedParcel($slowDriver, $windhoek, $walvis, $documents, ParcelRequest::STATUS_PICKED_UP, 'ICL-SLOW-002');

        $parcel = $this->makeParcelRequest($windhoek, $walvis, $documents, [
            'urgency_level' => 'same_day',
        ]);

        $matches = app(DriverMatchService::class)->match($parcel);

        $this->assertSame($fastDriver->id, $matches->first()->id);
        $this->assertContains('Express Ready', $matches->first()->match_badges);
    }

    public function test_offline_driver_is_excluded(): void
    {
        [$windhoek, $walvis] = $this->makeLocations(['Windhoek', 'Walvis Bay']);
        $documents = PackageType::create(['name' => 'Documents']);

        $onlineDriver = $this->createDriverWithRoute('Online Driver', 'van', 'medium', ['Windhoek', 'Walvis Bay'], ['Documents']);
        $this->createDriverWithRoute('Offline Driver', 'van', 'medium', ['Windhoek', 'Walvis Bay'], ['Documents'], false, 'inactive');

        $parcel = $this->makeParcelRequest($windhoek, $walvis, $documents);
        $matches = app(DriverMatchService::class)->match($parcel);

        $this->assertCount(1, $matches);
        $this->assertSame($onlineDriver->id, $matches->first()->id);
    }

    public function test_match_cache_refreshes_when_driver_route_state_changes(): void
    {
        [$windhoek, $walvis] = $this->makeLocations(['Windhoek', 'Walvis Bay']);
        $documents = PackageType::create(['name' => 'Documents']);

        $driver = $this->createDriverWithRoute('Cached Driver', 'van', 'medium', ['Windhoek', 'Walvis Bay'], ['Documents']);
        $parcel = $this->makeParcelRequest($windhoek, $walvis, $documents);

        $firstMatches = app(DriverMatchService::class)->match($parcel);
        $this->assertCount(1, $firstMatches);
        $this->assertSame($driver->id, $firstMatches->first()->id);

        $driver->driverRoutes()->first()->update(['available' => false]);

        $secondMatches = app(DriverMatchService::class)->match($parcel);
        $this->assertCount(0, $secondMatches);
    }

    public function test_refresh_open_request_matches_updates_stored_match_ids_after_route_change(): void
    {
        [$windhoek, $walvis] = $this->makeLocations(['Windhoek', 'Walvis Bay']);
        $documents = PackageType::create(['name' => 'Documents']);

        $driver = $this->createDriverWithRoute('Workflow Driver', 'van', 'medium', ['Windhoek', 'Walvis Bay'], ['Documents']);
        $parcel = $this->makeParcelRequest($windhoek, $walvis, $documents);

        app(ParcelWorkflowService::class)->matchDrivers($parcel);
        $this->assertSame([$driver->id], $parcel->fresh()->matched_driver_ids);
        $this->assertSame(ParcelRequest::STATUS_MATCHED, $parcel->fresh()->status);

        $driver->driverRoutes()->first()->update(['available' => false]);

        app(ParcelWorkflowService::class)->refreshOpenRequestMatches();

        $parcel->refresh();
        $this->assertSame([], $parcel->matched_driver_ids);
        $this->assertSame(ParcelRequest::STATUS_PENDING, $parcel->status);
    }

    public function test_impossible_vehicle_mismatch_is_rejected(): void
    {
        [$windhoek, $walvis] = $this->makeLocations(['Windhoek', 'Walvis Bay']);
        $mining = PackageType::create(['name' => 'Mining Equipment']);

        $truckDriver = $this->createDriverWithRoute('Truck Driver', 'truck', 'oversized', ['Windhoek', 'Walvis Bay'], ['Mining Equipment']);
        $this->createDriverWithRoute('Car Driver', 'car', 'small', ['Windhoek', 'Walvis Bay'], ['Mining Equipment']);

        $parcel = $this->makeParcelRequest($windhoek, $walvis, $mining, [
            'load_size' => 'oversized',
            'urgency_level' => 'express',
            'weight_kg' => 80,
        ]);

        $matches = app(DriverMatchService::class)->match($parcel);

        $this->assertCount(1, $matches);
        $this->assertSame($truckDriver->id, $matches->first()->id);
        $this->assertContains('Heavy Load Ready', $matches->first()->match_badges);
    }

    private function createDriverWithRoute(string $name, string $vehicleType, string $maxLoadSize, array $locationNames, array $packageNames, bool $available = true, string $status = 'active'): Driver
    {
        $user = User::create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)) . '@example.com',
            'phone' => '+264810000000',
            'location' => $locationNames[0] ?? 'Windhoek',
            'role' => 'Driver',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        $driver = Driver::create([
            'user_id' => $user->id,
            'phone' => $user->phone,
            'location' => $user->location,
            'status' => $status,
        ]);

        $route = DriverRoute::create([
            'driver_id' => $driver->id,
            'vehicle_type' => $vehicleType,
            'max_load_size' => $maxLoadSize,
            'is_refrigerated' => $vehicleType === 'refrigerated_truck',
            'car_make' => 'Test',
            'car_model' => 'Model',
            'car_number' => 'N 000',
            'available' => $available,
        ]);

        $route->locations()->sync(Location::query()->whereIn('name', $locationNames)->pluck('id'));
        $route->packages()->sync(PackageType::query()->whereIn('name', $packageNames)->pluck('id'));

        return $driver->fresh(['driverRoutes.locations', 'driverRoutes.packages']);
    }

    private function makeParcelRequest(Location $pickup, Location $dropoff, PackageType $packageType, array $overrides = []): ParcelRequest
    {
        $customer = User::create([
            'name' => 'Customer ' . uniqid(),
            'email' => uniqid('customer', true) . '@example.com',
            'phone' => '+264811111111',
            'location' => $pickup->name,
            'role' => 'user',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        return ParcelRequest::create(array_merge([
            'user_id' => $customer->id,
            'tracking_number' => 'ICL-' . uniqid(),
            'pickup_location_id' => $pickup->id,
            'dropoff_location_id' => $dropoff->id,
            'package_type_id' => $packageType->id,
            'pickup_address' => 'Pickup point',
            'dropoff_address' => 'Dropoff point',
            'receiver_name' => 'Receiver',
            'receiver_phone' => '+264822222222',
            'weight_kg' => 5,
            'load_size' => 'small',
            'urgency_level' => 'standard',
            'distance_km' => 350,
            'estimated_hours' => 5,
            'base_price' => 600,
            'weight_surcharge' => 0,
            'urgency_surcharge' => 0,
            'total_price' => 600,
            'final_price' => 600,
            'status' => ParcelRequest::STATUS_PENDING,
        ], $overrides));
    }

    private function makeAssignedParcel(Driver $driver, Location $pickup, Location $dropoff, PackageType $packageType, string $status, string $tracking): ParcelRequest
    {
        return $this->makeParcelRequest($pickup, $dropoff, $packageType, [
            'tracking_number' => $tracking,
            'assigned_driver_id' => $driver->id,
            'status' => $status,
            'accepted_at' => now()->subHour(),
        ]);
    }

    private function makeLocations(array $names): array
    {
        return collect($names)->map(fn ($name) => Location::create(['name' => $name]))->all();
    }
}
