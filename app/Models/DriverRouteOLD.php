<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverRouteOLD extends Model
{
    protected $fillable = [
        'driver_id',
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





}
