<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\DriverAlert;
use App\Models\DriverRoute;
use App\Models\Location;
use App\Models\PackageType;
use App\Models\ParcelRequest;
use App\Models\User;
class DriverWorkspaceService
{
    public function __construct(
        private DriverVerificationService $driverVerificationService,
    ) {
    }

    public function build(User $user): array
    {
        $driver = $this->ensureDriver($user);
        $driver = $this->driverVerificationService->refreshStatus($driver->loadMissing(['primaryLicence', 'bankAccount']));
        $driverRoute = $this->ensureDriverRoute($driver);

        $alertCollection = $driver->alerts()
            ->with('parcelRequest.pickupLocation', 'parcelRequest.dropoffLocation', 'parcelRequest.packageType')
            ->latest()
            ->take(20)
            ->get();

        $alertLookup = $alertCollection
            ->filter(fn (DriverAlert $alert) => $alert->parcel_request_id)
            ->keyBy('parcel_request_id');

        $availableRequestCollection = ParcelRequest::query()
            ->with(['pickupLocation', 'dropoffLocation', 'packageType'])
            ->forDriverFeed($driver)
            ->latest()
            ->take(12)
            ->get();

        $activeDeliveryCollection = ParcelRequest::query()
            ->with(['pickupLocation', 'dropoffLocation', 'packageType', 'statusUpdates'])
            ->forActiveDriver($driver)
            ->latest('accepted_at')
            ->take(8)
            ->get();

        $completedDeliveryCollection = ParcelRequest::query()
            ->with(['pickupLocation', 'dropoffLocation', 'packageType'])
            ->where('assigned_driver_id', $driver->id)
            ->where('status', ParcelRequest::STATUS_DELIVERED)
            ->latest('delivered_at')
            ->take(8)
            ->get();

        $availableRequests = $availableRequestCollection
            ->map(fn (ParcelRequest $parcel) => $this->mapParcelCard($parcel, $driver, $alertLookup->get($parcel->id)))
            ->values();

        $activeDeliveries = $activeDeliveryCollection
            ->map(fn (ParcelRequest $parcel) => $this->mapParcelCard($parcel, $driver, $alertLookup->get($parcel->id), true))
            ->values();

        $completedDeliveries = $completedDeliveryCollection
            ->map(fn (ParcelRequest $parcel) => $this->mapParcelCard($parcel, $driver, $alertLookup->get($parcel->id)))
            ->values();

        $completedTodayCount = ParcelRequest::query()
            ->where('assigned_driver_id', $driver->id)
            ->whereDate('delivered_at', today())
            ->count();

        $acceptedTodayCount = ParcelRequest::query()
            ->where('assigned_driver_id', $driver->id)
            ->whereDate('accepted_at', today())
            ->count();

        $deliveryValueToday = (float) ParcelRequest::query()
            ->where('assigned_driver_id', $driver->id)
            ->whereDate('delivered_at', today())
            ->sum('final_price');

        $deliveryValueWeek = (float) ParcelRequest::query()
            ->where('assigned_driver_id', $driver->id)
            ->whereDate('delivered_at', '>=', now()->startOfWeek()->toDateString())
            ->sum('final_price');

        $routeNames = $driverRoute->locations->pluck('name')->values();
        $packageNames = $driverRoute->packages->pluck('name')->values();
        $profileCompletion = $this->profileCompletion($driver, $driverRoute);
        $primaryLicence = $driver->primaryLicence;
        $licenceExpiry = optional($primaryLicence?->expiry_date)->toDateString();
        $licenceExpiryState = $this->licenceExpiryState($primaryLicence?->expiry_date);

        $activityFeed = $alertCollection->take(8)->map(fn (DriverAlert $alert) => [
            'id' => $alert->id,
            'title' => $alert->title,
            'message' => $alert->message,
            'tone' => $this->mapSeverityTone($alert->severity),
            'time' => $alert->created_at?->diffForHumans(),
            'tracking_number' => $alert->parcelRequest?->tracking_number,
        ])->values();

        $activeVehicleLabel = trim(collect([$driverRoute->car_make, $driverRoute->car_model])->filter()->implode(' '))
            ?: ucfirst(str_replace('_', ' ', (string) $driverRoute->vehicle_type));

        return [
            'user' => $user->fresh(),
            'driver' => $driver,
            'driverRoute' => $driverRoute,
            'vehicle' => [
                'id' => $driverRoute->id,
                'vehicle_type' => $driverRoute->vehicle_type,
                'max_load_size' => $driverRoute->max_load_size,
                'is_refrigerated' => $driverRoute->is_refrigerated,
                'car_make' => $driverRoute->car_make,
                'car_model' => $driverRoute->car_model,
                'car_number' => $driverRoute->car_number,
                'available' => $driverRoute->available,
            ],
            'routes' => $driverRoute->locations,
            'selectedLocations' => $driverRoute->locations,
            'packages' => $driverRoute->packages,
            'locations' => Location::all(),
            'packageTypes' => PackageType::all(),
            'contactStats' => [
                'views' => $driver->profileViews()->count(),
                'saves' => $driver->savedContacts()->count(),
            ],
            'savedContacts' => $driver->savedContacts()->with('user')->get()->map(fn ($savedContact) => [
                'id' => $savedContact->user->id,
                'name' => $savedContact->user->name,
                'email' => $savedContact->user->email,
            ]),
            'alerts' => $alertCollection->take(8)->map(fn (DriverAlert $alert) => [
                'id' => $alert->id,
                'title' => $alert->title,
                'message' => $alert->message,
                'severity' => $alert->severity,
                'is_read' => $alert->is_read,
                'created_at' => $alert->created_at?->diffForHumans(),
                'meta' => $alert->meta,
                'tracking_number' => $alert->parcelRequest?->tracking_number,
            ])->values(),
            'availableRequests' => $availableRequests,
            'activeDeliveries' => $activeDeliveries,
            'completedDeliveries' => $completedDeliveries,
            'dashboardSummary' => [
                'available_requests' => $availableRequests->count(),
                'active_deliveries' => $activeDeliveries->count(),
                'completed_today' => $completedTodayCount,
                'delivery_value_today' => $deliveryValueToday,
                'delivery_value_week' => $deliveryValueWeek,
                'accepted_today' => $acceptedTodayCount,
                'availability_label' => $driverRoute->available ? 'Online' : 'Offline',
            ],
            'profileSnapshot' => [
                'name' => $user->name,
                'avatar' => $user->profile_photo_url,
                'vehicle' => $activeVehicleLabel,
                'trust_indicator' => $driver->verification_status === 'verified'
                    ? 'Verified'
                    : ($driver->savedContacts()->count() > 0 ? 'Trusted' : 'New'),
                'active_routes_count' => $routeNames->count(),
                'accepted_today' => $acceptedTodayCount,
            ],
            'driverStatusPanel' => [
                'available' => $driverRoute->available,
                'availability_label' => $driverRoute->available ? 'Online' : 'Offline',
                'routes' => $routeNames,
                'package_types' => $packageNames,
                'vehicle_type' => ucfirst(str_replace('_', ' ', (string) $driverRoute->vehicle_type)),
                'capacity' => ucfirst((string) $driverRoute->max_load_size),
                'is_refrigerated' => $driverRoute->is_refrigerated,
                'incomplete_profile' => $profileCompletion['score'] < 100,
                'verification_status' => $driver->verification_status,
                'verification_expiry' => $licenceExpiry,
                'verification_expiry_state' => $licenceExpiryState,
                'banking_status' => $driver->bankAccount?->status ?? 'incomplete',
                'profile_completion' => $profileCompletion['score'],
            ],
            'activityFeed' => $activityFeed,
            'workspaceHero' => [
                'driver_name' => $user->name,
                'status' => $driverRoute->available ? 'Online' : 'Offline',
                'verification_status' => $driver->verification_status,
                'vehicle_label' => $activeVehicleLabel,
                'route_summary' => $routeNames->take(2)->implode(' -> ') ?: 'Add route coverage',
                'active_routes_count' => $routeNames->count(),
                'urgent_opportunities' => $availableRequests->whereIn('urgency_level', ['express', 'same_day'])->count(),
            ],
            'earningsSummary' => [
                'today_value' => $deliveryValueToday,
                'week_value' => $deliveryValueWeek,
                'completed_today' => $completedTodayCount,
                'accepted_today' => $acceptedTodayCount,
                'active_jobs' => $activeDeliveries->count(),
            ],
            'complianceSummary' => [
                'score' => $profileCompletion['score'],
                'items' => $profileCompletion['items'],
                'verification_status' => $driver->verification_status,
                'banking_status' => $driver->bankAccount?->status ?? 'incomplete',
                'licence_expiry' => $licenceExpiry,
                'licence_expiry_state' => $licenceExpiryState,
            ],
            'quickActions' => [
                ['label' => $driverRoute->available ? 'Go Offline' : 'Go Online', 'action' => 'toggle_availability', 'tone' => $driverRoute->available ? 'dark' : 'brand'],
                ['label' => 'Update Routes', 'href' => route('driver.routes'), 'tone' => 'neutral'],
                ['label' => 'Update Vehicle', 'href' => route('driver.routes'), 'tone' => 'neutral'],
                ['label' => 'Update Banking', 'href' => route('driver.profile') . '#banking', 'tone' => 'neutral'],
                ['label' => 'Upload Licence', 'href' => route('driver.profile') . '#verification', 'tone' => 'neutral'],
                ['label' => 'View Full Profile', 'href' => route('driver.profile'), 'tone' => 'neutral'],
            ],
            'marketSignals' => [
                'urgent_requests' => $availableRequests->whereIn('urgency_level', ['express', 'same_day'])->count(),
                'high_value_requests' => $availableRequests->filter(fn (array $parcel) => (float) ($parcel['estimated_price'] ?? 0) >= 1200)->count(),
                'active_deliveries' => $activeDeliveries->count(),
            ],
        ];
    }

