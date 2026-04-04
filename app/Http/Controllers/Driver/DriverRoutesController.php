<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DriverRoute;
use App\Models\Location;
use App\Models\PackageType;
use Illuminate\Http\Request;
use App\Services\ParcelWorkflowService;
use App\Services\DriverWorkspaceService;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\JsonResponse;

class DriverRoutesController extends Controller
{
    public function __construct(
        private ParcelWorkflowService $workflowService,
        private DriverWorkspaceService $driverWorkspaceService,
    ) {
    }

    public function home(): Response
    {
        return Inertia::render('Driver/Home', $this->driverWorkspaceService->build(auth()->user()));
    }

    public function dashboard(): Response
    {
        return Inertia::render('Driver/Profile/Dashboard', $this->driverWorkspaceService->build(auth()->user()));
    }

    public function dashboardData(): JsonResponse
    {
        return response()->json($this->driverWorkspaceService->build(auth()->user()));
    }

    public function index(): Response
    {
        return $this->routes();
    }

    public function routes(): Response
    {
        $user = auth()->user();
        $driver = $this->ensureDriver($user);
        $driverRoute = $this->ensureDriverRoute($driver);

        return Inertia::render('Driver/Profile/Routes', [
            'user' => $user,
            'driverRoute' => $driverRoute,
            'vehicle' => [
                'id' => $driverRoute->id,
                'vehicle_type' => $driverRoute->vehicle_type,
                'max_load_size' => $driverRoute->max_load_size,
                'is_refrigerated' => $driverRoute->is_refrigerated,
                'car_make' => $driverRoute->car_make,
                'car_model' => $driverRoute->car_model,
                'car_number' => $driverRoute->car_number,
                'available' => $driverRoute->available,
            ],
            'routes' => $driverRoute->locations,
            'packages' => $driverRoute->packages,
            'selectedLocations' => $driverRoute->locations,
            'locations' => Location::all(),
            'packageTypes' => PackageType::all(),
        ]);
    }

    public function updateAvailability(Request $request)
    {
        $validated = $request->validate([
            'available' => ['required', 'boolean'],
        ]);

        $driver = $this->ensureDriver(auth()->user());
        $driverRoute = $this->ensureDriverRoute($driver);
        $driverRoute->update([
            'available' => $validated['available'],
        ]);

        $refreshed = $this->workflowService->refreshOpenRequestMatches();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => "Availability updated successfully. Refreshed {$refreshed} open request match(es).",
                'available' => $driverRoute->available,
                'availability_label' => $driverRoute->available ? 'Online' : 'Offline',
            ]);
        }

        return redirect()->back()->with('success', "Availability updated successfully. Refreshed {$refreshed} open request match(es).");
    }

    public function update(Request $request, DriverRoute $driverRoute)
    {
        $this->authorize('update', $driverRoute);

        $validated = $request->validate([
            'vehicle_type' => ['required', 'in:bakkie,van,truck,refrigerated_truck,car'],
            'max_load_size' => ['required', 'in:small,medium,large,heavy,oversized'],
            'is_refrigerated' => ['required', 'boolean'],
            'car_make' => ['nullable', 'string', 'max:100'],
            'car_model' => ['nullable', 'string', 'max:100'],
            'car_number' => ['nullable', 'string', 'max:50'],
            'available' => ['required', 'boolean'],
            'locations' => ['array'],
            'locations.*' => ['integer', 'exists:locations,id'],
            'packages' => ['array'],
            'packages.*' => ['integer', 'exists:package_types,id'],
        ]);

        $driverRoute->update([
            'vehicle_type' => $validated['vehicle_type'],
            'max_load_size' => $validated['max_load_size'],
            'is_refrigerated' => $validated['is_refrigerated'],
            'car_make' => $validated['car_make'] ?? null,
            'car_model' => $validated['car_model'] ?? null,
            'car_number' => $validated['car_number'] ?? null,
            'available' => $validated['available'],
        ]);

        $driverRoute->locations()->sync($validated['locations'] ?? []);
        $driverRoute->packages()->sync($validated['packages'] ?? []);

        $refreshed = $this->workflowService->refreshOpenRequestMatches();

        return redirect()->back()->with('success', "Vehicle and route details updated. Refreshed {$refreshed} open request match(es).");
    }

    private function ensureDriver($user): Driver
    {
        return $user->driver ?? Driver::create([
            'user_id' => $user->id,
            'phone' => $user->phone,
            'location' => $user->location,
            'status' => 'active',
        ]);
    }

    private function ensureDriverRoute(Driver $driver): DriverRoute
    {
        $driverRoute = $driver->driverRoutes()->with(['locations', 'packages'])->first();

        if (! $driverRoute) {
            $driverRoute = DriverRoute::create([
                'driver_id' => $driver->id,
                'vehicle_type' => 'bakkie',
                'max_load_size' => 'medium',
                'is_refrigerated' => false,
                'car_make' => null,
                'car_model' => null,
                'car_number' => null,
                'available' => false,
            ]);
        }

        return $driverRoute->loadMissing(['locations', 'packages']);
    }

}
