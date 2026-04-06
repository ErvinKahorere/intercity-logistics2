<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Driver;
use App\Models\DriverAlert;
use App\Models\ParcelRequest;
use App\Models\ParcelStatusUpdate;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Collection as SupportCollection;
use InvalidArgumentException;

class ParcelWorkflowService
{
    private const BOOKING_STATUS_LABELS = [
        'pending' => 'Pending',
        'confirmed' => 'Booking Confirmed',
        'driver_assigned' => 'Driver Assigned',
        'picked_up' => 'Picked Up',
        'in_transit' => 'In Transit',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
    ];

    private const STATUS_META = [
        'pending' => ['title' => 'Request created', 'message' => 'Your parcel request has been received and is waiting for driver matching.'],
        'matched' => ['title' => 'Drivers matched', 'message' => 'Matching drivers have been alerted for this route.'],
        'accepted' => ['title' => 'Driver assigned', 'message' => 'A driver accepted your parcel request.'],
        'picked_up' => ['title' => 'Parcel picked up', 'message' => 'Your parcel has been collected from the pickup point.'],
        'in_transit' => ['title' => 'Parcel in transit', 'message' => 'Your parcel is moving to the destination city.'],
        'arrived' => ['title' => 'Parcel arrived', 'message' => 'Your parcel has arrived in the destination city.'],
        'delivered' => ['title' => 'Parcel delivered', 'message' => 'Your parcel was delivered successfully.'],
        'cancelled' => ['title' => 'Parcel cancelled', 'message' => 'This parcel request was cancelled.'],
    ];

    private const TRANSITION_TIMESTAMPS = [
        'matched' => 'matched_at',
        'accepted' => 'accepted_at',
        'picked_up' => 'picked_up_at',
        'in_transit' => 'in_transit_at',
        'arrived' => 'arrived_at',
        'delivered' => 'delivered_at',
        'cancelled' => 'cancelled_at',
    ];

    public function __construct(
        private DriverMatchService $driverMatchService,
        private PricingService $pricingService,
        private QuotationService $quotationService,
        private InvoiceService $invoiceService,
        private NotificationService $notificationService,
    ) {
    }

    public function createRequest(User $customer, array $validated): ParcelRequest
    {
        return DB::transaction(function () use ($customer, $validated) {
            $pricing = $this->pricingService->quote(
                (int) $validated['pickup_location_id'],
                (int) $validated['dropoff_location_id'],
                isset($validated['package_type_id']) ? (int) $validated['package_type_id'] : null,
                isset($validated['weight_kg']) ? (float) $validated['weight_kg'] : null,
                $validated['urgency_level'],
                $validated['load_size'] ?? 'small',
                $validated['notes'] ?? null
            );

            $clientOffer = isset($validated['client_offer_price']) && $validated['client_offer_price'] !== null && $validated['client_offer_price'] !== ''
                ? round((float) $validated['client_offer_price'], 2)
                : null;

            $parcelRequest = ParcelRequest::create([
                ...Arr::except($validated, ['selected_driver_id', 'confirmation_flow']),
                ...$pricing,
                'user_id' => $customer->id,
                'tracking_number' => $this->generateTrackingNumber(),
                'status' => ParcelRequest::STATUS_PENDING,
                'client_offer_price' => $clientOffer,
                'final_price' => $pricing['total_price'],
                'status_note' => $this->encodeSelectionMeta($validated),
            ]);

            $requestMessage = $clientOffer
                ? sprintf('Tracking number issued, route pricing prepared, and your offer of N$ %s was shared with matching drivers.', number_format($clientOffer, 2))
                : 'Tracking number issued and route pricing prepared.';

            $this->logStatus($parcelRequest, ParcelRequest::STATUS_PENDING, 'system');
            $this->notificationService->send($customer, $parcelRequest, [
                'title' => 'Request created',
                'message' => $requestMessage,
                'badge' => 'Created',
                'tone' => 'info',
                'event_type' => 'request_created',
                'template_key' => 'general.important_alert',
                'preference_key' => 'important_alerts',
                'context' => ['message' => $requestMessage],
                'meta' => [
                    'estimated_price' => $parcelRequest->total_price,
                    'client_offer_price' => $parcelRequest->client_offer_price,
                    'tracking_number' => $parcelRequest->tracking_number,
                ],
                'email_subject' => 'Your InterCity request is live',
            ]);

            $matches = $this->matchDrivers($parcelRequest, $validated);
            $selectedDriverId = isset($validated['selected_driver_id']) ? (int) $validated['selected_driver_id'] : null;

            if ($selectedDriverId && ! $matches->contains(fn ($driver) => (int) $driver->id === $selectedDriverId)) {
                throw new InvalidArgumentException('The selected driver is no longer available for this request. Please choose another driver.');
            }

            $this->quotationService->createFromParcelRequest(
                $parcelRequest->loadMissing(['customer', 'pickupLocation', 'dropoffLocation', 'packageType'])
            );

            return $parcelRequest->fresh([
                'pickupLocation',
                'dropoffLocation',
                'packageType',
                'assignedDriver.user',
                'statusUpdates',
                'latestQuotation',
                'latestInvoice',
            ]);
        });
    }

