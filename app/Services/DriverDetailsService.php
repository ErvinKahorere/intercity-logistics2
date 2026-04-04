<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\ParcelRequest;
use App\Models\User;

class DriverDetailsService
{
    public function build(Driver $driver, ?User $viewer = null): array
    {
        $driver->loadMissing([
            'user',
            'driverRoutes.locations',
            'driverRoutes.packages',
            'primaryLicence',
            'licences',
            'bankAccount',
            'profileViews',
            'savedBy',
        ]);

        $viewerMode = $this->viewerMode($driver, $viewer);
        $routes = $driver->driverRoutes;
        $primaryRoute = $routes->first();
        $activeRequests = ParcelRequest::query()
            ->with(['pickupLocation:id,name', 'dropoffLocation:id,name', 'packageType:id,name'])
            ->where('assigned_driver_id', $driver->id)
            ->whereIn('status', [
                ParcelRequest::STATUS_ACCEPTED,
                ParcelRequest::STATUS_PICKED_UP,
                ParcelRequest::STATUS_IN_TRANSIT,
                ParcelRequest::STATUS_ARRIVED,
            ])
            ->latest('accepted_at')
            ->take(4)
            ->get();

        $history = ParcelRequest::query()
            ->with(['pickupLocation:id,name', 'dropoffLocation:id,name', 'packageType:id,name'])
            ->where('assigned_driver_id', $driver->id)
            ->latest('updated_at')
            ->take(6)
            ->get();

        $completedCount = ParcelRequest::query()
            ->where('assigned_driver_id', $driver->id)
            ->where('status', ParcelRequest::STATUS_DELIVERED)
            ->count();

        $acceptedRequests = ParcelRequest::query()
            ->where('assigned_driver_id', $driver->id)
            ->whereNotNull('accepted_at')
            ->get(['id', 'created_at', 'accepted_at']);

        $acceptedCount = $acceptedRequests->count();
        $matchedCount = ParcelRequest::query()
            ->whereJsonContains('matched_driver_ids', $driver->id)
            ->count();

        $completionRate = $acceptedCount > 0 ? round(($completedCount / $acceptedCount) * 100) : 100;
        $activeJobs = $activeRequests->count();
        $responseHours = $acceptedRequests
            ->filter(fn (ParcelRequest $parcel) => $parcel->created_at && $parcel->accepted_at)
            ->map(fn (ParcelRequest $parcel) => $parcel->created_at->diffInMinutes($parcel->accepted_at) / 60)
            ->avg();

        $trustScore = $this->trustScore($driver, $completionRate, $activeJobs);
        $licence = $driver->primaryLicence;

        return [
            'viewer_mode' => $viewerMode,
            'profile' => [
                'id' => $driver->id,
                'name' => $driver->user?->name,
                'email' => $viewerMode !== 'customer' ? $driver->user?->email : null,
                'phone' => $viewerMode !== 'customer' ? ($driver->phone ?: $driver->user?->phone) : null,
                'image' => $driver->user?->profile_photo_url,
                'location' => $driver->location ?: $driver->user?->location,
                'designation' => $driver->designation ?: 'Intercity logistics driver',
                'speciality' => $driver->speciality,
                'about' => $driver->about ?: 'Professional route driver handling city-to-city deliveries with operational discipline and customer care.',
                'verification_status' => $driver->verification_status,
                'available' => (bool) $primaryRoute?->available,
                'vehicle_type' => $primaryRoute?->vehicle_type,
                'vehicle_label' => trim(collect([$primaryRoute?->car_make, $primaryRoute?->car_model])->filter()->implode(' '))
                    ?: ucfirst(str_replace('_', ' ', (string) $primaryRoute?->vehicle_type)),
                'home_base' => $driver->location ?: $driver->user?->location,
                'route_summary' => $routes->flatMap(fn ($route) => $route->locations->pluck('name'))->unique()->take(3)->implode(' -> '),
                'active_workload' => $activeJobs,
                'trust_score' => $trustScore,
                'trust_label' => $trustScore >= 92 ? 'Verified and reliable' : ($trustScore >= 80 ? 'Trusted route partner' : 'Building delivery track record'),
            ],
            'overview' => [
                'routes_served' => $routes->flatMap(fn ($route) => $route->locations->pluck('name'))->unique()->values()->all(),
                'preferred_parcel_types' => $routes->flatMap(fn ($route) => $route->packages->pluck('name'))->unique()->values()->all(),
                'vehicle_capacity' => $primaryRoute?->max_load_size,
                'is_refrigerated' => (bool) $primaryRoute?->is_refrigerated,
                'current_workload' => $activeJobs,
                'operating_region' => $driver->location ?: $driver->user?->location,
            ],
            'capabilities' => [
                'vehicle_type' => $primaryRoute?->vehicle_type,
                'vehicle_make' => $primaryRoute?->car_make,
                'vehicle_model' => $primaryRoute?->car_model,
                'vehicle_plate' => $viewerMode === 'customer' ? null : $primaryRoute?->car_number,
                'max_load_size' => $primaryRoute?->max_load_size,
                'supports_refrigerated' => (bool) $primaryRoute?->is_refrigerated,
                'parcel_types' => $routes->flatMap(fn ($route) => $route->packages->pluck('name'))->unique()->values()->all(),
                'badges' => $this->capabilityBadges($routes->flatMap(fn ($route) => $route->packages->pluck('name'))->unique()->values()->all(), (bool) $primaryRoute?->is_refrigerated),
            ],
            'verification' => [
                'status' => $driver->verification_status,
                'verified_at' => optional($driver->verified_at)->toDateString(),
                'licence_type' => $licence?->licence_type_name,
                'licence_number' => $viewerMode === 'customer' ? null : $licence?->licence_number,
                'issue_date' => optional($licence?->issue_date)->toDateString(),
                'expiry_date' => optional($licence?->expiry_date)->toDateString(),
                'expiry_state' => $this->expiryState($licence?->expiry_date),
                'document_url' => in_array($viewerMode, ['self', 'admin'], true) ? $licence?->document_url : null,
                'rejection_reason' => in_array($viewerMode, ['self', 'admin'], true) ? ($licence?->rejection_reason ?: $driver->verification_rejection_reason) : null,
                'primary_licence_id' => in_array($viewerMode, ['self', 'admin'], true) ? $licence?->id : null,
            ],
            'performance' => [
                'completion_rate' => $completionRate,
                'acceptance_rate' => $matchedCount > 0 ? min(100, round(($acceptedCount / $matchedCount) * 100)) : 0,
                'completed_deliveries' => $completedCount,
                'active_jobs' => $activeJobs,
                'response_speed' => $responseHours ? round((float) $responseHours, 1) . ' hrs' : 'New',
                'rating' => number_format(max(4.2, min(4.9, ($trustScore / 20))), 1),
                'profile_views' => $driver->profileViews()->count(),
                'saved_count' => $driver->savedBy()->count(),
            ],
            'routes' => $routes->map(fn ($route) => [
                'id' => $route->id,
                'vehicle_type' => $route->vehicle_type,
                'active' => (bool) $route->available,
                'summary' => $route->locations->pluck('name')->implode(' -> '),
                'locations' => $route->locations->pluck('name')->values()->all(),
                'packages' => $route->packages->pluck('name')->values()->all(),
            ])->values()->all(),
            'recent_activity' => $history->map(fn (ParcelRequest $parcel) => [
                'id' => $parcel->id,
                'tracking_number' => $parcel->tracking_number,
                'route' => ($parcel->pickupLocation?->name ?? 'Pickup') . ' -> ' . ($parcel->dropoffLocation?->name ?? 'Destination'),
                'parcel_type' => $parcel->packageType?->name,
                'status' => $parcel->status,
                'status_label' => $parcel->currentStatusLabel(),
                'updated_at' => optional($parcel->updated_at)->diffForHumans(),
            ])->values()->all(),
            'active_jobs' => $activeRequests->map(fn (ParcelRequest $parcel) => [
                'id' => $parcel->id,
                'tracking_number' => $parcel->tracking_number,
                'route' => ($parcel->pickupLocation?->name ?? 'Pickup') . ' -> ' . ($parcel->dropoffLocation?->name ?? 'Destination'),
                'parcel_type' => $parcel->packageType?->name,
                'status' => $parcel->status,
                'status_label' => $parcel->currentStatusLabel(),
            ])->values()->all(),
            'banking' => in_array($viewerMode, ['self', 'admin'], true) ? [
                'status' => $driver->bankAccount?->status ?? 'incomplete',
                'bank_name' => $driver->bankAccount?->bank_name,
                'account_holder_name' => $driver->bankAccount?->account_holder_name,
                'masked_account_number' => $driver->bankAccount?->masked_account_number,
                'account_type' => $driver->bankAccount?->account_type,
            ] : null,
            'documents' => in_array($viewerMode, ['self', 'admin'], true) ? [
                'licences' => $driver->licences->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->licence_type_name,
                    'status' => $item->verification_status,
                    'expiry_date' => optional($item->expiry_date)->toDateString(),
                    'document_url' => $item->document_url,
                ])->values()->all(),
            ] : null,
            'actions' => [
                'can_select' => $viewerMode === 'customer',
                'can_review' => $viewerMode === 'admin',
                'can_edit' => $viewerMode === 'self',
            ],
        ];
    }

    private function viewerMode(Driver $driver, ?User $viewer): string
    {
        if (! $viewer) {
            return 'customer';
        }

        if ($viewer->hasRole('admin')) {
            return 'admin';
        }

        if ($viewer->driver && (int) $viewer->driver->id === (int) $driver->id) {
            return 'self';
        }

        return 'customer';
    }

    private function capabilityBadges(array $parcelTypes, bool $isRefrigerated): array
    {
        $badges = [];
        $types = collect($parcelTypes)->map(fn ($item) => strtolower((string) $item));

        if ($isRefrigerated) {
            $badges[] = 'Refrigerated';
        }
        if ($types->contains(fn ($item) => str_contains($item, 'fragile'))) {
            $badges[] = 'Fragile';
        }
        if ($types->contains(fn ($item) => str_contains($item, 'vehicle'))) {
            $badges[] = 'Vehicle Loads';
        }
        if ($types->contains(fn ($item) => str_contains($item, 'bulk') || str_contains($item, 'mining') || str_contains($item, 'heavy'))) {
            $badges[] = 'Heavy Loads';
        }
        if ($types->contains(fn ($item) => str_contains($item, 'document'))) {
            $badges[] = 'Documents';
        }

        return array_values(array_unique($badges));
    }

    private function expiryState($expiryDate): string
    {
        if (! $expiryDate) {
            return 'unknown';
        }

        $days = now()->startOfDay()->diffInDays($expiryDate->startOfDay(), false);
        if ($days < 0) {
            return 'expired';
        }
        if ($days <= 30) {
            return 'expiring';
        }

        return 'valid';
    }

    private function trustScore(Driver $driver, int $completionRate, int $activeJobs): int
    {
        $base = match ($driver->verification_status) {
            'verified' => 86,
            'pending' => 72,
            'rejected' => 58,
            default => 66,
        };

        return max(52, min(98, $base + (int) round(($completionRate - 80) / 5) - ($activeJobs > 3 ? 4 : 0)));
    }
}
