<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverRoute extends Model
{
    protected $casts = [
        'available' => 'boolean',
        'is_refrigerated' => 'boolean',
    ];

    protected $fillable = [
        'driver_id',
        'vehicle_type',
        'max_load_size',
        'is_refrigerated',
        'car_make',
        'car_model',
        'car_number',
        'available',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function locations()
    {
        return $this->belongsToMany(Location::class, 'driver_route_location');
    }

    public function packages()
    {
        return $this->belongsToMany(PackageType::class, 'driver_route_package');
    }

    public function supportsLoadSize(string $loadSize): bool
    {
        $weights = [
            'small' => 1,
            'medium' => 2,
            'large' => 3,
            'heavy' => 4,
            'oversized' => 5,
        ];

        return ($weights[$this->max_load_size] ?? 1) >= ($weights[$loadSize] ?? 1);
    }
}
