<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CityRoute;
use App\Models\PackageType;
use App\Models\PricingRule;
use App\Services\PricingRulesService;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PricingOperationsController extends Controller
{
    public function __construct(
        private PricingRulesService $pricingRulesService,
        private PricingService $pricingService,
    ) {
    }

    public function page(): Response
    {
        return Inertia::render('Admin/Pricing/Index', [
            'rules' => $this->pricingRulesService->adminPayload(),
            'alerts' => $this->pricingRulesService->ruleAlerts(),
            'packageTypes' => PackageType::query()->orderBy('name')->get(['id', 'name', 'pricing_category']),
            'routes' => CityRoute::query()->with(['originLocation:id,name', 'destinationLocation:id,name'])->where('is_active', true)->orderBy('origin_location_id')->get()->map(fn (CityRoute $route) => [
                'id' => $route->id,
                'origin_location_id' => $route->origin_location_id,
                'destination_location_id' => $route->destination_location_id,
                'name' => ($route->originLocation?->name ?? 'Origin') . ' -> ' . ($route->destinationLocation?->name ?? 'Destination'),
            ])->values(),
        ]);
    }

    public function data(): JsonResponse
    {
        return response()->json([
            'rules' => $this->pricingRulesService->adminPayload(),
            'alerts' => $this->pricingRulesService->ruleAlerts(),
        ]);
    }

    public function saveRule(Request $request, ?PricingRule $pricingRule = null): JsonResponse
    {
        $validated = $request->validate([
            'rule_type' => ['required', 'in:global,weight_tier,urgency,parcel_type,route_override'],
            'rule_key' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'target_type' => ['nullable', 'string', 'max:50'],
            'target_id' => ['nullable', 'integer'],
            'config' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $rule = $this->pricingRulesService->saveRule($validated, $pricingRule);

        return response()->json([
            'message' => 'Pricing rule saved.',
            'rule' => $rule,
            'rules' => $this->pricingRulesService->adminPayload(),
            'alerts' => $this->pricingRulesService->ruleAlerts(),
        ]);
    }

    public function deleteRule(PricingRule $pricingRule): JsonResponse
    {
        $this->pricingRulesService->deleteRule($pricingRule);

        return response()->json([
            'message' => 'Pricing rule deleted.',
            'rules' => $this->pricingRulesService->adminPayload(),
            'alerts' => $this->pricingRulesService->ruleAlerts(),
        ]);
    }

    public function simulate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city_route_id' => ['nullable', Rule::exists('city_routes', 'id')->where('is_active', true)],
            'pickup_location_id' => ['required_without:city_route_id', 'exists:locations,id', 'different:dropoff_location_id'],
            'dropoff_location_id' => ['required_without:city_route_id', 'exists:locations,id'],
            'package_type_id' => ['required', 'exists:package_types,id'],
            'weight_kg' => ['nullable', 'numeric', 'min:0'],
            'load_size' => ['required', 'in:small,medium,large,heavy,oversized'],
            'urgency_level' => ['required', 'in:standard,express,same_day'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! empty($validated['city_route_id'])) {
            $route = CityRoute::query()->findOrFail($validated['city_route_id']);
            $validated['pickup_location_id'] = $route->origin_location_id;
            $validated['dropoff_location_id'] = $route->destination_location_id;
        }

        $quote = $this->pricingService->quote(
            (int) $validated['pickup_location_id'],
            (int) $validated['dropoff_location_id'],
            (int) $validated['package_type_id'],
            isset($validated['weight_kg']) ? (float) $validated['weight_kg'] : null,
            $validated['urgency_level'],
            $validated['load_size'],
            $validated['notes'] ?? null,
        );

        return response()->json([
            'simulation' => $quote,
        ]);
    }
}
