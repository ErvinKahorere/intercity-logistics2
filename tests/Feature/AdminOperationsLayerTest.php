<?php

namespace Tests\Feature;

use App\Models\CityRoute;
use App\Models\Driver;
use App\Models\DriverLicence;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\PackageType;
use App\Models\ParcelRequest;
use App\Models\PricingRule;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminOperationsLayerTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_users_cannot_access_admin_operations_pages(): void
    {
        $user = $this->makeUser('customer@example.com', 'user');

        $this->actingAs($user)
            ->get(route('admin.verification.index'))
            ->assertForbidden();
    }

    public function test_admin_cannot_approve_an_expired_licence_submission(): void
    {
        $admin = $this->makeUser('admin@example.com', 'admin');
        $driver = $this->makeDriver('driver@example.com');
        $licence = DriverLicence::create([
            'driver_id' => $driver->id,
            'licence_type_code' => 'CE',
            'licence_type_name' => 'Code CE / Heavy Combination',
            'licence_number' => 'DRV-001',
            'expiry_date' => now()->subDay()->toDateString(),
            'document_path' => 'driver-licences/test.pdf',
            'verification_status' => 'pending',
            'submitted_at' => now(),
            'is_primary' => true,
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.verification.review', $licence), [
                'status' => 'verified',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['expiry_date']);
    }

    public function test_admin_route_management_rejects_duplicate_city_pairs(): void
    {
        $admin = $this->makeUser('admin@example.com', 'admin');
        [$pickup, $dropoff] = $this->makeLocations(['Windhoek', 'Walvis Bay']);

        CityRoute::create([
            'origin_location_id' => $pickup->id,
            'destination_location_id' => $dropoff->id,
            'route_code' => 'WIN-WVB',
            'distance_km' => 395,
            'estimated_hours' => 4.8,
            'base_fare' => 100,
            'per_km_rate' => 2.4,
            'minimum_price' => 180,
            'distance_source' => 'manual',
            'reverse_route_enabled' => true,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.routes.store'), [
                'origin_location_id' => $pickup->id,
                'destination_location_id' => $dropoff->id,
                'distance_km' => 400,
                'estimated_hours' => 5,
                'base_fare' => 120,
                'per_km_rate' => 2.6,
                'minimum_price' => 200,
                'distance_source' => 'manual',
                'reverse_route_enabled' => true,
                'is_active' => true,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['destination_location_id']);
    }

    public function test_admin_can_delete_a_pricing_rule(): void
    {
        $admin = $this->makeUser('admin@example.com', 'admin');
        $rule = PricingRule::create([
            'rule_type' => 'urgency',
            'rule_key' => 'express',
            'name' => 'Express uplift',
            'config' => ['multiplier' => 0.2, 'flat_fee' => 0],
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $this->actingAs($admin)
            ->deleteJson(route('admin.pricing.rules.destroy', $rule))
            ->assertOk()
            ->assertJsonPath('message', 'Pricing rule deleted.');

        $this->assertDatabaseMissing('pricing_rules', ['id' => $rule->id]);
    }

    public function test_admin_pricing_simulator_accepts_active_city_route_id(): void
    {
        $admin = $this->makeUser('admin@example.com', 'admin');
        [$pickup, $dropoff] = $this->makeLocations(['Windhoek', 'Swakopmund']);
        $packageType = PackageType::create(['name' => 'Documents', 'pricing_category' => 'documents']);
        $route = CityRoute::create([
            'origin_location_id' => $pickup->id,
            'destination_location_id' => $dropoff->id,
            'route_code' => 'WIN-SWK',
            'distance_km' => 360,
            'estimated_hours' => 4.5,
            'base_fare' => 90,
            'per_km_rate' => 2.1,
            'minimum_price' => 140,
            'distance_source' => 'manual',
            'reverse_route_enabled' => true,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.pricing.simulate'), [
                'city_route_id' => $route->id,
                'package_type_id' => $packageType->id,
                'weight_kg' => 8,
                'load_size' => 'small',
                'urgency_level' => 'express',
            ])
            ->assertOk()
            ->assertJsonPath('simulation.distance_km', 360);
    }

    public function test_admin_can_view_operational_document_pages_and_download_quote_and_invoice(): void
    {
        $admin = $this->makeUser('admin@example.com', 'admin');
        $customer = $this->makeUser('customer@example.com', 'user');
        [$pickup, $dropoff] = $this->makeLocations(['Windhoek', 'Otjiwarongo']);
        $packageType = PackageType::create(['name' => 'Small Parcel', 'pricing_category' => 'small_parcels']);
        $parcel = ParcelRequest::create([
            'user_id' => $customer->id,
            'tracking_number' => 'IC-TRACK-001',
            'pickup_location_id' => $pickup->id,
            'dropoff_location_id' => $dropoff->id,
            'package_type_id' => $packageType->id,
            'pickup_address' => 'CBD',
            'dropoff_address' => 'Main road',
            'receiver_name' => 'Receiver',
            'receiver_phone' => '+264810000001',
            'weight_kg' => 5,
            'load_size' => 'small',
            'status' => 'accepted',
        ]);

        $quotation = Quotation::create([
            'user_id' => $customer->id,
            'parcel_request_id' => $parcel->id,
            'quotation_number' => 'QT-260404-ABCDE',
            'status' => 'issued',
            'issue_date' => now()->toDateString(),
            'expires_at' => now()->addDays(5)->toDateString(),
            'pickup_location_id' => $pickup->id,
            'dropoff_location_id' => $dropoff->id,
            'package_type_id' => $packageType->id,
            'weight_kg' => 5,
            'load_size' => 'small',
            'urgency_level' => 'standard',
            'distance_km' => 250,
            'estimated_hours' => 3.5,
            'total' => 420,
            'pricing_breakdown' => ['base_fee' => 100, 'total' => 420],
            'customer_snapshot' => ['name' => $customer->name, 'email' => $customer->email],
            'route_snapshot' => ['pickup_city' => $pickup->name, 'dropoff_city' => $dropoff->name],
        ]);

        $invoice = Invoice::create([
            'user_id' => $customer->id,
            'parcel_request_id' => $parcel->id,
            'quotation_id' => $quotation->id,
            'invoice_number' => 'INV-260404-ABCDE',
            'status' => 'issued',
            'payment_status' => 'pending',
            'booking_reference' => $quotation->quotation_number,
            'tracking_number' => $parcel->tracking_number,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'total' => 420,
            'pricing_breakdown' => ['base_fee' => 100, 'total' => 420],
            'customer_snapshot' => ['name' => $customer->name, 'email' => $customer->email],
            'route_snapshot' => ['pickup_city' => $pickup->name, 'dropoff_city' => $dropoff->name],
        ]);

        $this->actingAs($admin)->get(route('admin.quotations.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.invoices.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.requests.index'))->assertOk();
        $this->actingAs($admin)->get(route('quotations.download', $quotation))->assertOk();
        $this->actingAs($admin)->get(route('invoices.download', $invoice))->assertOk();
    }

    private function makeUser(string $email, string $role): User
    {
        return User::create([
            'name' => ucfirst($role) . ' User',
            'email' => $email,
            'phone' => '+264810000000',
            'location' => 'Windhoek',
            'role' => $role,
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
    }

    private function makeDriver(string $email): Driver
    {
        $user = $this->makeUser($email, 'Driver');

        return Driver::create([
            'user_id' => $user->id,
            'phone' => $user->phone,
            'location' => $user->location,
            'status' => 'active',
            'verification_status' => 'pending',
        ]);
    }

    private function makeLocations(array $names): array
    {
        return collect($names)->map(fn ($name) => Location::create(['name' => $name]))->all();
    }
}
