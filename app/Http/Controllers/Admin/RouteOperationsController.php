<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CityRoute;
use App\Services\RouteManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RouteOperationsController extends Controller
{
    public function __construct(
        private RouteManagementService $routeManagementService,
    ) {
    }

    public function page(): Response
    {
        return Inertia::render('Admin/Routes/Index', [
            'routes' => $this->routeManagementService->list(),
            'locations' => $this->routeManagementService->locations(),
        ]);
    }

    public function data(): JsonResponse
    {
        return response()->json([
            'routes' => $this->routeManagementService->list(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $route = $this->routeManagementService->save($this->validated($request));

        return response()->json([
            'message' => 'Route saved successfully.',
            'route' => $route,
            'routes' => $this->routeManagementService->list(),
        ]);
    }

    public function update(Request $request, CityRoute $cityRoute): JsonResponse
    {
        $route = $this->routeManagementService->save($this->validated($request), $cityRoute);

        return response()->json([
            'message' => 'Route updated successfully.',
            'route' => $route,
            'routes' => $this->routeManagementService->list(),
        ]);
    }

    public function createReverse(CityRoute $cityRoute): JsonResponse
    {
        $reverse = $this->routeManagementService->createReverse($cityRoute);

        return response()->json([
            'message' => 'Reverse route created.',
            'route' => $reverse,
            'routes' => $this->routeManagementService->list(),
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'origin_location_id' => ['required', 'exists:locations,id', 'different:destination_location_id'],
            'destination_location_id' => ['required', 'exists:locations,id'],
            'route_code' => ['nullable', 'string', 'max:30'],
            'distance_km' => ['required', 'numeric', 'gt:0'],
            'estimated_hours' => ['required', 'numeric', 'gt:0'],
            'base_fare' => ['nullable', 'numeric', 'min:0'],
            'per_km_rate' => ['nullable', 'numeric', 'min:0'],
            'minimum_price' => ['nullable', 'numeric', 'min:0'],
            'distance_source' => ['required', 'in:manual,operational,estimated,fallback,approximate'],
            'reverse_route_enabled' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'operational_notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