    private function ensureDriver(User $user): Driver
    {
        return $user->driver ?? Driver::create([
            'user_id' => $user->id,
            'phone' => $user->phone,
            'location' => $user->location,
            'status' => 'active',
        ]);
    }

    private function ensureDriverRoute(Driver $driver): DriverRoute
    {
        $driverRoute = $driver->driverRoutes()->with(['locations', 'packages'])->first();

        if (! $driverRoute) {
            $driverRoute = DriverRoute::create([
                'driver_id' => $driver->id,
                'vehicle_type' => 'bakkie',
                'max_load_size' => 'medium',
                'is_refrigerated' => false,
                'car_make' => null,
                'car_model' => null,
                'car_number' => null,
                'available' => false,
            ]);
        }

        return $driverRoute->loadMissing(['locations', 'packages']);
    }

    private function mapParcelCard(ParcelRequest $parcel, Driver $driver, ?DriverAlert $alert = null, bool $includeTimeline = false): array
    {
        $matchScore = (int) data_get($alert?->meta, 'match_score', 0);
        $estimatedPrice = (float) ($parcel->final_price ?: $parcel->total_price ?: 0);
        $nextSteps = ParcelRequest::driverTransitionsFor($parcel->status);

        return [
            'id' => $parcel->id,
            'tracking_number' => $parcel->tracking_number,
            'pickup_location' => $parcel->pickupLocation?->name,
            'dropoff_location' => $parcel->dropoffLocation?->name,
            'pickup_address' => $parcel->pickup_address,
            'dropoff_address' => $parcel->dropoff_address,
            'package_type' => $parcel->packageType?->name,
            'receiver_name' => $parcel->receiver_name,
            'receiver_phone' => $parcel->receiver_phone,
            'notes' => $parcel->notes,
            'load_size' => $parcel->load_size,
            'urgency_level' => $parcel->urgency_level,
            'weight_kg' => $parcel->weight_kg,
            'status' => $parcel->status,
            'status_label' => $parcel->currentStatusLabel(),
            'estimated_price' => $estimatedPrice,
            'estimated_payout' => round($estimatedPrice * 0.82, 2),
            'client_offer_price' => $parcel->client_offer_price,
            'total_price' => $estimatedPrice,
            'time_posted' => $parcel->created_at?->diffForHumans(),
            'accepted_time' => $parcel->accepted_at?->diffForHumans(),
            'delivered_time' => $parcel->delivered_at?->diffForHumans(),
            'assigned_driver_id' => $parcel->assigned_driver_id,
            'can_accept' => $parcel->status === ParcelRequest::STATUS_MATCHED && ! $parcel->assigned_driver_id,
            'next_steps' => $nextSteps,
            'next_action' => $nextSteps[0] ?? null,
            'eta_summary' => $parcel->estimated_hours ? round((float) $parcel->estimated_hours, 1) . ' hrs' : null,
            'timeline' => $includeTimeline ? $parcel->statusUpdates->take(5)->map(fn ($update) => [
                'id' => $update->id,
                'title' => $update->title,
                'status' => $update->status,
                'time' => $update->created_at?->diffForHumans(),
            ])->values() : [],
            'match_context' => [
                'route' => ($parcel->pickupLocation?->name ?? 'Pickup') . ' -> ' . ($parcel->dropoffLocation?->name ?? 'Dropoff'),
                'is_assigned_to_me' => $parcel->assigned_driver_id === $driver->id,
                'match_score' => $matchScore,
                'route_summary' => data_get($alert?->meta, 'route_summary'),
                'reasons' => data_get($alert?->meta, 'match_reasons', []),
                'badges' => data_get($alert?->meta, 'match_badges', []),
                'label' => data_get($alert?->meta, 'match_label'),
                'breakdown' => data_get($alert?->meta, 'match_breakdown', []),
            ],
        ];
    }

