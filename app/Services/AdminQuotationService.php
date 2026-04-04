<?php

namespace App\Services;

use App\Models\Quotation;

class AdminQuotationService
{
    public function list(): array
    {
        return Quotation::query()
            ->with(['customer:id,name,email,phone', 'pickupLocation:id,name', 'dropoffLocation:id,name', 'packageType:id,name', 'parcelRequest:id,tracking_number'])
            ->latest('issue_date')
            ->get()
            ->map(fn (Quotation $quotation) => [
                'id' => $quotation->id,
                'quotation_number' => $quotation->quotation_number,
                'status' => $quotation->status,
                'issue_date' => optional($quotation->issue_date)->toDateString(),
                'expires_at' => optional($quotation->expires_at)->toDateString(),
                'customer' => [
                    'name' => $quotation->customer?->name ?? data_get($quotation->customer_snapshot, 'name'),
                    'email' => $quotation->customer?->email ?? data_get($quotation->customer_snapshot, 'email'),
                    'phone' => $quotation->customer?->phone ?? data_get($quotation->customer_snapshot, 'phone'),
                ],
                'route' => [
                    'pickup' => $quotation->pickupLocation?->name
                        ?? data_get($quotation->route_snapshot, 'pickup_city')
                        ?? data_get($quotation->route_snapshot, 'pickup_address'),
                    'dropoff' => $quotation->dropoffLocation?->name
                        ?? data_get($quotation->route_snapshot, 'dropoff_city')
                        ?? data_get($quotation->route_snapshot, 'dropoff_address'),
                ],
                'parcel' => [
                    'type' => $quotation->packageType?->name,
                    'weight_kg' => $quotation->weight_kg,
                    'urgency' => $quotation->urgency_level,
                ],
                'distance_km' => $quotation->distance_km,
                'total' => $quotation->total,
                'pricing_breakdown' => $quotation->pricing_breakdown ?? [],
                'parcel_request_id' => $quotation->parcel_request_id,
                'tracking_number' => $quotation->parcelRequest?->tracking_number,
            ])
            ->values()
            ->all();
    }
}
