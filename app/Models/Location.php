<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $fillable = ['name', 'latitude', 'longitude', 'region'];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function driverRoutes()
    {
        return $this->belongsToMany(DriverRoute::class, 'driver_route_location');
    }
}
