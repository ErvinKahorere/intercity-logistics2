<?php

namespace App\Services;

use App\Models\PackageType;

class PricingService
{
    public function __construct(
        private RouteDistanceService $routeDistanceService,
        private PricingRulesService $pricingRulesService,
    ) {
    }

    public function quote(
        int $pickupLocationId,
        int $dropoffLocationId,
        ?int $packageTypeId,
        ?float $weightKg,
        string $urgencyLevel,
        string $loadSize = 'small',
        ?string $notes = null
    ): array {
        $routeMeta = $this->routeDistanceService->resolve($pickupLocationId, $dropoffLocationId);
        $packageType = $packageTypeId ? PackageType::query()->find($packageTypeId) : null;
        $weightKg = max(0, (float) ($weightKg ?? 0));
        $globalConfig = $this->pricingRulesService->globalConfig();
        $routeOverride = $this->pricingRulesService->resolveRouteOverride($routeMeta['city_route_id']);
        $packageAdjustments = $this->pricingRulesService->packageAdjustments($packageType, $loadSize);
        $packageMultiplier = (float) $packageAdjustments['multiplier'];
        $specialHandlingFee = (float) $packageAdjustments['special_handling_fee'];

        if ($this->pricingRulesService->requiresExtraHandling($packageAdjustments['pricing_category'], $notes, $loadSize)) {
            $specialHandlingFee += $this->pricingRulesService->extraHandlingSurcharge($loadSize, $weightKg);
        }

        $baseFare = (float) data_get($routeOverride?->config, 'base_fee_override', $routeMeta['base_fare']);
        $perKmRate = (float) data_get($routeOverride?->config, 'per_km_rate_override', $routeMeta['per_km_rate'] ?: data_get($globalConfig, 'fallback_per_km_rate', 2.35));
        $minimumPrice = (float) data_get($routeOverride?->config, 'minimum_charge_override', $routeMeta['minimum_price'] ?: data_get($globalConfig, 'minimum_charge', 120));
        $distanceKm = (float) $routeMeta['distance_km'];
        $includedKm = $this->includedDistanceKm($distanceKm);
        $billableDistanceKm = max(0, $distanceKm - $includedKm);
        $distanceMultiplier = $this->distanceMultiplier($packageMultiplier);
        $baseFee = round(max($baseFare, (float) data_get($globalConfig, 'base_fee_floor', 55)) + $this->baseLoadAdjustment($loadSize), 2);
        $distanceFee = round($billableDistanceKm * $perKmRate * $distanceMultiplier, 2);
        $weightMeta = $this->pricingRulesService->computeWeightFee($weightKg, $loadSize);
        $weightFee = round((float) $weightMeta['fee'], 2);
        $urgencyMeta = $this->pricingRulesService->computeUrgencyFee($urgencyLevel, $baseFee + $distanceFee + $weightFee + $specialHandlingFee);
        $urgencyFee = round((float) $urgencyMeta['fee'], 2);

        $subtotal = round($baseFee + $distanceFee + $weightFee + $specialHandlingFee + $urgencyFee, 2);
        $minimumCharge = round(max($minimumPrice - $subtotal, 0), 2);
        $total = round($subtotal + $minimumCharge, 2);

        return [
            'city_route_id' => $routeMeta['city_route_id'],
            'distance_km' => round((float) $routeMeta['distance_km'], 2),
            'estimated_hours' => round((float) $routeMeta['estimated_hours'], 1),
            'base_price' => $baseFee,
            'distance_fee' => $distanceFee,
            'weight_surcharge' => $weightFee,
            'urgency_surcharge' => $urgencyFee,
            'special_handling_fee' => round($specialHandlingFee, 2),
            'minimum_charge' => $minimumCharge,
            'parcel_multiplier' => $packageMultiplier,
            'total_price' => $total,
            'final_price' => $total,
            'pricing_breakdown' => [
                'distance_source' => $routeMeta['distance_source'],
                'route_resolution' => $routeMeta['resolution'],
                'is_reverse_route' => $routeMeta['is_reverse_route'],
                'included_distance_km' => round($includedKm, 2),
                'billable_distance_km' => round($billableDistanceKm, 2),
                'route_override_rule_id' => $routeOverride?->id,
                'weight_rule_id' => $weightMeta['rule']?->id,
                'urgency_rule_id' => $urgencyMeta['rule']?->id,
                'parcel_rule_id' => $packageAdjustments['rule']?->id,
                'weight_tier_fee' => $weightMeta['tier_fee'],
                'weight_incremental_fee' => $weightMeta['incremental_fee'],
                'load_surcharge' => $weightMeta['load_surcharge'],
                'urgency_multiplier' => $urgencyMeta['multiplier'],
                'urgency_flat_fee' => $urgencyMeta['flat_fee'],
                'base_fee' => $baseFee,
                'distance_fee' => $distanceFee,
                'weight_fee' => $weightFee,
                'urgency_fee' => $urgencyFee,
                'special_handling_fee' => round($specialHandlingFee, 2),
                'minimum_charge' => $minimumCharge,
                'package_multiplier' => $packageMultiplier,
                'distance_multiplier' => $distanceMultiplier,
                'subtotal' => $subtotal,
                'total' => $total,
            ],
        ];
    }

    private function includedDistanceKm(float $distanceKm): float
    {
        return match (true) {
            $distanceKm <= 80 => 15,
            $distanceKm <= 250 => 25,
            $distanceKm <= 500 => 35,
            default => 45,
        };
    }

    private function distanceMultiplier(float $packageMultiplier): float
    {
        if ($packageMultiplier <= 1) {
            return 1;
        }

        return round(1 + (($packageMultiplier - 1) * 0.55), 3);
    }

    private function baseLoadAdjustment(string $loadSize): float
    {
        return match ($loadSize) {
            'large' => 18,
            'heavy' => 35,
            'oversized' => 55,
            default => 0,
        };
    }
}
