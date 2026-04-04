<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\DriverRoute;
use App\Models\ParcelRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class DriverMatchService
{
    private const ROUTE_WEIGHT = 35;
    private const PARCEL_WEIGHT = 20;
    private const VEHICLE_WEIGHT = 20;
    private const AVAILABILITY_WEIGHT = 10;
    private const WORKLOAD_WEIGHT = 5;
    private const RECENCY_WEIGHT = 5;
    private const URGENCY_WEIGHT = 5;

    private const ACTIVE_STATUSES = [
        ParcelRequest::STATUS_ACCEPTED,
        ParcelRequest::STATUS_PICKED_UP,
        ParcelRequest::STATUS_IN_TRANSIT,
        ParcelRequest::STATUS_ARRIVED,
    ];

    private const CORRIDORS = [
        'coast' => ['windhoek', 'okahandja', 'swakopmund', 'walvis bay'],
        'north' => ['windhoek', 'okahandja', 'otjiwarongo', 'tsumeb', 'oshakati', 'ondangwa'],
        'northeast' => ['windhoek', 'okahandja', 'otjiwarongo', 'grootfontein', 'rundu'],
        'south' => ['windhoek', 'rehoboth', 'mariental', 'keetmanshoop', 'luderitz'],
        'east' => ['windhoek', 'gobabis'],
    ];

    private const PACKAGE_GROUPS = [
        'documents' => ['documents', 'small package', 'small packages', 'small parcels'],
        'fragile' => ['fragile', 'electronics', 'office equipment'],
        'commerce' => ['retail stock', 'food supplies', 'household goods'],
        'furniture' => ['furniture', 'household goods'],
        'refrigerated' => ['refrigerated goods', 'food supplies'],
        'bulk' => ['bulk cargo', 'construction materials', 'industrial equipment'],
        'heavy' => ['heavy loads', 'heavy machinery', 'mining equipment', 'industrial equipment', 'bulk cargo', 'oversized cargo'],
        'vehicles' => ['vehicles'],
        'livestock' => ['livestock'],
    ];

    public function match(ParcelRequest $parcelRequest, int $limit = 10): Collection
    {
        $rankings = collect(Cache::remember(
            $this->cacheKey($parcelRequest, $limit),
            now()->addMinutes(15),
            fn () => $this->calculateRankings($parcelRequest, $limit)->all()
        ));

        if ($rankings->isEmpty()) {
            return collect();
        }

        $drivers = Driver::query()
            ->with(['user', 'driverRoutes.locations', 'driverRoutes.packages'])
            ->whereIn('id', $rankings->pluck('driver_id')->all())
            ->get()
            ->keyBy('id');

        return $rankings->map(function (array $ranking) use ($drivers) {
            /** @var Driver|null $driver */
            $driver = $drivers->get($ranking['driver_id']);
            if (! $driver) {
                return null;
            }

            $bestRoute = $driver->driverRoutes->firstWhere('id', $ranking['route_id']);

            $driver->setAttribute('match_score', $ranking['score']);
            $driver->setAttribute('match_reasons', $ranking['reasons']);
            $driver->setAttribute('match_badges', $ranking['badges']);
            $driver->setAttribute('match_label', $ranking['match_label']);
            $driver->setAttribute('match_breakdown', $ranking['breakdown']);
            $driver->setAttribute('match_explanation', $ranking['explanation']);
            $driver->setAttribute('matching_route_summary', $ranking['route_summary']);
            $driver->setAttribute('matching_vehicle_type', $ranking['vehicle_type']);

            if ($bestRoute) {
                $driver->setRelation('bestRoute', $bestRoute);
            }

            return $driver;
        })->filter()->values();
    }

    private function calculateRankings(ParcelRequest $parcelRequest, int $limit): Collection
    {
        $drivers = Driver::query()
            ->with(['user', 'driverRoutes.locations', 'driverRoutes.packages'])
            ->where('status', 'active')
            ->whereHas('driverRoutes', fn ($query) => $query->where('available', true))
            ->get();

        if ($drivers->isEmpty()) {
            return collect();
        }

        $driverIds = $drivers->pluck('id');
        $activeWorkloads = ParcelRequest::query()
            ->selectRaw('assigned_driver_id, COUNT(*) as aggregate')
            ->whereIn('assigned_driver_id', $driverIds)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->groupBy('assigned_driver_id')
            ->pluck('aggregate', 'assigned_driver_id');

        return $drivers
            ->map(function (Driver $driver) use ($parcelRequest, $activeWorkloads) {
                return $this->scoreDriver($driver, $parcelRequest, (int) ($activeWorkloads[$driver->id] ?? 0));
            })
            ->filter()
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }

    private function scoreDriver(Driver $driver, ParcelRequest $parcelRequest, int $activeWorkload): ?array
    {
        $availableRoutes = $driver->driverRoutes->where('available', true)->values();
        if ($availableRoutes->isEmpty()) {
            return null;
        }

        $bestMatch = null;

        foreach ($availableRoutes as $route) {
            $routeDimension = $this->scoreRouteMatch($route, $parcelRequest);
            if ($routeDimension['reject']) {
                continue;
            }

            $parcelDimension = $this->scoreParcelCompatibility($route, $parcelRequest);
            if ($parcelDimension['reject']) {
                continue;
            }

            $vehicleDimension = $this->scoreVehicleCapability($route, $parcelRequest);
            if ($vehicleDimension['reject']) {
                continue;
            }

            $availabilityDimension = $this->scoreAvailability($driver, $route, $activeWorkload);
            if ($availabilityDimension['reject']) {
                continue;
            }

            $workloadDimension = $this->scoreWorkload($activeWorkload);
            $recencyDimension = $this->scoreRecency($route);
            $urgencyDimension = $this->scoreUrgency($parcelRequest, $activeWorkload, $route);
            $verificationDimension = $this->scoreVerification($driver);

            $score = $routeDimension['score']
                + $parcelDimension['score']
                + $vehicleDimension['score']
                + $availabilityDimension['score']
                + $workloadDimension['score']
                + $recencyDimension['score']
                + $urgencyDimension['score']
                + $verificationDimension['score'];

            $reasons = collect([
                ...$routeDimension['reasons'],
                ...$parcelDimension['reasons'],
                ...$vehicleDimension['reasons'],
                ...$availabilityDimension['reasons'],
                ...$workloadDimension['reasons'],
                ...$recencyDimension['reasons'],
                ...$urgencyDimension['reasons'],
                ...$verificationDimension['reasons'],
            ])->unique()->values()->all();

            $badges = collect([
                ...$routeDimension['badges'],
                ...$parcelDimension['badges'],
                ...$vehicleDimension['badges'],
                ...$availabilityDimension['badges'],
                ...$workloadDimension['badges'],
                ...$urgencyDimension['badges'],
                ...$verificationDimension['badges'],
            ])->unique()->values()->all();

            $match = [
                'driver_id' => $driver->id,
                'route_id' => $route->id,
                'score' => min((int) round($score), 100),
                'reasons' => $reasons,
                'badges' => $badges,
                'match_label' => $this->labelForScore((int) round($score)),
                'route_summary' => $route->locations->pluck('name')->join(' -> '),
                'vehicle_type' => $route->vehicle_type,
                'breakdown' => [
                    'route' => $routeDimension['score'],
                    'parcel_type' => $parcelDimension['score'],
                    'vehicle' => $vehicleDimension['score'],
                    'availability' => $availabilityDimension['score'],
                    'workload' => $workloadDimension['score'],
                    'recency' => $recencyDimension['score'],
                    'urgency' => $urgencyDimension['score'],
                    'verification' => $verificationDimension['score'],
                ],
                'explanation' => [
                    'route_quality' => $routeDimension['quality'],
                    'parcel_fit' => $parcelDimension['quality'],
                    'vehicle_fit' => $vehicleDimension['quality'],
                    'availability' => $availabilityDimension['quality'],
                    'workload' => $workloadDimension['quality'],
                    'activity' => $recencyDimension['quality'],
                    'urgency' => $urgencyDimension['quality'],
                    'verification' => $verificationDimension['quality'],
                ],
            ];

            if (! $bestMatch || $match['score'] > $bestMatch['score']) {
                $bestMatch = $match;
            }
        }

        if (! $bestMatch) {
            return null;
        }

        $bestMatch['badges'] = $this->finalizeBadges($bestMatch);

        return $bestMatch;
    }

    private function scoreRouteMatch(DriverRoute $route, ParcelRequest $parcelRequest): array
    {
        $locationIds = $route->locations->pluck('id')->all();
        $routeCities = $route->locations->pluck('name')->map(fn ($name) => $this->normalize($name))->all();
        $pickup = $this->normalize($parcelRequest->pickupLocation?->name);
        $dropoff = $this->normalize($parcelRequest->dropoffLocation?->name);
        $hasPickup = in_array($parcelRequest->pickup_location_id, $locationIds, true);
        $hasDropoff = in_array($parcelRequest->dropoff_location_id, $locationIds, true);

        if ($hasPickup && $hasDropoff) {
            return [
                'score' => self::ROUTE_WEIGHT,
                'quality' => 'exact',
                'reasons' => ['Exact route match'],
                'badges' => ['Route Match'],
                'reject' => false,
            ];
        }

        $sharedCorridors = $this->sharedCorridors($routeCities, [$pickup, $dropoff]);

        if (($hasPickup || $hasDropoff) && ! empty($sharedCorridors)) {
            return [
                'score' => 24,
                'quality' => 'partial',
                'reasons' => ['Partial route match'],
                'badges' => ['Partial Route'],
                'reject' => false,
            ];
        }

        if (! empty($sharedCorridors)) {
            return [
                'score' => 16,
                'quality' => 'corridor',
                'reasons' => ['Same corridor coverage'],
                'badges' => ['Corridor'],
                'reject' => false,
            ];
        }

        return [
            'score' => 0,
            'quality' => 'none',
            'reasons' => [],
            'badges' => [],
            'reject' => true,
        ];
    }

    private function scoreParcelCompatibility(DriverRoute $route, ParcelRequest $parcelRequest): array
    {
        $requestCategory = $this->packageCategory($parcelRequest->packageType?->name);
        $supportedCategories = $route->packages
            ->pluck('name')
            ->map(fn ($name) => $this->packageCategory($name))
            ->filter()
            ->values();

        if ($supportedCategories->contains($requestCategory)) {
            return [
                'score' => self::PARCEL_WEIGHT,
                'quality' => 'exact',
                'reasons' => ['Supports parcel category'],
                'badges' => ['Parcel Fit'],
                'reject' => false,
            ];
        }

        if ($this->hasRelatedPackageSupport($requestCategory, $supportedCategories)) {
            return [
                'score' => 12,
                'quality' => 'related',
                'reasons' => ['Supports related cargo type'],
                'badges' => ['Related Fit'],
                'reject' => false,
            ];
        }

        return [
            'score' => 0,
            'quality' => 'unsupported',
            'reasons' => [],
            'badges' => [],
            'reject' => true,
        ];
    }

    private function scoreVehicleCapability(DriverRoute $route, ParcelRequest $parcelRequest): array
    {
        if (! $route->supportsLoadSize($parcelRequest->load_size)) {
            return ['score' => 0, 'quality' => 'impossible', 'reasons' => [], 'badges' => [], 'reject' => true];
        }

        $packageCategory = $this->packageCategory($parcelRequest->packageType?->name);
        $vehicleType = $route->vehicle_type ?: 'car';

        if ($packageCategory === 'refrigerated' && ! $route->is_refrigerated) {
            return ['score' => 0, 'quality' => 'impossible', 'reasons' => [], 'badges' => [], 'reject' => true];
        }

        $score = match ($vehicleType) {
            'refrigerated_truck' => $packageCategory === 'refrigerated' ? 20 : ($route->supportsLoadSize($parcelRequest->load_size) ? 17 : 0),
            'truck' => in_array($parcelRequest->load_size, ['heavy', 'oversized'], true) || in_array($packageCategory, ['heavy', 'bulk', 'vehicles'], true) ? 20 : 14,
            'van' => in_array($parcelRequest->load_size, ['small', 'medium', 'large'], true) && ! in_array($packageCategory, ['heavy', 'vehicles', 'livestock'], true) ? 17 : 10,
            'bakkie' => in_array($parcelRequest->load_size, ['small', 'medium', 'large'], true) ? 16 : 11,
            'car' => in_array($parcelRequest->load_size, ['small', 'medium'], true) && in_array($packageCategory, ['documents', 'fragile', 'commerce'], true) ? 13 : 0,
            default => 10,
        };

        if ($score <= 0) {
            return ['score' => 0, 'quality' => 'impossible', 'reasons' => [], 'badges' => [], 'reject' => true];
        }

        return [
            'score' => min($score, self::VEHICLE_WEIGHT),
            'quality' => $score >= 18 ? 'ideal' : 'acceptable',
            'reasons' => [$score >= 18 ? 'Vehicle fits parcel type' : 'Vehicle can handle this load'],
            'badges' => $score >= 18 && in_array($parcelRequest->load_size, ['heavy', 'oversized'], true) ? ['Heavy Load Ready'] : ['Vehicle Fit'],
            'reject' => false,
        ];
    }

    private function scoreAvailability(Driver $driver, DriverRoute $route, int $activeWorkload): array
    {
        if ($driver->status !== 'active' || ! $route->available) {
            return ['score' => 0, 'quality' => 'offline', 'reasons' => [], 'badges' => [], 'reject' => true];
        }

        if ($activeWorkload >= 4) {
            return ['score' => 0, 'quality' => 'overloaded', 'reasons' => [], 'badges' => [], 'reject' => true];
        }

        return [
            'score' => $activeWorkload === 0 ? self::AVAILABILITY_WEIGHT : 8,
            'quality' => $activeWorkload === 0 ? 'available_now' : 'busy_available',
            'reasons' => [$activeWorkload === 0 ? 'Available now' : 'Available with open capacity'],
            'badges' => ['Available'],
            'reject' => false,
        ];
    }

    private function scoreWorkload(int $activeWorkload): array
    {
        return match (true) {
            $activeWorkload === 0 => ['score' => self::WORKLOAD_WEIGHT, 'quality' => 'low', 'reasons' => ['Low current workload'], 'badges' => ['Low Load']],
            $activeWorkload <= 2 => ['score' => 3, 'quality' => 'steady', 'reasons' => ['Manageable current workload'], 'badges' => []],
            default => ['score' => 1, 'quality' => 'busy', 'reasons' => ['Busy but still available'], 'badges' => []],
        };
    }

    private function scoreRecency(DriverRoute $route): array
    {
        $hours = now()->diffInHours($route->updated_at ?? now());

        return match (true) {
            $hours <= 6 => ['score' => self::RECENCY_WEIGHT, 'quality' => 'recent', 'reasons' => ['Recently active on route'], 'badges' => ['Recently Active']],
            $hours <= 24 => ['score' => 3, 'quality' => 'today', 'reasons' => ['Active on this route today'], 'badges' => []],
            $hours <= 72 => ['score' => 1, 'quality' => 'stale', 'reasons' => [], 'badges' => []],
            default => ['score' => 0, 'quality' => 'old', 'reasons' => [], 'badges' => []],
        };
    }

    private function scoreUrgency(ParcelRequest $parcelRequest, int $activeWorkload, DriverRoute $route): array
    {
        if ($parcelRequest->urgency_level === 'standard') {
            return ['score' => 2, 'quality' => 'standard', 'reasons' => [], 'badges' => []];
        }

        $score = 0;
        if ($activeWorkload === 0) {
            $score += 3;
        } elseif ($activeWorkload <= 2) {
            $score += 1;
        }

        if (($route->updated_at ?? now())->gt(now()->subHours(12))) {
            $score += 2;
        }

        return [
            'score' => min($score, self::URGENCY_WEIGHT),
            'quality' => $score >= 4 ? 'express_ready' : ($score > 0 ? 'possible' : 'slow'),
            'reasons' => $score >= 4 ? ['Express ready'] : ($score > 0 ? ['Can handle urgent request'] : []),
            'badges' => $score >= 4 ? ['Express Ready'] : [],
        ];
    }

    private function scoreVerification(Driver $driver): array
    {
        return match ($driver->verification_status) {
            'verified' => ['score' => 5, 'quality' => 'verified', 'reasons' => ['Driver verification approved'], 'badges' => ['Verified Driver']],
            'pending' => ['score' => 0, 'quality' => 'pending', 'reasons' => [], 'badges' => []],
            'rejected' => ['score' => 0, 'quality' => 'rejected', 'reasons' => [], 'badges' => []],
            default => ['score' => 1, 'quality' => 'legacy', 'reasons' => [], 'badges' => []],
        };
    }

    private function hasRelatedPackageSupport(?string $requestCategory, Collection $supportedCategories): bool
    {
        if (! $requestCategory) {
            return false;
        }

        $relatedGroups = [
            'documents' => ['fragile', 'commerce'],
            'fragile' => ['documents', 'commerce'],
            'commerce' => ['documents', 'fragile', 'furniture'],
            'furniture' => ['commerce', 'bulk'],
            'bulk' => ['furniture', 'heavy'],
            'heavy' => ['bulk', 'vehicles'],
            'vehicles' => ['heavy'],
            'refrigerated' => ['commerce'],
        ];

        return collect($relatedGroups[$requestCategory] ?? [])->intersect($supportedCategories)->isNotEmpty();
    }

    private function sharedCorridors(array $routeCities, array $requestCities): array
    {
        $routeCorridors = collect($routeCities)
            ->flatMap(fn ($city) => $this->corridorsForCity($city))
            ->unique();

        $requestCorridors = collect($requestCities)
            ->flatMap(fn ($city) => $this->corridorsForCity($city))
            ->unique();

        return $routeCorridors->intersect($requestCorridors)->values()->all();
    }

    private function corridorsForCity(?string $city): array
    {
        if (! $city) {
            return [];
        }

        return collect(self::CORRIDORS)
            ->filter(fn (array $cities) => in_array($city, $cities, true))
            ->keys()
            ->values()
            ->all();
    }

    private function packageCategory(?string $name): ?string
    {
        $normalized = $this->normalize($name);

        foreach (self::PACKAGE_GROUPS as $category => $labels) {
            if (in_array($normalized, array_map(fn ($label) => $this->normalize($label), $labels), true)) {
                return $category;
            }
        }

        return match (true) {
            Str::contains($normalized, ['document']) => 'documents',
            Str::contains($normalized, ['fragile', 'electronic']) => 'fragile',
            Str::contains($normalized, ['furniture', 'household']) => 'furniture',
            Str::contains($normalized, ['refrigerated', 'cold']) => 'refrigerated',
            Str::contains($normalized, ['bulk', 'construction']) => 'bulk',
            Str::contains($normalized, ['mining', 'heavy', 'industrial', 'machinery']) => 'heavy',
            Str::contains($normalized, ['vehicle']) => 'vehicles',
            Str::contains($normalized, ['livestock']) => 'livestock',
            Str::contains($normalized, ['food', 'retail']) => 'commerce',
            default => null,
        };
    }

    private function finalizeBadges(array $match): array
    {
        $badges = collect($match['badges']);

        if ($match['score'] >= 90) {
            $badges->prepend('Best Match');
        } elseif ($match['score'] >= 80) {
            $badges->prepend('Strong Match');
        }

        $priority = [
            'Best Match',
            'Strong Match',
            'Express Ready',
            'Heavy Load Ready',
            'Route Match',
            'Parcel Fit',
            'Vehicle Fit',
            'Available',
            'Low Load',
            'Recently Active',
            'Partial Route',
            'Corridor',
            'Related Fit',
        ];

        return $badges
            ->unique()
            ->sortBy(fn (string $badge) => array_search($badge, $priority, true) !== false ? array_search($badge, $priority, true) : 999)
            ->take(4)
            ->values()
            ->all();
    }

    private function labelForScore(int $score): string
    {
        return match (true) {
            $score >= 90 => 'Best Match',
            $score >= 80 => 'Strong Match',
            $score >= 65 => 'Good Match',
            default => 'Route Match',
        };
    }

    private function cacheKey(ParcelRequest $parcelRequest, int $limit): string
    {
        return sprintf(
            'driver-match:%s:%s:%s:%d',
            $parcelRequest->id,
            optional($parcelRequest->updated_at)->timestamp ?? now()->timestamp,
            $this->matchingStateVersion(),
            $limit
        );
    }

    private function matchingStateVersion(): string
    {
        $driverState = Driver::query()
            ->orderBy('id')
            ->get(['id', 'status', 'updated_at'])
            ->map(fn (Driver $driver) => implode(':', [
                $driver->id,
                $driver->status,
                optional($driver->updated_at)?->format('Y-m-d H:i:s.u') ?? '0',
            ]))
            ->implode('|');

        $routeState = DriverRoute::query()
            ->orderBy('id')
            ->get(['id', 'driver_id', 'vehicle_type', 'max_load_size', 'available', 'is_refrigerated', 'updated_at'])
            ->map(fn (DriverRoute $route) => implode(':', [
                $route->id,
                $route->driver_id,
                $route->vehicle_type,
                $route->max_load_size,
                $route->available ? '1' : '0',
                $route->is_refrigerated ? '1' : '0',
                optional($route->updated_at)?->format('Y-m-d H:i:s.u') ?? '0',
            ]))
            ->implode('|');

        $workloadState = ParcelRequest::query()
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->orderBy('id')
            ->get(['id', 'assigned_driver_id', 'status', 'updated_at'])
            ->map(fn (ParcelRequest $parcel) => implode(':', [
                $parcel->id,
                $parcel->assigned_driver_id,
                $parcel->status,
                optional($parcel->updated_at)?->format('Y-m-d H:i:s.u') ?? '0',
            ]))
            ->implode('|');

        return md5(implode('||', [$driverState, $routeState, $workloadState]));
    }

    private function normalize(?string $value): string
    {
        return Str::of((string) $value)->lower()->ascii()->replace('-', ' ')->squish()->toString();
    }
}