    public function matchDrivers(ParcelRequest $parcelRequest, array $context = []): Collection
    {
        $matches = $this->driverMatchService->match($parcelRequest);
        $preferredDriverId = isset($context['selected_driver_id']) ? (int) $context['selected_driver_id'] : $this->preferredDriverId($parcelRequest);
        if ($preferredDriverId) {
            $matches = $this->prioritizePreferredDriver($matches, $preferredDriverId);
        }
        $previousMatchedIds = collect($parcelRequest->matched_driver_ids ?? []);
        $newMatchedIds = $matches->pluck('id');
        $newlyMatchedIds = $newMatchedIds->diff($previousMatchedIds)->values();
        $removedMatchedIds = $previousMatchedIds->diff($newMatchedIds)->values();
        $previousStatus = $parcelRequest->status;

        $paymentStatus = $preferredDriverId ? 'ready' : 'pending';
        $bookingStatus = $preferredDriverId ? 'confirmed' : 'pending';

        $parcelRequest->forceFill([
            'matched_driver_ids' => $newMatchedIds->all(),
            'status' => $matches->isNotEmpty() ? ParcelRequest::STATUS_MATCHED : ParcelRequest::STATUS_PENDING,
            'matched_at' => $matches->isNotEmpty() ? now() : null,
            'assigned_driver_id' => null,
            'status_note' => $this->mergeSelectionMeta($parcelRequest, [
                'preferred_driver_id' => $preferredDriverId,
                'preferred_driver_matched' => $preferredDriverId ? $newMatchedIds->contains($preferredDriverId) : false,
                'payment_status' => $paymentStatus,
                'booking_status' => $bookingStatus,
            ]),
        ])->save();

        if ($matches->isNotEmpty() && $previousStatus !== ParcelRequest::STATUS_MATCHED) {
            $this->logStatus($parcelRequest, ParcelRequest::STATUS_MATCHED, 'system', [
                'message' => 'Matching drivers have been alerted and can now accept this delivery job.',
            ]);
        }

        if ($matches->isEmpty() && $previousStatus === ParcelRequest::STATUS_MATCHED) {
            $this->logStatus($parcelRequest, ParcelRequest::STATUS_PENDING, 'system', [
                'message' => 'This request is waiting for a fresh driver match after route or availability changes.',
            ]);
        }

        if ($removedMatchedIds->isNotEmpty()) {
            DriverAlert::query()
                ->where('parcel_request_id', $parcelRequest->id)
                ->where('title', 'New matching request')
                ->whereIn('driver_id', $removedMatchedIds->all())
                ->delete();
        }

        foreach ($matches as $driver) {
            $summary = sprintf(
                '%s -> %s | %s | %s urgency',
                $parcelRequest->pickupLocation?->name ?? 'Pickup',
                $parcelRequest->dropoffLocation?->name ?? 'Dropoff',
                $parcelRequest->packageType?->name ?? 'Parcel',
                str_replace('_', ' ', $parcelRequest->urgency_level)
            );

            DriverAlert::updateOrCreate(
                [
                    'driver_id' => $driver->id,
                    'parcel_request_id' => $parcelRequest->id,
                    'title' => 'New matching request',
                ],
                [
                    'message' => $parcelRequest->hasClientOffer()
                        ? $summary . sprintf(' | Client offer N$ %s', number_format((float) $parcelRequest->client_offer_price, 2))
                        : $summary,
                    'severity' => $preferredDriverId === $driver->id ? 'success' : 'info',
                    'is_read' => false,
                    'meta' => [
                        'tracking_number' => $parcelRequest->tracking_number,
                        'match_score' => $driver->match_score,
                        'route_summary' => $driver->matching_route_summary,
                        'pickup' => $parcelRequest->pickupLocation?->name,
                        'destination' => $parcelRequest->dropoffLocation?->name,
                        'package_type' => $parcelRequest->packageType?->name,
                        'urgency_level' => $parcelRequest->urgency_level,
                        'price' => $parcelRequest->total_price,
                        'client_offer_price' => $parcelRequest->client_offer_price,
                        'match_reasons' => $driver->match_reasons ?? [],
                        'match_badges' => $driver->match_badges ?? [],
                        'match_label' => $driver->match_label ?? null,
                        'match_breakdown' => $driver->match_breakdown ?? [],
                        'preferred_driver' => $preferredDriverId === $driver->id,
                    ],
                ]
            );

            if ($driver->user && $newlyMatchedIds->contains($driver->id)) {
                $this->notificationService->send($driver->user, $parcelRequest, [
                    'title' => 'New matching request',
                    'message' => $parcelRequest->hasClientOffer()
                        ? $summary . sprintf(' | Offer N$ %s', number_format((float) $parcelRequest->client_offer_price, 2))
                        : $summary,
                    'badge' => 'Match',
                    'tone' => $preferredDriverId === $driver->id ? 'success' : 'info',
                    'event_type' => $preferredDriverId === $driver->id ? 'preferred_driver_match' : 'driver_match',
                    'template_key' => in_array($parcelRequest->urgency_level, ['express', 'same_day'], true)
                        ? 'driver.urgent_job_alert'
                        : 'driver.new_matching_request',
                    'preference_key' => in_array($parcelRequest->urgency_level, ['express', 'same_day'], true)
                        ? 'urgent_job_alert'
                        : 'driver_match',
                    'context' => [
                        'pickup' => $parcelRequest->pickupLocation?->name,
                        'destination' => $parcelRequest->dropoffLocation?->name,
                        'tracking' => $parcelRequest->tracking_number,
                    ],
                    'meta' => [
                        'match_score' => $driver->match_score,
                        'estimated_price' => $parcelRequest->total_price,
                        'client_offer_price' => $parcelRequest->client_offer_price,
                        'match_reasons' => $driver->match_reasons ?? [],
                        'match_badges' => $driver->match_badges ?? [],
                        'match_label' => $driver->match_label ?? null,
                        'preferred_driver' => $preferredDriverId === $driver->id,
                        'urgency_level' => $parcelRequest->urgency_level,
                        'tracking_number' => $parcelRequest->tracking_number,
                    ],
                    'email_subject' => 'New InterCity delivery request match',
                ]);
            }
        }

        return $matches;
    }

