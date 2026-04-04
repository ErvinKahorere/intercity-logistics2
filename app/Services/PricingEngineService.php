<?php

namespace App\Services;

use App\Models\ParcelRequest;

class PricingEngineService
{
    public function __construct(
        private PricingService $pricingService,
    ) {
    }

    public function quote(
        int $pickupLocationId,
        int $dropoffLocationId,
        ?float $weightKg,
        string $urgencyLevel
    ): array {
        return $this->pricingService->quote(
            $pickupLocationId,
            $dropoffLocationId,
            null,
            $weightKg,
            $urgencyLevel
        );
    }

    public function forParcel(ParcelRequest $parcelRequest): array
    {
        return $this->pricingService->quote(
            $parcelRequest->pickup_location_id,
            $parcelRequest->dropoff_location_id,
            $parcelRequest->package_type_id,
            (float) $parcelRequest->weight_kg,
            $parcelRequest->urgency_level ?? 'standard',
            $parcelRequest->load_size ?? 'small',
            $parcelRequest->notes
        );
    }
}
