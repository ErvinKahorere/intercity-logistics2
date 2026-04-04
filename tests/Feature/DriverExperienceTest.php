<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\DriverBankAccount;
use App\Models\DriverLicence;
use App\Models\DriverRoute;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\PackageType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DriverExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_homepage_redirects_logged_in_drivers_to_driver_home(): void
    {
        $driverUser = $this->makeUser('Driver Home User', 'driver-home@example.com', 'Driver', 'Windhoek');
        Driver::create([
            'user_id' => $driverUser->id,
            'phone' => $driverUser->phone,
            'location' => $driverUser->location,
            'status' => 'active',
        ]);

        $this->actingAs($driverUser)
            ->get(route('welcome'))
            ->assertRedirect(route('driver.home'));
    }

    public function test_driver_detail_hides_sensitive_information_from_customers(): void
    {
        $driver = $this->createDriverFixture();
        $customer = $this->makeUser('Customer Viewer', 'customer-viewer@example.com', 'user', 'Windhoek');

        $response = $this->actingAs($customer)->get(route('driver.detail', $driver));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('details.viewer_mode', 'customer')
                ->where('details.profile.email', null)
                ->where('details.profile.phone', null)
                ->where('details.verification.licence_number', null)
                ->where('details.banking', null)
                ->where('details.documents', null)
            );
    }

    public function test_driver_detail_shows_operational_information_for_self_view(): void
    {
        $driver = $this->createDriverFixture();
        $driverUser = $driver->user;

        $response = $this->actingAs($driverUser)->get(route('driver.detail', $driver));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('details.viewer_mode', 'self')
                ->where('details.banking.bank_name', 'Bank Windhoek')
                ->where('details.verification.licence_number', 'DRV-44321')
                ->where('details.actions.can_edit', true)
            );
    }

    public function test_driver_profile_exposes_basic_accounting_history(): void
    {
        $driver = $this->createDriverFixture();

        Invoice::create([
            'user_id' => $driver->user_id,
            'driver_id' => $driver->id,
            'invoice_number' => 'INV-TEST-001',
            'status' => 'issued',
            'payment_status' => 'pending',
            'booking_reference' => 'BKG-TEST-001',
            'tracking_number' => 'ICL-TEST-ACC',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'base_fee' => 120,
            'distance_fee' => 200,
            'weight_fee' => 20,
            'urgency_fee' => 15,
            'special_handling_fee' => 0,
            'subtotal' => 355,
            'total' => 355,
            'route_snapshot' => [
                'pickup_city' => 'Windhoek',
                'dropoff_city' => 'Walvis Bay',
            ],
        ]);

        $response = $this->actingAs($driver->user)->get(route('driver.profile'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('driverProfile.accounting.invoice_count', 1)
                ->where('driverProfile.accounting.latest_invoices.0.invoice_number', 'INV-TEST-001')
                ->where('driverProfile.accounting.latest_invoices.0.total', 355)
            );
    }

    private function createDriverFixture(): Driver
    {
        [$windhoek, $walvis] = $this->makeLocations(['Windhoek', 'Walvis Bay']);
        $packageType = PackageType::create(['name' => 'Documents']);
        $user = $this->makeUser('Driver Detail User', 'driver-detail@example.com', 'Driver', $windhoek->name);

        $driver = Driver::create([
            'user_id' => $user->id,
            'phone' => $user->phone,
            'location' => $user->location,
            'status' => 'active',
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);

        $route = DriverRoute::create([
            'driver_id' => $driver->id,
            'vehicle_type' => 'van',
            'max_load_size' => 'medium',
            'car_make' => 'Toyota',
            'car_model' => 'Hiace',
            'car_number' => 'N 5544',
            'available' => true,
        ]);

        $route->locations()->sync([$windhoek->id, $walvis->id]);
        $route->packages()->sync([$packageType->id]);

        DriverLicence::create([
            'driver_id' => $driver->id,
            'licence_type_code' => 'code_b',
            'licence_type_name' => 'Code B / Light Motor Vehicle',
            'licence_number' => 'DRV-44321',
            'issue_date' => now()->subYears(3),
            'expiry_date' => now()->addMonths(18),
            'document_path' => 'licences/driver-detail-user.pdf',
            'document_original_name' => 'driver-detail-user.pdf',
            'document_mime_type' => 'application/pdf',
            'document_size' => 24576,
            'verification_status' => 'verified',
            'is_primary' => true,
        ]);

        DriverBankAccount::create([
            'driver_id' => $driver->id,
            'account_holder_name' => 'Driver Detail User',
            'bank_name' => 'Bank Windhoek',
            'account_number' => '1234567890',
            'account_number_last4' => '7890',
            'account_type' => 'cheque',
            'status' => 'confirmed',
        ]);

        return $driver->fresh(['user']);
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