    public function refreshOpenRequestMatches(int $limit = 25): int
    {
        $openRequests = ParcelRequest::query()
            ->with(['pickupLocation', 'dropoffLocation', 'packageType'])
            ->openForMatching()
            ->latest()
            ->take($limit)
            ->get();

        foreach ($openRequests as $parcelRequest) {
            $this->matchDrivers($parcelRequest);
        }

        return $openRequests->count();
    }

    public function acceptByDriver(ParcelRequest $parcelRequest, Driver $driver): ParcelRequest
    {
        return DB::transaction(function () use ($parcelRequest, $driver) {
            if (in_array($driver->verification_status, ['pending', 'rejected'], true)) {
                throw new InvalidArgumentException('Your verification review must be cleared before accepting delivery jobs.');
            }

            $eligibleDriverIds = $parcelRequest->matched_driver_ids ?? [];
            if (! in_array($driver->id, $eligibleDriverIds, true) && $parcelRequest->assigned_driver_id !== $driver->id) {
                throw new InvalidArgumentException('You are not eligible to accept this parcel.');
            }

            if ($parcelRequest->assigned_driver_id && $parcelRequest->assigned_driver_id !== $driver->id) {
                throw new InvalidArgumentException('This parcel has already been accepted by another driver.');
            }

            $acceptedPrice = $parcelRequest->hasClientOffer()
                ? (float) $parcelRequest->client_offer_price
                : (float) ($parcelRequest->final_price ?: $parcelRequest->total_price);

            $parcelRequest->forceFill([
                'assigned_driver_id' => $driver->id,
                'status' => ParcelRequest::STATUS_ACCEPTED,
                'accepted_at' => now(),
                'final_price' => $acceptedPrice,
                'status_note' => $this->mergeSelectionMeta($parcelRequest, [
                    'payment_status' => 'manual',
                    'booking_status' => 'driver_assigned',
                ]),
            ])->save();

            $this->logStatus($parcelRequest, ParcelRequest::STATUS_ACCEPTED, 'driver', [
                'message' => sprintf('%s accepted this parcel and is preparing for pickup.', $driver->user?->name ?? 'A driver'),
            ]);

            DriverAlert::create([
                'driver_id' => $driver->id,
                'parcel_request_id' => $parcelRequest->id,
                'title' => 'Job accepted',
                'message' => $parcelRequest->hasClientOffer()
                    ? sprintf('You accepted parcel %s at the client offer of N$ %s.', $parcelRequest->tracking_number, number_format($acceptedPrice, 2))
                    : sprintf('You accepted parcel %s.', $parcelRequest->tracking_number),
                'severity' => 'success',
                'meta' => [
                    'tracking_number' => $parcelRequest->tracking_number,
                    'final_price' => $acceptedPrice,
                    'client_offer_price' => $parcelRequest->client_offer_price,
                    'match_reasons' => $driver->match_reasons ?? [],
                    'match_badges' => $driver->match_badges ?? [],
                    'match_label' => $driver->match_label ?? null,
                    'match_breakdown' => $driver->match_breakdown ?? [],
                ],
            ]);

            if ($driver->user) {
                $this->notificationService->send($driver->user, $parcelRequest, [
                    'title' => 'Job accepted',
                    'message' => sprintf('You accepted parcel %s. Pickup at %s.', $parcelRequest->tracking_number, $parcelRequest->pickupLocation?->name ?? 'the pickup point'),
                    'badge' => 'Accepted',
                    'tone' => 'success',
                    'event_type' => 'driver_job_accepted',
                    'template_key' => 'driver.job_accepted',
                    'preference_key' => 'job_accepted',
                    'context' => [
                        'tracking' => $parcelRequest->tracking_number,
                        'pickup' => $parcelRequest->pickupLocation?->name,
                    ],
                    'meta' => [
                        'tracking_number' => $parcelRequest->tracking_number,
                        'final_price' => $acceptedPrice,
                    ],
                    'email_subject' => 'InterCity job accepted',
                ]);
            }

            if ($parcelRequest->customer) {
                $assignedMessage = $parcelRequest->hasClientOffer()
                    ? sprintf('%s accepted your offer of N$ %s for delivery from %s to %s.', $driver->user?->name ?? 'A driver', number_format($acceptedPrice, 2), $parcelRequest->pickupLocation?->name ?? 'pickup', $parcelRequest->dropoffLocation?->name ?? 'dropoff')
                    : sprintf('%s accepted your delivery from %s to %s.', $driver->user?->name ?? 'A driver', $parcelRequest->pickupLocation?->name ?? 'pickup', $parcelRequest->dropoffLocation?->name ?? 'dropoff');

                $this->notificationService->send($parcelRequest->customer, $parcelRequest, [
                    'title' => 'Driver assigned',
                    'message' => $assignedMessage,
                    'badge' => 'Accepted',
                    'tone' => 'success',
                    'event_type' => 'driver_assigned',
                    'template_key' => 'customer.driver_assigned',
                    'preference_key' => 'driver_assigned',
                    'context' => [
                        'pickup' => $parcelRequest->pickupLocation?->name,
                        'destination' => $parcelRequest->dropoffLocation?->name,
                        'tracking' => $parcelRequest->tracking_number,
                        'driver' => $driver->user?->name ?? 'your driver',
                    ],
                    'meta' => [
                        'final_price' => $acceptedPrice,
                        'client_offer_price' => $parcelRequest->client_offer_price,
                        'tracking_number' => $parcelRequest->tracking_number,
                    ],
                    'email_subject' => 'Your driver has been assigned',
                ]);
            }

            $otherDrivers = Driver::query()->whereIn('id', Arr::where($eligibleDriverIds, fn ($id) => $id !== $driver->id))->with('user')->get();
            foreach ($otherDrivers as $otherDriver) {
                if ($otherDriver->user) {
                    $this->notificationService->send($otherDriver->user, $parcelRequest, [
                        'title' => 'Request no longer available',
                        'message' => sprintf('Parcel %s was accepted by another driver.', $parcelRequest->tracking_number),
                        'badge' => 'Taken',
                        'tone' => 'warning',
                        'event_type' => 'request_taken',
                        'channels' => [],
                        'meta' => ['tracking_number' => $parcelRequest->tracking_number],
                    ]);
                }
            }

            $quotation = $parcelRequest->latestQuotation()->first();
            $this->invoiceService->createForParcelRequest($parcelRequest->loadMissing(['customer', 'pickupLocation', 'dropoffLocation', 'packageType', 'assignedDriver.user']), $quotation);

            return $parcelRequest->fresh(['assignedDriver.user', 'statusUpdates']);
        });
    }

