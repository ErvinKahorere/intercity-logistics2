<?php

namespace App\Services;

use App\Models\ParcelRequest;
use App\Models\Quotation;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Support\Str;

class QuotationService
{
    public function __construct(
        private PricingService $pricingService,
        private InvoiceService $invoiceService,
        private SmsService $smsService,
    ) {
    }

    public function createFromPreview(User $user, array $payload): Quotation
    {
        $selectedDriver = ! empty($payload['selected_driver_id'])
            ? Driver::query()->with(['user', 'driverRoutes.locations'])->find((int) $payload['selected_driver_id'])
            : null;
        $pricing = $this->pricingService->quote(
            (int) $payload['pickup_location_id'],
            (int) $payload['dropoff_location_id'],
            isset($payload['package_type_id']) ? (int) $payload['package_type_id'] : null,
            isset($payload['weight_kg']) ? (float) $payload['weight_kg'] : null,
            $payload['urgency_level'],
            $payload['load_size'] ?? 'small',
            $payload['notes'] ?? null,
        );

        $quotation = Quotation::create([
            'user_id' => $user->id,
            'driver_id' => $selectedDriver?->id,
            'quotation_number' => $this->generateNumber(),
            'status' => 'issued',
            'issue_date' => now()->toDateString(),
            'expires_at' => now()->addDays(5)->toDateString(),
            'pickup_location_id' => $payload['pickup_location_id'],
            'dropoff_location_id' => $payload['dropoff_location_id'],
            'package_type_id' => $payload['package_type_id'] ?? null,
            'weight_kg' => $payload['weight_kg'] ?? null,
            'load_size' => $payload['load_size'] ?? 'small',
            'urgency_level' => $payload['urgency_level'],
            'distance_km' => $pricing['distance_km'],
            'estimated_hours' => $pricing['estimated_hours'],
            'base_fee' => $pricing['base_price'],
            'distance_fee' => $pricing['distance_fee'],
            'weight_fee' => $pricing['weight_surcharge'],
            'urgency_fee' => $pricing['urgency_surcharge'],
            'special_handling_fee' => $pricing['special_handling_fee'],
            'subtotal' => $pricing['total_price'] - $pricing['minimum_charge'],
            'total' => $pricing['total_price'],
            'pricing_breakdown' => $pricing['pricing_breakdown'],
            'customer_snapshot' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
            'driver_snapshot' => $this->buildDriverSnapshot($selectedDriver),
            'route_snapshot' => [
                'pickup_address' => $payload['pickup_address'] ?? null,
                'dropoff_address' => $payload['dropoff_address'] ?? null,
                'receiver_name' => $payload['receiver_name'] ?? null,
                'receiver_phone' => $payload['receiver_phone'] ?? null,
            ],
        ]);

        $this->smsService->queueTemplate(
            $user,
            'customer.quotation_ready',
            [
                'quote_number' => $quotation->quotation_number,
            ],
            [
                'event_type' => 'customer_quotation_ready',
                'preference_key' => 'billing_updates',
            ]
        );

        return $quotation;
    }

