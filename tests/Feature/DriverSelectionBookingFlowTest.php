<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\DriverRoute;
use App\Models\Quotation;
use App\Models\Location;
use App\Models\PackageType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DriverSelectionBookingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_confirmation_returns_payment_ready_payload_for_selected_driver(): void
    {
        [$pickup, $dropoff] = $this->makeLocations(['Windhoek', 'Walvis Bay']);
        $packageType = PackageType::create(['name' => 'Documents']);
        $customer = $this->makeUser('Booking Customer', 'booking-customer@example.com', 'user', $pickup->name);
        $driver = $this->createDriverWithRoute('Matched Driver', 'van', 'medium', ['Windhoek', 'Walvis Bay'], ['Documents']);

        $response = $this->actingAs($customer)->postJson(route('parcel-requests.store'), [
            'pickup_location_id' => $pickup->id,
            'dropoff_location_id' => $dropoff->id,
            'package_type_id' => $packageType->id,
            'pickup_address' => 'CBD pickup',
            'dropoff_address' => 'Harbour dropoff',
            'receiver_name' => 'Receiver Name',
            'receiver_phone' => '+264811234567',
            'weight_kg' => 4,
            'load_size' => 'small',
            'urgency_level' => 'standard',
            'selected_driver_id' => $driver->id,
            'confirmation_flow' => 'driver_selection',
        ]);

        $response->assertOk()
            ->assertJsonPath('parcel.selected_driver_id', $driver->id)
            ->assertJsonPath('parcel.payment_status', 'ready')
            ->assertJsonPath('parcel.booking_status', 'confirmed')
            ->assertJsonPath('parcel.preferred_driver.id', $driver->id);

        $this->assertNotEmpty($response->json('parcel.booking_reference'));
        $this->assertNotEmpty($response->json('parcel.tracking_number'));
        $this->assertSame(route('user.parcels.index'), $response->json('next_actions.track'));
    }

    public function test_booking_confirmation_rejects_driver_that_is_no_longer_a_match(): void
    {
        [$pickup, $dropoff] = $this->makeLocations(['Windhoek', 'Walvis Bay']);
        $packageType = PackageType::create(['name' => 'Documents']);
        PackageType::create(['name' => 'Furniture']);
        $customer = $this->makeUser('Validation Customer', 'validation-customer@example.com', 'user', $pickup->name);
        $driver = $this->createDriverWithRoute('Wrong Driver', 'van', 'medium', ['Windhoek', 'Walvis Bay'], ['Furniture']);

        $response = $this->actingAs($customer)->postJson(route('parcel-requests.store'), [
            'pickup_location_id' => $pickup->id,
            'dropoff_location_id' => $dropoff->id,
            'package_type_id' => $packageType->id,
            'pickup_address' => 'CBD pickup',
            'dropoff_address' => 'Harbour dropoff',
            'receiver_name' => 'Receiver Name',
            'receiver_phone' => '+264811234567',
            'weight_kg' => 4,
            'load_size' => 'small',
            'urgency_level' => 'standard',
            'selected_driver_id' => $driver->id,
            'confirmation_flow' => 'driver_selection',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['selected_driver_id']);
    }

    public function test_driver_selection_flow_requires_selected_driver_id(): void
    {
        [$pickup, $dropoff] = $this->makeLocations(['Windhoek', 'Walvis Bay']);
        $packageType = PackageType::create(['name' => 'Documents']);
        $customer = $this->makeUser('Missing Driver Customer', 'missing-driver@example.com', 'user', $pickup->name);

        $response = $this->actingAs($customer)->postJson(route('parcel-requests.store'), [
            'pickup_location_id' => $pickup->id,
            'dropoff_location_id' => $dropoff->id,
            'package_type_id' => $packageType->id,
            'pickup_address' => 'CBD pickup',
            'dropoff_address' => 'Harbour dropoff',
            'receiver_name' => 'Receiver Name',
            'receiver_phone' => '+264811234567',
            'weight_kg' => 4,
            'load_size' => 'small',
            'urgency_level' => 'standard',
            'confirmation_flow' => 'driver_selection',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['selected_driver_id']);
    }

    public function test_generated_quotation_includes_selected_driver_snapshot(): void
    {
        [$pickup, $dropoff] = $this->makeLocations(['Windhoek', 'Swakopmund']);
        $packageType = PackageType::create(['name' => 'Documents']);
        $customer = $this->makeUser('Quote Customer', 'quote-customer@example.com', 'user', $pickup->name);
        $driver = $this->createDriverWithRoute('Quoted Driver', 'van', 'medium', ['Windhoek', 'Swakopmund'], ['Documents']);

        $response = $this->actingAs($customer)->postJson(route('quotations.preview'), [
            'pickup_location_id' => $pickup->id,
            'dropoff_location_id' => $dropoff->id,
            'package_type_id' => $packageType->id,
            'weight_kg' => 4,
            'load_size' => 'small',
            'urgency_level' => 'standard',
            'selected_driver_id' => $driver->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('quotation.driver_snapshot.id', $driver->id)
            ->assertJsonPath('quotation.driver_snapshot.name', 'Quoted Driver');

        $this->assertNotNull(Quotation::query()->first()?->driver_snapshot);
    }

    private function createDriverWithRoute(string $name, string $vehicleType, string $maxLoadSize, array $locationNames, array $packageNames, bool $available = true): Driver
    {
        $user = $this->makeUser($name, strtolower(str_replace(' ', '.', $name)) . '@example.com', 'Driver', $locationNames[0] ?? 'Windhoek');

        $driver = Driver::create([
            'user_id' => $user->id,
            'phone' => $user->phone,
            'location' => $user->location,
            'status' => 'active',
        ]);

        $route = DriverRoute::create([
            'driver_id' => $driver->id,
            'vehicle_type' => $vehicleType,
            'max_load_size' => $maxLoadSize,
            'car_make' => 'Test',
            'car_model' => 'Model',
            'car_number' => 'N 001',
            'available' => $available,
        ]);

        $route->locations()->sync(Location::query()->whereIn('name', $locationNames)->pluck('id'));
        $route->packages()->sync(PackageType::query()->whereIn('name', $packageNames)->pluck('id'));

        return $driver->fresh(['driverRoutes.locations', 'driverRoutes.packages']);
    }

    private function makeLocations(array $names): array
    {
        return collect($names)->map(fn ($name) => Location::create(['name' => $name]))->all();
    }

    private function makeUser(string $name, string $email, string $role, string $location): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'phone' => '+264810000000',
            'location' => $location,
            'role' => $role,
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
    }
}