    public function transitionByDriver(ParcelRequest $parcelRequest, Driver $driver, string $status): ParcelRequest
    {
        $allowed = ParcelRequest::driverTransitionsFor($parcelRequest->status);
        if (! in_array($status, $allowed, true)) {
            throw new InvalidArgumentException('Invalid parcel workflow transition.');
        }

        return DB::transaction(function () use ($parcelRequest, $driver, $status) {
            $updates = ['status' => $status];
            $timestampColumn = self::TRANSITION_TIMESTAMPS[$status] ?? null;
            if ($timestampColumn) {
                $updates[$timestampColumn] = now();
            }

            $parcelRequest->forceFill($updates)->save();
            $bookingStatus = match ($status) {
                ParcelRequest::STATUS_PICKED_UP => 'picked_up',
                ParcelRequest::STATUS_IN_TRANSIT, ParcelRequest::STATUS_ARRIVED => 'in_transit',
                ParcelRequest::STATUS_DELIVERED => 'delivered',
                ParcelRequest::STATUS_CANCELLED => 'cancelled',
                default => null,
            };

            if ($bookingStatus) {
                $parcelRequest->forceFill([
                    'status_note' => $this->mergeSelectionMeta($parcelRequest, [
                        'booking_status' => $bookingStatus,
                        'payment_status' => $status === ParcelRequest::STATUS_DELIVERED ? 'paid' : null,
                    ]),
                ])->save();
            }

            $this->logStatus($parcelRequest, $status, 'driver');

            if ($parcelRequest->customer) {
                $meta = self::STATUS_META[$status];
                $templateKey = match ($status) {
                    ParcelRequest::STATUS_PICKED_UP => 'customer.parcel_picked_up',
                    ParcelRequest::STATUS_IN_TRANSIT, ParcelRequest::STATUS_ARRIVED => 'customer.parcel_in_transit',
                    ParcelRequest::STATUS_DELIVERED => 'customer.parcel_delivered',
                    default => null,
                };

                $preferenceKey = match ($status) {
                    ParcelRequest::STATUS_PICKED_UP => 'parcel_picked_up',
                    ParcelRequest::STATUS_IN_TRANSIT, ParcelRequest::STATUS_ARRIVED => 'parcel_in_transit',
                    ParcelRequest::STATUS_DELIVERED => 'parcel_delivered',
                    default => null,
                };

                $this->notificationService->send($parcelRequest->customer, $parcelRequest, [
                    'title' => $meta['title'],
                    'message' => $meta['message'],
                    'badge' => Str::headline(str_replace('_', ' ', $status)),
                    'tone' => $this->notificationToneForStatus($status),
                    'event_type' => 'parcel_' . $status,
                    'template_key' => $templateKey,
                    'preference_key' => $preferenceKey,
                    'context' => [
                        'pickup' => $parcelRequest->pickupLocation?->name,
                        'destination' => $parcelRequest->dropoffLocation?->name,
                        'tracking' => $parcelRequest->tracking_number,
                        'driver' => $driver->user?->name ?? 'your driver',
                    ],
                    'meta' => [
                        'tracking_number' => $parcelRequest->tracking_number,
                        'status' => $status,
                    ],
                    'email_subject' => 'Parcel update: ' . $meta['title'],
                ]);
            }

            if ($driver->user) {
                $meta = self::STATUS_META[$status];
                $this->notificationService->send($driver->user, $parcelRequest, [
                    'title' => $meta['title'],
                    'message' => sprintf('Parcel %s is now %s.', $parcelRequest->tracking_number, str_replace('_', ' ', $status)),
                    'badge' => 'Update',
                    'tone' => 'info',
                    'event_type' => 'driver_delivery_update',
                    'channels' => [],
                    'meta' => [
                        'tracking_number' => $parcelRequest->tracking_number,
                        'status' => $status,
                    ],
                ]);
            }

            return $parcelRequest->fresh(['assignedDriver.user', 'statusUpdates']);
        });
    }

