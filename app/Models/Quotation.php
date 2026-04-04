<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quotation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'driver_id',
        'parcel_request_id',
        'quotation_number',
        'status',
        'issue_date',
        'expires_at',
        'accepted_at',
        'converted_at',
        'pickup_location_id',
        'dropoff_location_id',
        'package_type_id',
        'weight_kg',
        'load_size',
        'urgency_level',
        'distance_km',
        'estimated_hours',
        'base_fee',
        'distance_fee',
        'weight_fee',
        'urgency_fee',
        'special_handling_fee',
        'subtotal',
        'total',
        'pricing_breakdown',
        'customer_snapshot',
        'driver_snapshot',
        'route_snapshot',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expires_at' => 'date',
        'accepted_at' => 'datetime',
        'converted_at' => 'datetime',
        'pricing_breakdown' => 'array',
        'customer_snapshot' => 'array',
        'driver_snapshot' => 'array',
        'route_snapshot' => 'array',
        'weight_kg' => 'decimal:2',
        'distance_km' => 'decimal:2',
        'estimated_hours' => 'decimal:2',
        'base_fee' => 'decimal:2',
        'distance_fee' => 'decimal:2',
        'weight_fee' => 'decimal:2',
        'urgency_fee' => 'decimal:2',
        'special_handling_fee' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function parcelRequest()
    {
        return $this->belongsTo(ParcelRequest::class);
    }

    public function pickupLocation()
    {
        return $this->belongsTo(Location::class, 'pickup_location_id');
    }

    public function dropoffLocation()
    {
        return $this->belongsTo(Location::class, 'dropoff_location_id');
    }

    public function packageType()
    {
        return $this->belongsTo(PackageType::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }
}
