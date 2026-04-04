<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackageType extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'pricing_category', 'pricing_multiplier', 'special_handling_fee'];

    protected $casts = [
        'pricing_multiplier' => 'decimal:2',
        'special_handling_fee' => 'decimal:2',
    ];

    public function driverRoutes()
    {
        return $this->belongsToMany(DriverRoute::class, 'driver_route_package');
    }
}