    public function logStatus(ParcelRequest $parcelRequest, string $status, string $actorRole, array $overrides = []): ParcelStatusUpdate
    {
        $meta = array_merge(self::STATUS_META[$status] ?? ['title' => Str::headline($status), 'message' => null], $overrides);

        return ParcelStatusUpdate::create([
            'parcel_request_id' => $parcelRequest->id,
            'status' => $status,
            'actor_role' => $actorRole,
            'title' => $meta['title'],
            'message' => $meta['message'],
        ]);
    }

    public function notifyUser(
        User $user,
        ?ParcelRequest $parcelRequest,
        string $title,
        string $message,
        ?string $badge = null,
        string $tone = 'info',
        string $eventType = 'general',
        array $meta = []
    ): AppNotification {
        return $this->notificationService->send($user, $parcelRequest, [
            'title' => $title,
            'message' => $message,
            'badge' => $badge,
            'tone' => $tone,
            'event_type' => $eventType,
            'channels' => [],
            'meta' => $meta,
        ]);
    }

    private function notificationToneForStatus(string $status): string
    {
        return match ($status) {
            ParcelRequest::STATUS_PICKED_UP, ParcelRequest::STATUS_IN_TRANSIT => 'info',
            ParcelRequest::STATUS_ARRIVED => 'warning',
            ParcelRequest::STATUS_DELIVERED => 'success',
            ParcelRequest::STATUS_CANCELLED => 'error',
            default => 'info',
        };
    }

