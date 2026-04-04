<?php

namespace Database\Seeders;

use App\Models\AppNotification;
use App\Models\Driver;
use App\Models\DriverAlert;
use App\Models\DriverRoute;
use App\Models\Location;
use App\Models\PackageType;
use App\Models\ParcelRequest;
use App\Models\ParcelStatusUpdate;
use App\Models\User;
use App\Services\ParcelWorkflowService;
use App\Services\PricingEngineService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoLogisticsSeeder extends Seeder
{
    public function run(): void
    {
        $pricingEngine = app(PricingEngineService::class);
        $workflow = app(ParcelWorkflowService::class);

        $customers = [
            ['name' => 'Selma Shipping', 'email' => 'customer1@example.com', 'location' => 'Windhoek'],
            ['name' => 'Northern Trade Hub', 'email' => 'customer2@example.com', 'location' => 'Oshakati'],
        ];

        foreach ($customers as $customer) {
            User::updateOrCreate(
                ['email' => $customer['email']],
                [
                    'name' => $customer['name'],
                    'email' => $customer['email'],
                    'location' => $customer['location'],
                    'phone' => '+264810000000',
                    'role' => 'user',
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );
        }

        $driverDefinitions = [
            [
                'name' => 'Erastus Haingura',
                'email' => 'driver1@example.com',
                'phone' => '+264811111111',
                'location' => 'Windhoek',
                'route_locations' => ['Windhoek', 'Okahandja', 'Swakopmund', 'Walvis Bay'],
                'packages' => ['Documents', 'Small Parcels', 'Electronics', 'Mining Equipment'],
                'vehicle' => ['Toyota', 'Hilux', 'N 11111 WB', 'bakkie', 'heavy', false],
            ],
            [
                'name' => 'Hileni Tomas',
                'email' => 'driver2@example.com',
                'phone' => '+264822222222',
                'location' => 'Oshakati',
                'route_locations' => ['Windhoek', 'Otjiwarongo', 'Tsumeb', 'Oshakati', 'Ondangwa'],
                'packages' => ['Documents', 'Retail Stock', 'Food Supplies', 'Bulk Cargo'],
                'vehicle' => ['Ford', 'Transit', 'N 22222 OS', 'van', 'large', false],
            ],
            [
                'name' => 'Paulus Shipanga',
                'email' => 'driver3@example.com',
                'phone' => '+264833333333',
                'location' => 'Rundu',
                'route_locations' => ['Windhoek', 'Otjiwarongo', 'Grootfontein', 'Rundu'],
                'packages' => ['Documents', 'Office Equipment', 'Industrial Equipment'],
                'vehicle' => ['Isuzu', 'D-Max', 'N 33333 RU', 'truck', 'oversized', false],
            ],
        ];

        foreach ($driverDefinitions as $definition) {
            $user = User::updateOrCreate(
                ['email' => $definition['email']],
                [
                    'name' => $definition['name'],
                    'email' => $definition['email'],
                    'location' => $definition['location'],
                    'phone' => $definition['phone'],
                    'role' => 'Driver',
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );

            $driver = Driver::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'phone' => $definition['phone'],
                    'status' => 'active',
                    'location' => $definition['location'],
                ]
            );

            $driverRoute = DriverRoute::updateOrCreate(
                ['driver_id' => $driver->id],
                [
                    'vehicle_type' => $definition['vehicle'][3],
                    'max_load_size' => $definition['vehicle'][4],
                    'is_refrigerated' => $definition['vehicle'][5],
                    'car_make' => $definition['vehicle'][0],
                    'car_model' => $definition['vehicle'][1],
                    'car_number' => $definition['vehicle'][2],
                    'available' => true,
                ]
            );

            $driverRoute->locations()->sync(Location::query()->whereIn('name', $definition['route_locations'])->pluck('id'));
            $driverRoute->packages()->sync(PackageType::query()->whereIn('name', $definition['packages'])->pluck('id'));
        }

        $parcelDefinitions = [
            [
                'customer_email' => 'customer1@example.com',
                'pickup' => 'Windhoek',
                'dropoff' => 'Walvis Bay',
                'package' => 'Mining Equipment',
                'weight_kg' => 18,
                'load_size' => 'heavy',
                'urgency_level' => 'express',
                'client_offer_price' => 980,
                'receiver_name' => 'Port Dispatch Desk',
                'receiver_phone' => '+264844444444',
                'status' => ParcelRequest::STATUS_MATCHED,
                'assigned_driver_email' => null,
                'matched_driver_emails' => ['driver1@example.com'],
            ],
            [
                'customer_email' => 'customer2@example.com',
                'pickup' => 'Oshakati',
                'dropoff' => 'Windhoek',
                'package' => 'Food Supplies',
                'weight_kg' => 9,
                'load_size' => 'medium',
                'urgency_level' => 'same_day',
                'client_offer_price' => 1325,
                'receiver_name' => 'Central Market Team',
                'receiver_phone' => '+264855555555',
                'status' => ParcelRequest::STATUS_IN_TRANSIT,
                'assigned_driver_email' => 'driver2@example.com',
                'matched_driver_emails' => ['driver2@example.com'],
            ],
            [
                'customer_email' => 'customer1@example.com',
                'pickup' => 'Windhoek',
                'dropoff' => 'Rundu',
                'package' => 'Office Equipment',
                'weight_kg' => 6,
                'load_size' => 'medium',
                'urgency_level' => 'standard',
                'client_offer_price' => null,
                'receiver_name' => 'Regional Office',
                'receiver_phone' => '+264866666666',
                'status' => ParcelRequest::STATUS_DELIVERED,
                'assigned_driver_email' => 'driver3@example.com',
                'matched_driver_emails' => ['driver3@example.com'],
            ],
        ];

        foreach ($parcelDefinitions as $definition) {
            $customer = User::where('email', $definition['customer_email'])->first();
            $pickup = Location::where('name', $definition['pickup'])->first();
            $dropoff = Location::where('name', $definition['dropoff'])->first();
            $package = PackageType::where('name', $definition['package'])->first();
            $assignedDriver = $definition['assigned_driver_email']
                ? Driver::whereHas('user', fn ($query) => $query->where('email', $definition['assigned_driver_email']))->first()
                : null;
            $matchedDriverIds = Driver::whereHas('user', fn ($query) => $query->whereIn('email', $definition['matched_driver_emails']))->pluck('id')->all();

            if (! $customer || ! $pickup || ! $dropoff || ! $package) {
                continue;
            }

            $pricing = $pricingEngine->quote($pickup->id, $dropoff->id, $definition['weight_kg'], $definition['urgency_level']);
            $acceptedPrice = $definition['client_offer_price'] ?? $pricing['final_price'];

            $parcel = ParcelRequest::updateOrCreate(
                [
                    'tracking_number' => 'ICL-DEMO-' . strtoupper(Str::slug($definition['pickup'] . '-' . $definition['dropoff'] . '-' . $definition['package'], '')),
                ],
                [
                    'user_id' => $customer->id,
                    'city_route_id' => $pricing['city_route_id'],
                    'pickup_location_id' => $pickup->id,
                    'dropoff_location_id' => $dropoff->id,
                    'package_type_id' => $package->id,
                    'pickup_address' => $definition['pickup'] . ' Main Depot',
                    'dropoff_address' => $definition['dropoff'] . ' Central Yard',
                    'receiver_name' => $definition['receiver_name'],
                    'receiver_phone' => $definition['receiver_phone'],
                    'weight_kg' => $definition['weight_kg'],
                    'load_size' => $definition['load_size'],
                    'urgency_level' => $definition['urgency_level'],
                    'distance_km' => $pricing['distance_km'],
                    'estimated_hours' => $pricing['estimated_hours'],
                    'base_price' => $pricing['base_price'],
                    'weight_surcharge' => $pricing['weight_surcharge'],
                    'urgency_surcharge' => $pricing['urgency_surcharge'],
                    'total_price' => $pricing['total_price'],
                    'client_offer_price' => $definition['client_offer_price'],
                    'final_price' => in_array($definition['status'], [ParcelRequest::STATUS_ACCEPTED, ParcelRequest::STATUS_PICKED_UP, ParcelRequest::STATUS_IN_TRANSIT, ParcelRequest::STATUS_ARRIVED, ParcelRequest::STATUS_DELIVERED], true) ? $acceptedPrice : $pricing['final_price'],
                    'declared_value' => 15000,
                    'notes' => 'Seeded demo parcel for workflow testing.',
                    'status' => $definition['status'],
                    'assigned_driver_id' => $assignedDriver?->id,
                    'matched_driver_ids' => $matchedDriverIds,
                    'matched_at' => now()->subHours(6),
                    'accepted_at' => in_array($definition['status'], [ParcelRequest::STATUS_ACCEPTED, ParcelRequest::STATUS_PICKED_UP, ParcelRequest::STATUS_IN_TRANSIT, ParcelRequest::STATUS_ARRIVED, ParcelRequest::STATUS_DELIVERED], true) ? now()->subHours(5) : null,
                    'picked_up_at' => in_array($definition['status'], [ParcelRequest::STATUS_PICKED_UP, ParcelRequest::STATUS_IN_TRANSIT, ParcelRequest::STATUS_ARRIVED, ParcelRequest::STATUS_DELIVERED], true) ? now()->subHours(4) : null,
                    'in_transit_at' => in_array($definition['status'], [ParcelRequest::STATUS_IN_TRANSIT, ParcelRequest::STATUS_ARRIVED, ParcelRequest::STATUS_DELIVERED], true) ? now()->subHours(3) : null,
                    'arrived_at' => in_array($definition['status'], [ParcelRequest::STATUS_ARRIVED, ParcelRequest::STATUS_DELIVERED], true) ? now()->subHours(1) : null,
                    'delivered_at' => $definition['status'] === ParcelRequest::STATUS_DELIVERED ? now()->subMinutes(20) : null,
                ]
            );

            ParcelStatusUpdate::where('parcel_request_id', $parcel->id)->delete();
            DriverAlert::where('parcel_request_id', $parcel->id)->delete();
            AppNotification::where('parcel_request_id', $parcel->id)->delete();

            $workflow->logStatus($parcel, ParcelRequest::STATUS_PENDING, 'system');
            if ($definition['status'] !== ParcelRequest::STATUS_PENDING) {
                $workflow->logStatus($parcel, ParcelRequest::STATUS_MATCHED, 'system', ['message' => 'Matching drivers were alerted for this route.']);
            }
            if ($definition['status'] !== ParcelRequest::STATUS_MATCHED && $assignedDriver) {
                $workflow->logStatus($parcel, ParcelRequest::STATUS_ACCEPTED, 'driver', ['message' => ($assignedDriver->user?->name ?? 'Driver') . ' accepted this parcel.']);
            }
            if (in_array($definition['status'], [ParcelRequest::STATUS_PICKED_UP, ParcelRequest::STATUS_IN_TRANSIT, ParcelRequest::STATUS_ARRIVED, ParcelRequest::STATUS_DELIVERED], true)) {
                $workflow->logStatus($parcel, ParcelRequest::STATUS_PICKED_UP, 'driver');
            }
            if (in_array($definition['status'], [ParcelRequest::STATUS_IN_TRANSIT, ParcelRequest::STATUS_ARRIVED, ParcelRequest::STATUS_DELIVERED], true)) {
                $workflow->logStatus($parcel, ParcelRequest::STATUS_IN_TRANSIT, 'driver');
            }
            if (in_array($definition['status'], [ParcelRequest::STATUS_ARRIVED, ParcelRequest::STATUS_DELIVERED], true)) {
                $workflow->logStatus($parcel, ParcelRequest::STATUS_ARRIVED, 'driver');
            }
            if ($definition['status'] === ParcelRequest::STATUS_DELIVERED) {
                $workflow->logStatus($parcel, ParcelRequest::STATUS_DELIVERED, 'driver');
            }

            $workflow->notifyUser(
                $customer,
                $parcel,
                'Request created',
                $definition['client_offer_price']
                    ? 'Tracking number issued, route pricing prepared, and your offer was shared with matching drivers.'
                    : 'Tracking number issued and route pricing prepared.',
                'Created',
                'info',
                'request_created',
                [
                    'estimated_price' => $parcel->total_price,
                    'client_offer_price' => $parcel->client_offer_price,
                ]
            );
            if ($assignedDriver) {
                $workflow->notifyUser(
                    $customer,
                    $parcel,
                    'Driver assigned',
                    $definition['client_offer_price']
                        ? ($assignedDriver->user?->name ?? 'A driver') . ' accepted your offer of N$ ' . number_format((float) $acceptedPrice, 2) . '.'
                        : ($assignedDriver->user?->name ?? 'A driver') . ' accepted your delivery request.',
                    'Accepted',
                    'success',
                    'driver_assigned',
                    [
                        'final_price' => $acceptedPrice,
                        'client_offer_price' => $parcel->client_offer_price,
                    ]
                );
            }
            if (in_array($definition['status'], [ParcelRequest::STATUS_IN_TRANSIT, ParcelRequest::STATUS_ARRIVED, ParcelRequest::STATUS_DELIVERED], true)) {
                $workflow->notifyUser($customer, $parcel, 'Parcel in transit', 'Your parcel is moving to the destination city.', 'Transit', 'info', 'parcel_in_transit');
            }
            if ($definition['status'] === ParcelRequest::STATUS_DELIVERED) {
                $workflow->notifyUser($customer, $parcel, 'Parcel delivered', 'Your parcel was delivered successfully.', 'Delivered', 'success', 'parcel_delivered');
            }

            foreach ($matchedDriverIds as $driverId) {
                $driver = Driver::find($driverId);
                if (! $driver) {
                    continue;
                }

                DriverAlert::create([
                    'driver_id' => $driverId,
                    'parcel_request_id' => $parcel->id,
                    'title' => 'New matching request',
                    'message' => sprintf(
                        '%s -> %s | %s | %s urgency%s',
                        $definition['pickup'],
                        $definition['dropoff'],
                        $definition['package'],
                        str_replace('_', ' ', $definition['urgency_level']),
                        $definition['client_offer_price'] ? ' | Offer N$ ' . number_format((float) $definition['client_offer_price'], 2) : ''
                    ),
                    'severity' => $assignedDriver?->id === $driverId ? 'success' : 'info',
                    'is_read' => false,
                    'meta' => [
                        'tracking_number' => $parcel->tracking_number,
                        'route_summary' => $definition['pickup'] . ' -> ' . $definition['dropoff'],
                        'match_score' => $assignedDriver?->id === $driverId ? 92 : 86,
                        'estimated_price' => $parcel->total_price,
                        'client_offer_price' => $parcel->client_offer_price,
                    ],
                ]);

                if ($driver->user) {
                    $workflow->notifyUser(
                        $driver->user,
                        $parcel,
                        'New matching request',
                        sprintf(
                            '%s -> %s | %s urgency%s',
                            $definition['pickup'],
                            $definition['dropoff'],
                            str_replace('_', ' ', $definition['urgency_level']),
                            $definition['client_offer_price'] ? ' | Offer N$ ' . number_format((float) $definition['client_offer_price'], 2) : ''
                        ),
                        'Match',
                        'info',
                        'driver_match',
                        [
                            'estimated_price' => $parcel->total_price,
                            'client_offer_price' => $parcel->client_offer_price,
                        ]
                    );
                }
            }
        }

        $this->command?->info('Demo logistics data seeded.');
        $this->command?->warn('Customer Login: customer1@example.com | password');
        $this->command?->warn('Driver Login: driver1@example.com | password');
    }
}
