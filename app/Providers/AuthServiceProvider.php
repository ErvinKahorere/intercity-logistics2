<?php

namespace App\Providers;

use App\Models\DriverRoute;
use App\Policies\DriverRoutePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        DriverRoute::class => DriverRoutePolicy::class,
    ];

 /*   protected $policies = [
        \App\Models\DriverRoute::class => \App\Policies\DriverRoutePolicy::class,
    ];*/

    public function boot()
    {
        $this->registerPolicies();
    }
}