    private function generateTrackingNumber(): string
    {
        do {
            $trackingNumber = 'ICL-' . now()->format('ymd') . '-' . strtoupper(Str::random(6));
        } while (ParcelRequest::query()->where('tracking_number', $trackingNumber)->exists());

        return $trackingNumber;
    }

    private function encodeSelectionMeta(array $validated): ?string
    {
        $selectedDriverId = isset($validated['selected_driver_id']) ? (int) $validated['selected_driver_id'] : null;
        if (! $selectedDriverId) {
            return null;
        }

        return json_encode([
            'br' => $this->generateBookingReference(),
            'pd' => $selectedDriverId,
            'pm' => false,
            'pay' => 'ready',
            'bs' => 'confirmed',
            'sf' => $validated['confirmation_flow'] ?? null,
        ]);
    }

    private function preferredDriverId(ParcelRequest $parcelRequest): ?int
    {
        $meta = $this->normalizedSelectionMeta((string) ($parcelRequest->status_note ?? ''));
        if (empty($meta['preferred_driver_id'])) {
            return null;
        }

        return (int) $meta['preferred_driver_id'];
    }

    private function mergeSelectionMeta(ParcelRequest $parcelRequest, array $updates): string
    {
        $current = $this->normalizedSelectionMeta((string) ($parcelRequest->status_note ?? ''));

        if (empty($current['booking_reference'])) {
            $current['booking_reference'] = $this->generateBookingReference();
        }

        return json_encode($this->compactSelectionMeta(array_merge(
            $current,
            array_filter($updates, fn ($value) => $value !== null)
        )));
    }

