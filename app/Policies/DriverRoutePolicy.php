<?php

namespace App\Policies;

use App\Models\DriverRoute;
use App\Models\User;

class DriverRoutePolicy
{
    public function update(User $user, DriverRoute $driverRoute)
    {
       // return $user->id === $route->driver_id;

        // Allow if the authenticated user owns this driver route
        return $driverRoute->driver_id === $user->driver->id;

    }
}
