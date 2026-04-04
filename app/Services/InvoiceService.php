<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\ParcelRequest;
use App\Models\Quotation;
use Illuminate\Support\Str;

class InvoiceService
{
    public function __construct(
        private SmsService $smsService,
    ) {
    }

    public function createForParcelRequest(ParcelRequest $parcelRequest, ?Quotation $quotation = null): Invoice
    {
        $existing = Invoice::query()
            ->where('parcel_request_id', $parcelRequest->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $driver = $parcelRequest->assignedDriver?->loadMissing(['user', 'driverRoutes.locations']);

        $invoice = Invoice::create([
            'user_id' => $parcelRequest->user_id,
            'driver_id' => $driver?->id,
            'parcel_request_id' => $parcelRequest->id,
            'quotation_id' => $quotation?->id,
            'invoice_number' => $this->generateNumber(),
            'status' => 'issued',
            'payment_status' => 'pending',
            'booking_reference' => $quotation?->quotation_number,
            'tracking_number' => $parcelRequest->tracking_number,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'base_fee' => $parcelRequest->base_price,
            'distance_fee' => $parcelRequest->distance_fee,
            'weight_fee' => $parcelRequest->weight_surcharge,
            'urgency_fee' => $parcelRequest->urgency_surcharge,
            'special_handling_fee' => $parcelRequest->special_handling_fee,
            'subtotal' => ((float) $parcelRequest->total_price) - ((float) $parcelRequest->minimum_charge),
            'total' => $parcelRequest->final_price ?: $parcelRequest->total_price,
            'pricing_breakdown' => $parcelRequest->pricing_breakdown,
            'customer_snapshot' => [
                'name' => $parcelRequest->customer?->name,
                'email' => $parcelRequest->customer?->email,
                'phone' => $parcelRequest->customer?->phone,
            ],
            'driver_snapshot' => $driver ? [
                'id' => $driver->id,
                'name' => $driver->user?->name,
                'phone' => $driver->user?->phone ?? $driver->phone,
                'email' => $driver->user?->email,
                'verification_status' => $driver->verification_status,
                'vehicle_label' => trim(collect([$driver->driverRoutes->first()?->car_make, $driver->driverRoutes->first()?->car_model])->filter()->join(' ')) ?: ($driver->driverRoutes->first()?->vehicle_type ?? 'Delivery vehicle'),
                'vehicle_type' => $driver->driverRoutes->first()?->vehicle_type,
                'vehicle_registration' => $driver->driverRoutes->first()?->car_number,
                'route_summary' => $driver->driverRoutes->first()?->locations?->pluck('name')->join(' -> '),
            ] : null,
            'route_snapshot' => [
                'pickup_city' => $parcelRequest->pickupLocation?->name,
                'dropoff_city' => $parcelRequest->dropoffLocation?->name,
                'pickup_address' => $parcelRequest->pickup_address,
                'dropoff_address' => $parcelRequest->dropoff_address,
                'receiver_name' => $parcelRequest->receiver_name,
                'receiver_phone' => $parcelRequest->receiver_phone,
                'load_size' => $parcelRequest->load_size,
                'urgency_level' => $parcelRequest->urgency_level,
                'weight_kg' => $parcelRequest->weight_kg,
            ],
            'notes' => 'Manual payment support is available while payment integration is being phased in.',
        ]);

        if ($parcelRequest->customer) {
            $this->smsService->queueTemplate(
                $parcelRequest->customer,
                'customer.invoice_ready',
                [
                    'invoice_number' => $invoice->invoice_number,
                ],
                [
                    'event_type' => 'customer_invoice_ready',
                    'preference_key' => 'billing_updates',
                    'parcel_request_id' => $parcelRequest->id,
                ]
            );
        }

        return $invoice;
    }

    private function generateNumber(): string
    {
        do {
            $reference = 'INV-' . now()->format('ymd') . '-' . strtoupper(Str::random(5));
        } while (Invoice::query()->where('invoice_number', $reference)->exists());

        return $reference;
    }
}
