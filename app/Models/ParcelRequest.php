<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ParcelRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_MATCHED = 'matched';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_PICKED_UP = 'picked_up';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_ARRIVED = 'arrived';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    public const DRIVER_TRANSITIONS = [
        self::STATUS_ACCEPTED => [self::STATUS_PICKED_UP],
        self::STATUS_PICKED_UP => [self::STATUS_IN_TRANSIT],
        self::STATUS_IN_TRANSIT => [self::STATUS_ARRIVED],
        self::STATUS_ARRIVED => [self::STATUS_DELIVERED],
    ];

    protected $fillable = [
        'user_id',
        'tracking_number',
        'city_route_id',
        'pickup_location_id',
        'dropoff_location_id',
        'package_type_id',
        'pickup_address',
        'dropoff_address',
        'receiver_name',
        'receiver_phone',
        'weight_kg',
        'load_size',
        'urgency_level',
        'distance_km',
        'estimated_hours',
        'base_price',
        'distance_fee',
        'weight_surcharge',
        'urgency_surcharge',
        'special_handling_fee',
        'minimum_charge',
        'parcel_multiplier',
        'total_price',
        'client_offer_price',
        'final_price',
        'pricing_breakdown',
        'declared_value',
        'notes',
        'status_note',
        'status',
        'assigned_driver_id',
        'matched_driver_ids',
        'matched_at',
        'accepted_at',
        'picked_up_at',
        'in_transit_at',
        'arrived_at',
        'delivered_at',
        'cancelled_at',
    ];

    protected $casts = [
        'matched_driver_ids' => 'array',
        'weight_kg' => 'decimal:2',
        'estimated_hours' => 'decimal:1',
        'base_price' => 'decimal:2',
        'distance_fee' => 'decimal:2',
        'weight_surcharge' => 'decimal:2',
        'urgency_surcharge' => 'decimal:2',
        'special_handling_fee' => 'decimal:2',
        'minimum_charge' => 'decimal:2',
        'parcel_multiplier' => 'decimal:2',
        'total_price' => 'decimal:2',
        'client_offer_price' => 'decimal:2',
        'final_price' => 'decimal:2',
        'declared_value' => 'decimal:2',
        'pricing_breakdown' => 'array',
        'matched_at' => 'datetime',
        'accepted_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'in_transit_at' => 'datetime',
        'arrived_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function pickupLocation()
    {
        return $this->belongsTo(Location::class, 'pickup_location_id');
    }

    public function cityRoute()
    {
        return $this->belongsTo(CityRoute::class);
    }

    public function dropoffLocation()
    {
        return $this->belongsTo(Location::class, 'dropoff_location_id');
    }

    public function packageType()
    {
        return $this->belongsTo(PackageType::class);
    }

    public function assignedDriver()
    {
        return $this->belongsTo(Driver::class, 'assigned_driver_id');
    }

    public function statusUpdates()
    {
        return $this->hasMany(ParcelStatusUpdate::class)->latest();
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class)->latest('issue_date');
    }

    public function latestQuotation()
    {
        return $this->hasOne(Quotation::class)->latestOfMany('issue_date');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class)->latest('issue_date');
    }

    public function latestInvoice()
    {
        return $this->hasOne(Invoice::class)->latestOfMany('issue_date');
    }

    public function notifications()
    {
        return $this->hasMany(AppNotification::class)->latest();
    }

    public function driverAlerts()
    {
        return $this->hasMany(DriverAlert::class)->latest();
    }

    public function scopeForDriverFeed($query, Driver $driver)
    {
        return $query->whereJsonContains('matched_driver_ids', $driver->id)
            ->whereNull('assigned_driver_id')
            ->where('status', self::STATUS_MATCHED);
    }

    public function scopeForActiveDriver($query, Driver $driver)
    {
        return $query->where('assigned_driver_id', $driver->id)
            ->whereIn('status', [
                self::STATUS_ACCEPTED,
                self::STATUS_PICKED_UP,
                self::STATUS_IN_TRANSIT,
                self::STATUS_ARRIVED,
            ]);
    }

    public function scopeTrackable($query)
    {
        return $query->whereNotIn('status', [self::STATUS_CANCELLED]);
    }

    public function scopeOpenForMatching($query)
    {
        return $query->whereNull('assigned_driver_id')
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_MATCHED]);
    }

    public function currentStatusLabel(): string
    {
        return Str::headline(str_replace('_', ' ', $this->status));
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::driverTransitionsFor($this->status), true);
    }

    public function hasClientOffer(): bool
    {
        return $this->client_offer_price !== null && (float) $this->client_offer_price > 0;
    }

    public static function driverTransitionsFor(string $status): array
    {
        return self::DRIVER_TRANSITIONS[$status] ?? [];
    }
}
