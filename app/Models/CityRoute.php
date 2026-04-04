<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CityRoute extends Model
{
    protected $fillable = [
        'origin_location_id',
        'destination_location_id',
        'route_code',
        'distance_km',
        'distance_source',
        'road_adjustment_factor',
        'estimated_hours',
        'base_fare',
        'per_km_rate',
        'minimum_price',
        'reverse_route_enabled',
        'operational_notes',
        'is_featured',
        'is_active',
    ];

    protected $casts = [
        'distance_km' => 'decimal:2',
        'road_adjustment_factor' => 'decimal:2',
        'estimated_hours' => 'decimal:1',
        'base_fare' => 'decimal:2',
        'per_km_rate' => 'decimal:2',
        'minimum_price' => 'decimal:2',
        'reverse_route_enabled' => 'boolean',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function originLocation()
    {
        return $this->belongsTo(Location::class, 'origin_location_id');
    }

    public function destinationLocation()
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }
}