    public function createFromParcelRequest(ParcelRequest $parcelRequest): Quotation
    {
        $existing = $parcelRequest->latestQuotation()->first();
        if ($existing) {
            return $existing;
        }

        $driver = $parcelRequest->assignedDriver ?: $this->selectedDriverFromParcel($parcelRequest);

        $quotation = Quotation::create([
            'user_id' => $parcelRequest->user_id,
            'driver_id' => $driver?->id,
            'parcel_request_id' => $parcelRequest->id,
            'quotation_number' => $this->generateNumber(),
            'status' => 'issued',
            'issue_date' => now()->toDateString(),
            'expires_at' => now()->addDays(5)->toDateString(),
            'pickup_location_id' => $parcelRequest->pickup_location_id,
            'dropoff_location_id' => $parcelRequest->dropoff_location_id,
            'package_type_id' => $parcelRequest->package_type_id,
            'weight_kg' => $parcelRequest->weight_kg,
            'load_size' => $parcelRequest->load_size,
            'urgency_level' => $parcelRequest->urgency_level,
            'distance_km' => $parcelRequest->distance_km,
            'estimated_hours' => $parcelRequest->estimated_hours,
            'base_fee' => $parcelRequest->base_price,
            'distance_fee' => $parcelRequest->distance_fee,
            'weight_fee' => $parcelRequest->weight_surcharge,
            'urgency_fee' => $parcelRequest->urgency_surcharge,
            'special_handling_fee' => $parcelRequest->special_handling_fee,
            'subtotal' => ((float) $parcelRequest->total_price) - ((float) $parcelRequest->minimum_charge),
            'total' => $parcelRequest->total_price,
            'pricing_breakdown' => $parcelRequest->pricing_breakdown,
            'customer_snapshot' => [
                'name' => $parcelRequest->customer?->name,
                'email' => $parcelRequest->customer?->email,
                'phone' => $parcelRequest->customer?->phone,
            ],
            'driver_snapshot' => $this->buildDriverSnapshot($driver),
            'route_snapshot' => [
                'pickup_city' => $parcelRequest->pickupLocation?->name,
                'dropoff_city' => $parcelRequest->dropoffLocation?->name,
                'pickup_address' => $parcelRequest->pickup_address,
                'dropoff_address' => $parcelRequest->dropoff_address,
                'receiver_name' => $parcelRequest->receiver_name,
                'receiver_phone' => $parcelRequest->receiver_phone,
            ],
        ]);

        if ($parcelRequest->customer) {
            $this->smsService->queueTemplate(
                $parcelRequest->customer,
                'customer.quotation_ready',
                [
                    'quote_number' => $quotation->quotation_number,
                ],
                [
                    'event_type' => 'customer_quotation_ready',
                    'preference_key' => 'billing_updates',
                    'parcel_request_id' => $parcelRequest->id,
                ]
            );
        }

        return $quotation;
    }

    public function accept(Quotation $quotation): Quotation
    {
        if ($quotation->status === 'accepted') {
            return $quotation->loadMissing('invoice');
        }

        $quotation->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        if ($quotation->parcelRequest) {
            $invoice = $this->invoiceService->createForParcelRequest($quotation->parcelRequest, $quotation);

            if ($invoice) {
                $quotation->update([
                    'status' => 'converted',
                    'converted_at' => now(),
                ]);
            }
        }

        return $quotation->fresh(['invoice', 'pickupLocation', 'dropoffLocation', 'packageType']);
    }

    private function generateNumber(): string
    {
        do {
            $reference = 'QT-' . now()->format('ymd') . '-' . strtoupper(Str::random(5));
        } while (Quotation::query()->where('quotation_number', $reference)->exists());

        return $reference;
    }

    private function selectedDriverFromParcel(ParcelRequest $parcelRequest): ?Driver
    {
        $selection = json_decode((string) ($parcelRequest->status_note ?? ''), true);
        $driverId = $selection['preferred_driver_id'] ?? $selection['pd'] ?? null;

        if (! $driverId) {
            return null;
        }

        return Driver::query()
            ->with(['user', 'driverRoutes.locations'])
            ->find((int) $driverId);
    }

    private function buildDriverSnapshot(?Driver $driver): ?array
    {
        if (! $driver) {
            return null;
        }

        $route = $driver->driverRoutes->firstWhere('available', true) ?? $driver->driverRoutes->first();

        return [
            'id' => $driver->id,
            'name' => $driver->user?->name,
            'phone' => $driver->user?->phone ?? $driver->phone,
            'email' => $driver->user?->email,
            'verification_status' => $driver->verification_status,
            'vehicle_label' => trim(collect([$route?->car_make, $route?->car_model])->filter()->join(' ')) ?: ($route?->vehicle_type ?? 'Delivery vehicle'),
            'vehicle_type' => $route?->vehicle_type,
            'vehicle_registration' => $route?->car_number,
            'route_summary' => $route?->locations?->pluck('name')->join(' -> '),
        ];
    }
}
