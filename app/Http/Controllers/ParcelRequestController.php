<?php

namespace App\Http\Controllers;

use App\Models\CityRoute;
use App\Models\Driver;
use App\Models\Location;
use App\Models\PackageType;
use App\Models\ParcelRequest;
use App\Services\DriverMatchService;
use App\Services\ParcelWorkflowService;
use App\Services\PricingService;
use App\Services\QuotationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class ParcelRequestController extends Controller
{
    public function __construct(
        private ParcelWorkflowService $workflowService,
    ) {
    }

    public function create(): Response
    {
        return Inertia::render('ParcelRequestBooking', [
            'locations' => Location::orderBy('name')->get(['id', 'name']),
            'packageTypes' => PackageType::orderBy('name')->get(['id', 'name']),
            'cityRoutes' => CityRoute::query()
                ->with(['originLocation:id,name', 'destinationLocation:id,name'])
                ->where('is_active', true)
                ->orderByDesc('is_featured')
                ->orderBy('distance_km')
                ->get()
                ->map(fn (CityRoute $route) => [
                    'id' => $route->id,
                    'origin_location_id' => $route->origin_location_id,
                    'destination_location_id' => $route->destination_location_id,
                    'origin_name' => $route->originLocation?->name,
                    'destination_name' => $route->destinationLocation?->name,
                    'distance_km' => $route->distance_km,
                    'estimated_hours' => $route->estimated_hours,
                    'base_fare' => $route->base_fare,
                    'is_featured' => $route->is_featured,
                ]),
        ]);
    }

    public function confirmSelection(Request $request): Response
    {
        $validated = $request->validate([
            'pickup' => ['required', 'exists:locations,id'],
            'destination' => ['required', 'exists:locations,id', 'different:pickup'],
            'parcel' => ['required', 'exists:package_types,id'],
            'driver' => ['required', 'exists:drivers,id'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'load' => ['nullable', 'in:small,medium,large,heavy,oversized'],
            'urgency' => ['nullable', 'in:standard,express,same_day'],
        ]);

        return Inertia::render('DriverSelectionConfirm', [
            'selection' => [
                'pickup_location_id' => (int) $validated['pickup'],
                'dropoff_location_id' => (int) $validated['destination'],
                'package_type_id' => (int) $validated['parcel'],
                'selected_driver_id' => (int) $validated['driver'],
                'weight_kg' => Arr::get($validated, 'weight'),
                'load_size' => Arr::get($validated, 'load', 'medium'),
                'urgency_level' => Arr::get($validated, 'urgency', 'standard'),
            ],
            'routeLabels' => [
                'pickup' => Location::query()->find($validated['pickup'], ['id', 'name']),
                'dropoff' => Location::query()->find($validated['destination'], ['id', 'name']),
                'packageType' => PackageType::query()->find($validated['parcel'], ['id', 'name']),
                'driver' => Driver::query()->with('user:id,name,profile_photo_path')->find($validated['driver'], ['id', 'user_id']),
            ],
        ]);
    }


public function preview(
    Request $request,
    DriverMatchService $driverMatchService,
    PricingService $pricingService,
    QuotationService $quotationService,
): JsonResponse {
    $validated = $request->validate([
        'pickup_location_id' => ['required', 'exists:locations,id', 'different:dropoff_location_id'],
        'dropoff_location_id' => ['required', 'exists:locations,id'],
        'package_type_id' => ['required', 'exists:package_types,id'],
        'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        'load_size' => ['required', 'in:small,medium,large,heavy,oversized'],
        'urgency_level' => ['required', 'in:standard,express,same_day'],
        'notes' => ['nullable', 'string', 'max:2000'],
        'limit' => ['nullable', 'integer', 'min:1', 'max:24'],
        'selected_driver_id' => ['nullable', 'exists:drivers,id'],
    ]);

    $parcel = new ParcelRequest([
        'pickup_location_id' => $validated['pickup_location_id'],
        'dropoff_location_id' => $validated['dropoff_location_id'],
        'package_type_id' => $validated['package_type_id'],
        'weight_kg' => $validated['weight_kg'] ?? null,
        'load_size' => $validated['load_size'],
        'urgency_level' => $validated['urgency_level'],
        'status' => ParcelRequest::STATUS_PENDING,
    ]);

    $parcel->setRelation('pickupLocation', Location::query()->find($validated['pickup_location_id'], ['id', 'name']));
    $parcel->setRelation('dropoffLocation', Location::query()->find($validated['dropoff_location_id'], ['id', 'name']));
    $parcel->setRelation('packageType', PackageType::query()->find($validated['package_type_id'], ['id', 'name']));

    $quote = $pricingService->quote(
        (int) $validated['pickup_location_id'],
        (int) $validated['dropoff_location_id'],
        (int) $validated['package_type_id'],
        isset($validated['weight_kg']) ? (float) $validated['weight_kg'] : null,
        $validated['urgency_level'],
        $validated['load_size'],
        $validated['notes'] ?? null,
    );

    $parcel->fill($quote);

    $drivers = $driverMatchService
        ->match($parcel, (int) ($validated['limit'] ?? 3))
        ->map(function ($driver) {
            $route = $driver->bestRoute ?? $driver->driverRoutes->first();
            $vehicle = trim(collect([$route?->car_make, $route?->car_model])->filter()->join(' ')) ?: ($route?->vehicle_type ?? 'Delivery vehicle');
            $parcelSpecialties = $route?->packages?->map(fn ($pkg) => [
                'id' => $pkg->id,
                'name' => $pkg->name,
            ])->values()->all() ?? [];
            $routeLocations = $route?->locations?->map(fn ($location) => [
                'id' => $location->id,
                'name' => $location->name,
            ])->values()->all() ?? [];

            return [
                'id' => $driver->id,
                'name' => $driver->user?->name ?? 'Driver',
                'phone' => $driver->user?->phone ?? $driver->phone,
                'email' => $driver->user?->email,
                'image' => $driver->user?->profile_photo_url,
                'status' => $driver->status,
                'available_now' => (bool) ($route?->available && $driver->status === 'active'),
                'vehicle' => $vehicle,
                'vehicle_type' => $route?->vehicle_type,
                'plate_label' => $route?->car_number,
                'max_load_size' => $route?->max_load_size,
                'is_refrigerated' => (bool) ($route?->is_refrigerated),
                'match_score' => (int) ($driver->match_score ?? 0),
                'match_label' => $driver->match_label ?? 'Match',
                'badges' => array_values(array_slice($driver->match_badges ?? [], 0, 3)),
                'reasons' => array_values(array_slice($driver->match_reasons ?? [], 0, 5)),
                'route_summary' => $driver->matching_route_summary ?? ($route?->locations?->pluck('name')->join(' -> ') ?: ''),
                'route_locations' => $routeLocations,
                'parcel_specialties' => $parcelSpecialties,
                'match_breakdown' => $driver->match_breakdown ?? [],
                'match_explanation' => $driver->match_explanation ?? [],
            ];
        })
        ->values();

    return response()->json([
        'quote' => $quote,
        'quote_expires_at' => now()->addMinutes(15)->toIso8601String(),
        'quotation' => $request->user() && $request->boolean('persist_quote')
            ? $quotationService->createFromPreview($request->user(), $validated + [
                'selected_driver_id' => $validated['selected_driver_id'] ?? null,
                'pickup_address' => $request->string('pickup_address')->value(),
                'dropoff_address' => $request->string('dropoff_address')->value(),
                'receiver_name' => $request->string('receiver_name')->value(),
                'receiver_phone' => $request->string('receiver_phone')->value(),
                'notes' => $request->string('notes')->value(),
            ])->load(['pickupLocation', 'dropoffLocation', 'packageType', 'driver.user'])
            : null,
        'drivers' => $drivers,
        'selected_driver' => isset($validated['selected_driver_id'])
            ? $drivers->firstWhere('id', (int) $validated['selected_driver_id'])
            : null,
    ]);
}

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'pickup_location_id' => ['required', 'exists:locations,id', 'different:dropoff_location_id'],
            'dropoff_location_id' => ['required', 'exists:locations,id'],
            'package_type_id' => ['required', 'exists:package_types,id'],
            'pickup_address' => ['nullable', 'string', 'max:255'],
            'dropoff_address' => ['nullable', 'string', 'max:255'],
            'receiver_name' => ['required', 'string', 'max:255'],
            'receiver_phone' => ['required', 'string', 'max:30'],
            'weight_kg' => ['nullable', 'numeric', 'min:0.1', 'max:100000'],
            'load_size' => ['required', 'in:small,medium,large,heavy,oversized'],
            'urgency_level' => ['required', 'in:standard,express,same_day'],
            'client_offer_price' => ['nullable', 'numeric', 'min:1', 'max:100000'],
            'declared_value' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'selected_driver_id' => ['nullable', 'exists:drivers,id'],
            'confirmation_flow' => ['nullable', 'in:driver_selection'],
        ]);

        if (($validated['confirmation_flow'] ?? null) === 'driver_selection' && empty($validated['selected_driver_id'])) {
            throw ValidationException::withMessages([
                'selected_driver_id' => 'Please go back and select a driver before confirming this booking.',
            ]);
        }

        try {
            $parcelRequest = $this->workflowService->createRequest($request->user(), $validated);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'selected_driver_id' => $exception->getMessage(),
            ]);
        }

        $serializedParcel = $this->serializeParcel($parcelRequest->id, $request->user()->id);

        $matchedCount = count($parcelRequest->matched_driver_ids ?? []);
        $offerNote = $parcelRequest->client_offer_price
            ? ' Your offer was shared with matching drivers.'
            : '';
        $successMessage = ($validated['confirmation_flow'] ?? null) === 'driver_selection'
            ? 'Booking confirmed and moved into the payment-ready stage.'
            : ($matchedCount > 0
            ? "Parcel request created and sent to {$matchedCount} matching driver(s).{$offerNote}"
            : 'Parcel request created. It is pending until a suitable driver becomes available.');

        $isInertiaRequest = $request->header('X-Inertia') === 'true';

        if (! $isInertiaRequest && ($request->expectsJson() || $request->ajax())) {
            return response()->json([
                'message' => $successMessage,
                'parcel' => $serializedParcel,
                'next_actions' => [
                    'track' => route('user.parcels.index'),
                    'requests' => route('user.parcels.index'),
                    'book_another' => route('welcome'),
                    'payment_ready' => route('parcel-requests.payment-ready', ['parcelRequest' => $parcelRequest->id]),
                ],
            ]);
        }

        return redirect()
            ->route(
                ($validated['confirmation_flow'] ?? null) === 'driver_selection'
                    ? 'parcel-requests.payment-ready'
                    : 'user.parcels.index',
                ($validated['confirmation_flow'] ?? null) === 'driver_selection' ? ['parcelRequest' => $parcelRequest->id] : []
            )
            ->with('success', $successMessage);
    }

    public function paymentReady(Request $request, ParcelRequest $parcelRequest): Response
    {
        abort_unless($parcelRequest->user_id === $request->user()->id, 403);

        $serialized = $this->serializeParcel($parcelRequest->id, $request->user()->id);

        abort_unless($serialized, 404);

        return Inertia::render('ParcelPaymentReady', [
            'parcel' => $serialized,
        ]);
    }

    public function index(Request $request): Response
    {
        return Inertia::render('User/Parcels', [
            'parcelRequests' => $this->serializeUserParcels($request->user()->id),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        return response()->json([
            'parcelRequests' => $this->serializeUserParcels($request->user()->id),
        ]);
    }

    private function serializeUserParcels(int $userId)
    {
        return ParcelRequest::query()
            ->with([
                'cityRoute.originLocation',
                'cityRoute.destinationLocation',
                'pickupLocation',
                'dropoffLocation',
                'packageType',
                'assignedDriver.user',
                'assignedDriver.driverRoutes',
                'driverAlerts.driver.user',
                'driverAlerts.driver.driverRoutes',
                'statusUpdates',
                'latestQuotation',
                'latestInvoice',
            ])
            ->where('user_id', $userId)
            ->latest()
            ->get()
            ->map(fn (ParcelRequest $parcel) => $this->serializeParcelModel($parcel));
    }

    private function serializeParcel(int $parcelId, int $userId): ?array
    {
        $parcel = ParcelRequest::query()
            ->with([
                'cityRoute.originLocation',
                'cityRoute.destinationLocation',
                'pickupLocation',
                'dropoffLocation',
                'packageType',
                'assignedDriver.user',
                'assignedDriver.driverRoutes',
                'driverAlerts.driver.user',
                'driverAlerts.driver.driverRoutes',
                'statusUpdates',
                'latestQuotation',
                'latestInvoice',
            ])
            ->where('user_id', $userId)
            ->find($parcelId);

        return $parcel ? $this->serializeParcelModel($parcel) : null;
    }

    private function serializeParcelModel(ParcelRequest $parcel): array
    {
        $selectionMeta = $this->selectionMeta($parcel);
        $preferredDriverId = Arr::get($selectionMeta, 'preferred_driver_id');
        $preferredAlert = $preferredDriverId
            ? $parcel->driverAlerts->firstWhere('driver_id', $preferredDriverId)
            : null;
        $preferredDriver = $preferredAlert?->driver;
        $preferredRoute = $preferredDriver?->driverRoutes?->first();
        $assignedDriver = $parcel->assignedDriver;
        $assignedRoute = $assignedDriver?->driverRoutes?->first();

        return [
            'id' => $parcel->id,
            'tracking_number' => $parcel->tracking_number,
            'status' => $parcel->status,
            'status_label' => $parcel->currentStatusLabel(),
            'pickup_location' => $parcel->pickupLocation,
            'dropoff_location' => $parcel->dropoffLocation,
            'package_type' => $parcel->packageType,
            'load_size' => $parcel->load_size,
            'urgency_level' => $parcel->urgency_level,
            'weight_kg' => $parcel->weight_kg,
            'receiver_name' => $parcel->receiver_name,
            'receiver_phone' => $parcel->receiver_phone,
            'pickup_address' => $parcel->pickup_address,
            'dropoff_address' => $parcel->dropoff_address,
            'notes' => $parcel->notes,
            'distance_km' => $parcel->distance_km,
            'estimated_hours' => $parcel->estimated_hours,
            'base_price' => $parcel->base_price,
            'distance_fee' => $parcel->distance_fee,
            'weight_surcharge' => $parcel->weight_surcharge,
            'urgency_surcharge' => $parcel->urgency_surcharge,
            'special_handling_fee' => $parcel->special_handling_fee,
            'total_price' => $parcel->total_price,
            'estimated_total' => $parcel->client_offer_price ?: $parcel->final_price ?: $parcel->total_price,
            'estimated_price' => $parcel->total_price,
            'client_offer_price' => $parcel->client_offer_price,
            'final_price' => $parcel->final_price,
            'pricing_breakdown' => $parcel->pricing_breakdown,
            'declared_value' => $parcel->declared_value,
            'selection_meta' => $selectionMeta,
            'booking_reference' => Arr::get($selectionMeta, 'booking_reference'),
            'selected_driver_id' => $preferredDriverId ?: $parcel->assigned_driver_id,
            'payment_status' => Arr::get($selectionMeta, 'payment_status', 'pending'),
            'payment_state' => Arr::get($selectionMeta, 'payment_state'),
            'booking_status' => Arr::get($selectionMeta, 'booking_status', $parcel->status),
            'booking_status_label' => Arr::get($selectionMeta, 'booking_status_label', $parcel->currentStatusLabel()),
            'request_status' => $parcel->status,
            'payment_methods' => Arr::get($selectionMeta, 'payment_methods', []),
            'quotation' => $parcel->latestQuotation ? [
                'id' => $parcel->latestQuotation->id,
                'quotation_number' => $parcel->latestQuotation->quotation_number,
                'status' => $parcel->latestQuotation->status,
                'issue_date' => optional($parcel->latestQuotation->issue_date)->toDateString(),
                'expires_at' => optional($parcel->latestQuotation->expires_at)->toDateString(),
                'driver_snapshot' => $parcel->latestQuotation->driver_snapshot,
            ] : null,
            'invoice' => $parcel->latestInvoice ? [
                'id' => $parcel->latestInvoice->id,
                'invoice_number' => $parcel->latestInvoice->invoice_number,
                'status' => $parcel->latestInvoice->status,
                'payment_status' => $parcel->latestInvoice->payment_status,
                'issue_date' => optional($parcel->latestInvoice->issue_date)->toDateString(),
                'driver_snapshot' => $parcel->latestInvoice->driver_snapshot,
            ] : null,
            'matched_driver_ids' => $parcel->matched_driver_ids,
            'matched_driver_count' => $parcel->driverAlerts->count(),
            'matched_drivers_preview' => $parcel->driverAlerts
                ->sortByDesc(fn ($alert) => (int) data_get($alert->meta, 'match_score', 0))
                ->take(3)
                ->map(fn ($alert) => [
                    'id' => $alert->driver?->id,
                    'name' => $alert->driver?->user?->name,
                    'phone' => $alert->driver?->phone,
                    'score' => (int) data_get($alert->meta, 'match_score', 0),
                    'label' => data_get($alert->meta, 'match_label', 'Route Match'),
                    'badges' => data_get($alert->meta, 'match_badges', []),
                    'reasons' => data_get($alert->meta, 'match_reasons', []),
                    'route_summary' => data_get($alert->meta, 'route_summary'),
                    'vehicle_type' => optional($alert->driver?->driverRoutes?->first())->vehicle_type,
                ])
                ->filter(fn (array $driver) => ! empty($driver['id']))
                ->values(),
            'assigned_driver' => $assignedDriver ? [
                'id' => $assignedDriver->id,
                'name' => $assignedDriver->user?->name,
                'phone' => $assignedDriver->phone,
                'image' => $assignedDriver->user?->profile_photo_url,
                'verification_status' => $assignedDriver->verification_status,
                'route_summary' => $assignedRoute?->locations?->pluck('name')->join(' -> '),
                'vehicle' => $assignedRoute ? [
                    'vehicle_type' => $assignedRoute->vehicle_type,
                    'car_make' => $assignedRoute->car_make,
                    'car_model' => $assignedRoute->car_model,
                    'car_number' => $assignedRoute->car_number,
                ] : null,
            ] : null,
            'preferred_driver' => $preferredDriver ? [
                'id' => $preferredDriver->id,
                'name' => $preferredDriver->user?->name,
                'phone' => $preferredDriver->phone,
                'image' => $preferredDriver->user?->profile_photo_url,
                'verification_status' => $preferredDriver->verification_status,
                'route_summary' => data_get($preferredAlert?->meta, 'route_summary') ?: $preferredRoute?->locations?->pluck('name')->join(' -> '),
                'match_score' => (int) data_get($preferredAlert?->meta, 'match_score', 0),
                'match_label' => data_get($preferredAlert?->meta, 'match_label', 'Route Match'),
                'vehicle' => $preferredRoute ? [
                    'vehicle_type' => $preferredRoute->vehicle_type,
                    'car_make' => $preferredRoute->car_make,
                    'car_model' => $preferredRoute->car_model,
                    'car_number' => $preferredRoute->car_number,
                ] : null,
            ] : null,
            'timeline' => $parcel->statusUpdates->map(fn ($update) => [
                'id' => $update->id,
                'status' => $update->status,
                'title' => $update->title,
                'message' => $update->message,
                'actor_role' => $update->actor_role,
                'time' => $update->created_at?->diffForHumans(),
                'created_at' => $update->created_at?->toIso8601String(),
            ])->values(),
        ];
    }

    private function selectionMeta(ParcelRequest $parcel): array
    {
        $decoded = json_decode((string) ($parcel->status_note ?? ''), true);
        if (! is_array($decoded)) {
            return [];
        }

        $paymentStatus = $decoded['payment_status'] ?? $decoded['pay'] ?? 'pending';
        $bookingStatus = $decoded['booking_status'] ?? $decoded['bs'] ?? $parcel->status;

        return [
            'booking_reference' => $decoded['booking_reference'] ?? $decoded['br'] ?? null,
            'preferred_driver_id' => $decoded['preferred_driver_id'] ?? $decoded['pd'] ?? null,
            'preferred_driver_matched' => $decoded['preferred_driver_matched'] ?? $decoded['pm'] ?? false,
            'payment_status' => $paymentStatus,
            'payment_state' => match ($paymentStatus) {
                'ready' => 'payment_ready',
                'manual' => 'awaiting_payment',
                'paid' => 'paid',
                'failed' => 'payment_failed',
                default => 'pending',
            },
            'booking_status' => $bookingStatus,
            'booking_status_label' => match ($bookingStatus) {
                'pending' => 'Pending',
                'confirmed' => 'Booking Confirmed',
                'driver_assigned' => 'Driver Assigned',
                'picked_up' => 'Picked Up',
                'in_transit' => 'In Transit',
                'delivered' => 'Delivered',
                'cancelled' => 'Cancelled',
                default => $parcel->currentStatusLabel(),
            },
            'payment_methods' => ['mobile_money', 'bank_transfer', 'card', 'cash_on_pickup', 'cash_on_delivery'],
            'selection_flow' => $decoded['selection_flow'] ?? $decoded['sf'] ?? null,
        ];
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function accept(Request $request, ParcelRequest $parcelRequest): JsonResponse|RedirectResponse
    {
        $driver = $request->user()?->driver;
        abort_unless($driver, 403);

        try {
            $this->workflowService->acceptByDriver($parcelRequest->loadMissing(['customer', 'pickupLocation', 'dropoffLocation', 'packageType']), $driver);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'parcel' => $exception->getMessage(),
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Job accepted successfully. Notifications were processed for SMS-enabled users.',
                'parcel_id' => $parcelRequest->id,
                'notification_feedback' => [
                    'channel' => 'sms',
                    'state' => 'processed',
                ],
            ]);
        }

        return back()->with('success', 'Job accepted successfully.');
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function updateStatus(Request $request, ParcelRequest $parcelRequest): JsonResponse|RedirectResponse
    {
        $driver = $request->user()?->driver;
        abort_unless($driver, 403);

        if ($parcelRequest->assigned_driver_id !== $driver->id) {
            throw new AuthorizationException('Only the assigned driver can update this parcel.');
        }

        $validated = $request->validate([
            'status' => ['required', 'in:picked_up,in_transit,arrived,delivered'],
        ]);

        try {
            $this->workflowService->transitionByDriver(
                $parcelRequest->loadMissing(['customer', 'pickupLocation', 'dropoffLocation', 'packageType']),
                $driver,
                $validated['status']
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'status' => $exception->getMessage(),
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Parcel status updated. Customer delivery notifications were processed.',
                'parcel_id' => $parcelRequest->id,
                'status' => $parcelRequest->fresh()->status,
                'notification_feedback' => [
                    'channel' => 'sms',
                    'state' => 'processed',
                ],
            ]);
        }

        return back()->with('success', 'Parcel status updated.');
    }
}
