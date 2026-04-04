<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;

class DriverController extends Controller
{
    public function index()
    {
        $drivers = Driver::with(['user', 'driverRoutes.locations', 'driverRoutes.packages'])
            ->where('status', 'active')
            ->get()
            ->map(function ($driver) {
                return [
                    'id' => $driver->id,
                    'phone' => $driver->phone,
                    'status' => $driver->status,
                    'user' => [
                        'id' => $driver->user?->id,
                        'name' => $driver->user?->name,
                        'email' => $driver->user?->email,
                        'phone' => $driver->user?->phone,
                        'location' => $driver->user?->location,
                        'profile_photo_url' => $driver->user?->profile_photo_url,
                    ],
                    'driverRoutes' => $driver->driverRoutes->map(fn ($route) => [
                        'id' => $route->id,
                        'car_make' => $route->car_make,
                        'car_model' => $route->car_model,
                        'car_number' => $route->car_number,
                        'available' => $route->available,
                        'locations' => $route->locations->map->only(['id', 'name'])->values(),
                        'packages' => $route->packages->map->only(['id', 'name'])->values(),
                    ])->values(),
                ];
            });

        return response()->json($drivers->values());
    }
}