    private function mapSeverityTone(?string $severity): string
    {
        return match ($severity) {
            'success' => 'success',
            'warning' => 'warning',
            'error' => 'danger',
            default => 'info',
        };
    }

    private function licenceExpiryState($expiryDate): string
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

    private function profileCompletion(Driver $driver, DriverRoute $driverRoute): array
    {
        $licence = $driver->primaryLicence;
        $licenceReady = (bool) $licence && $this->licenceExpiryState($licence->expiry_date) !== 'expired';
        $verificationReady = $driver->verification_status === 'verified';
        $items = [
            ['label' => 'Verification', 'complete' => $verificationReady],
            ['label' => 'Licence uploaded', 'complete' => $licenceReady],
            ['label' => 'Banking added', 'complete' => filled($driver->bankAccount?->account_number_last4)],
            ['label' => 'Routes configured', 'complete' => $driverRoute->locations->isNotEmpty()],
            ['label' => 'Vehicle configured', 'complete' => filled($driverRoute->vehicle_type)],
        ];

        $score = (int) round((collect($items)->where('complete', true)->count() / max(count($items), 1)) * 100);

        return [
            'score' => $score,
            'items' => collect($items)->map(fn (array $item) => [
                ...$item,
                'tone' => $item['complete'] ? 'success' : 'warning',
            ])->all(),
        ];
    }
}