    private function prioritizePreferredDriver(SupportCollection $matches, int $preferredDriverId): SupportCollection
    {
        $preferred = $matches->firstWhere('id', $preferredDriverId);
        if (! $preferred) {
            return $matches;
        }

        return $matches
            ->sortByDesc(fn ($driver) => $driver->id === $preferredDriverId ? 1 : 0)
            ->values();
    }

    private function generateBookingReference(): string
    {
        return 'BKG-' . now()->format('ymd') . '-' . strtoupper(Str::random(5));
    }

    private function normalizedSelectionMeta(string $statusNote): array
    {
        $decoded = json_decode($statusNote, true);
        if (! is_array($decoded)) {
            return [];
        }

        return [
            'booking_reference' => $decoded['booking_reference'] ?? $decoded['br'] ?? null,
            'preferred_driver_id' => $decoded['preferred_driver_id'] ?? $decoded['pd'] ?? null,
            'preferred_driver_matched' => $decoded['preferred_driver_matched'] ?? $decoded['pm'] ?? null,
            'payment_status' => $decoded['payment_status'] ?? $decoded['pay'] ?? null,
            'booking_status' => $decoded['booking_status'] ?? $decoded['bs'] ?? null,
            'selection_flow' => $decoded['selection_flow'] ?? $decoded['sf'] ?? null,
        ];
    }

    private function compactSelectionMeta(array $meta): array
    {
        return array_filter([
            'br' => $meta['booking_reference'] ?? null,
            'pd' => isset($meta['preferred_driver_id']) ? (int) $meta['preferred_driver_id'] : null,
            'pm' => $meta['preferred_driver_matched'] ?? null,
            'pay' => $meta['payment_status'] ?? null,
            'bs' => $meta['booking_status'] ?? null,
            'sf' => $meta['selection_flow'] ?? null,
        ], fn ($value) => $value !== null);
    }
}
