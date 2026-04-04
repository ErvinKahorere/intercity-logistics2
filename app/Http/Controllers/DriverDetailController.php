<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Services\DriverDetailsService;
use Inertia\Inertia;
use Inertia\Response;

class DriverDetailController extends Controller
{
    public function __construct(
        private DriverDetailsService $driverDetailsService,
    ) {
    }

    public function show(Driver $driver): Response
    {
        return Inertia::render('DriverDetail', [
            'details' => $this->driverDetailsService->build(
                $driver,
                auth()->user()?->loadMissing('driver')
            ),
        ]);
    }
}
