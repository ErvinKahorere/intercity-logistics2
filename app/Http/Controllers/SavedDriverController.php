<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SavedDriverController extends Controller
{
    public function save(Request $request, int $driverId): RedirectResponse
    {
        $user = $request->user();
        $driver = Driver::findOrFail($driverId);

        if ($driver->user_id === $user->id) {
            return back()->with('error', 'You cannot save your own driver profile.');
        }

        if (! $user->savedDrivers()->where('drivers.id', $driverId)->exists()) {
            $user->savedDrivers()->attach($driverId);
        }

        return back()->with('success', 'Driver saved successfully.');
    }

    public function unsave(Request $request, int $driverId): RedirectResponse
    {
        Driver::findOrFail($driverId);
        $request->user()->savedDrivers()->detach($driverId);

        return back()->with('success', 'Driver removed successfully.');
    }
}
