<?php

namespace App\Services;

use App\Models\PackageType;
use App\Models\PricingRule;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PricingRulesService
{
    public function globalConfig(): array
    {
        $rule = PricingRule::query()
            ->where('rule_type', 'global')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->first();

        return array_replace_recursive($this->defaultGlobalConfig(), $rule?->config ?? []);
    }

    public function resolveWeightRule(float $weightKg): ?PricingRule
    {
        return $this->activeRules('weight_tier')
            ->first(function (PricingRule $rule) use ($weightKg) {
                $min = (float) data_get($rule->config, 'min_weight', 0);
                $max = data_get($rule->config, 'max_weight');

                return $weightKg >= $min && ($max === null || $weightKg <= (float) $max);
            });
    }

    public function resolveUrgencyRule(string $urgencyLevel): ?PricingRule
    {
        return $this->activeRules('urgency')->firstWhere('rule_key', $urgencyLevel);
    }

    public function resolvePackageRule(?PackageType $packageType): ?PricingRule
    {
        if (! $packageType) {
            return null;
        }

        return PricingRule::query()
            ->where('rule_type', 'parcel_type')
            ->where('target_type', 'package_type')
            ->where('target_id', $packageType->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->first();
    }

    public function resolveRouteOverride(?int $cityRouteId): ?PricingRule
    {
        if (! $cityRouteId) {
            return null;
        }

        return PricingRule::query()
            ->where('rule_type', 'route_override')
            ->where('target_type', 'city_route')
            ->where('target_id', $cityRouteId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->first();
    }

    public function computeWeightFee(float $weightKg, string $loadSize): array
    {
        $global = $this->globalConfig();
        $tier = $this->resolveWeightRule($weightKg);
        $tierFee = (float) data_get($tier?->config, 'fee', 0);
        $tierMin = (float) data_get($tier?->config, 'min_weight', 0);
        $incremental = (float) data_get($tier?->config, 'incremental_fee_per_kg', 0);
        $extraWeight = $incremental > 0 ? max(0, $weightKg - $tierMin) : 0;
        $loadSurcharge = (float) data_get($global, "load_size_surcharges.$loadSize", 0);
        $fee = round($tierFee + ($extraWeight * $incremental) + $loadSurcharge, 2);

        return [
            'rule' => $tier,
            'fee' => $fee,
            'tier_fee' => round($tierFee, 2),
            'incremental_fee' => round($extraWeight * $incremental, 2),
            'load_surcharge' => round($loadSurcharge, 2),
        ];
    }

    public function computeUrgencyFee(string $urgencyLevel, float $runningTotal): array
    {
        $rule = $this->resolveUrgencyRule($urgencyLevel);
        $multiplier = (float) data_get($rule?->config, 'multiplier', 0);
        $flatFee = (float) data_get($rule?->config, 'flat_fee', 0);
        $fee = round(($runningTotal * $multiplier) + $flatFee, 2);

        return [
            'rule' => $rule,
            'multiplier' => $multiplier,
            'flat_fee' => round($flatFee, 2),
            'fee' => $fee,
        ];
    }

    public function packageAdjustments(?PackageType $packageType, string $loadSize): array
    {
        $global = $this->globalConfig();
        $rule = $this->resolvePackageRule($packageType);
        $defaultMultiplier = (float) data_get($global, "load_size_multipliers.$loadSize", 1);

        return [
            'rule' => $rule,
            'multiplier' => (float) data_get($rule?->config, 'multiplier', $packageType?->pricing_multiplier ?? $defaultMultiplier),
            'special_handling_fee' => (float) data_get($rule?->config, 'special_handling_fee', $packageType?->special_handling_fee ?? 0),
            'pricing_category' => data_get($rule?->config, 'pricing_category', $packageType?->pricing_category),
        ];
    }

    public function requiresExtraHandling(?string $pricingCategory, ?string $notes, string $loadSize): bool
    {
        $terms = collect(data_get($this->globalConfig(), 'special_handling_terms', []))
            ->filter()
            ->map(fn ($value) => strtolower((string) $value));

        $haystack = strtolower((string) $notes);

        return in_array($pricingCategory, ['fragile', 'refrigerated', 'vehicles', 'heavy', 'bulk_cargo', 'oversized'], true)
            || in_array($loadSize, ['heavy', 'oversized'], true)
            || $terms->contains(fn ($term) => str_contains($haystack, $term));
    }

    public function extraHandlingSurcharge(string $loadSize, float $weightKg): float
    {
        $global = $this->globalConfig();
        $base = match ($loadSize) {
            'heavy' => 120,
            'oversized' => 180,
            'large' => 55,
            default => (float) data_get($global, 'extra_handling_default', 30),
        };

        return round($base + ($weightKg > 100 ? (float) data_get($global, 'high_weight_extra_handling', 65) : 0), 2);
    }

    public function groupedRules(): array
    {
        return [
            'global' => $this->activeRules('global')->values()->all(),
            'weight_tiers' => $this->activeRules('weight_tier')->values()->all(),
            'urgency' => $this->activeRules('urgency')->values()->all(),
            'parcel_types' => $this->activeRules('parcel_type')->values()->all(),
            'route_overrides' => $this->activeRules('route_override')->values()->all(),
        ];
    }

    public function adminPayload(): array
    {
        return [
            'global' => $this->serializeRules($this->allRules('global')),
            'weight_tiers' => $this->serializeRules($this->allRules('weight_tier')),
            'urgency' => $this->serializeRules($this->allRules('urgency')),
            'parcel_types' => $this->serializeRules($this->allRules('parcel_type')),
            'route_overrides' => $this->serializeRules($this->allRules('route_override')),
        ];
    }

    public function saveRule(array $payload, ?PricingRule $rule = null): PricingRule
    {
        $payload = $this->normalizePayload($payload);
        $this->assertValidRule($payload, $rule);

        $rule ??= new PricingRule();
        $rule->fill($payload);
        $rule->save();

        if ($rule->rule_type === 'global' && $rule->is_active) {
            PricingRule::query()
                ->where('rule_type', 'global')
                ->whereKeyNot($rule->id)
                ->update(['is_active' => false]);
        }

        return $rule->fresh();
    }

    public function deleteRule(PricingRule $rule): void
    {
        $rule->delete();
    }

    public function ruleAlerts(): array
    {
        $packageTypes = PackageType::query()->get(['id', 'name']);
        $configuredIds = PricingRule::query()
            ->where('rule_type', 'parcel_type')
            ->where('is_active', true)
            ->where('target_type', 'package_type')
            ->pluck('target_id')
            ->filter()
            ->all();

        $missing = $packageTypes->whereNotIn('id', $configuredIds);

        return [
            'inactive_rules' => PricingRule::query()->where('is_active', false)->count(),
            'missing_parcel_type_rules' => $missing->map(fn (PackageType $type) => [
                'id' => $type->id,
                'name' => $type->name,
            ])->values()->all(),
        ];
    }

    private function activeRules(string $type): Collection
    {
        return $this->allRules($type)->where('is_active', true)->values();
    }

    private function allRules(string $type): Collection
    {
        return PricingRule::query()
            ->where('rule_type', $type)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function serializeRules(Collection $rules): array
    {
        return $rules->map(fn (PricingRule $rule) => [
            'id' => $rule->id,
            'rule_type' => $rule->rule_type,
            'rule_key' => $rule->rule_key,
            'name' => $rule->name,
            'description' => $rule->description,
            'target_type' => $rule->target_type,
            'target_id' => $rule->target_id,
            'config' => $rule->config ?? [],
            'is_active' => $rule->is_active,
            'sort_order' => $rule->sort_order,
            'updated_at' => optional($rule->updated_at)->toIso8601String(),
        ])->values()->all();
    }

    private function defaultGlobalConfig(): array
    {
        return [
            'base_fee_floor' => 55,
            'fallback_per_km_rate' => 2.35,
            'minimum_charge' => 120,
            'load_size_multipliers' => [
                'small' => 1,
                'medium' => 1.1,
                'large' => 1.22,
                'heavy' => 1.4,
                'oversized' => 1.6,
            ],
            'load_size_surcharges' => [
                'small' => 0,
                'medium' => 0,
                'large' => 35,
                'heavy' => 90,
                'oversized' => 160,
            ],
            'special_handling_terms' => ['fragile', 'forklift', 'mining'],
            'extra_handling_default' => 30,
            'high_weight_extra_handling' => 65,
        ];
    }

    private function normalizePayload(array $payload): array
    {
        $payload['rule_key'] = filled($payload['rule_key'] ?? null) ? trim((string) $payload['rule_key']) : null;
        $payload['target_type'] = filled($payload['target_type'] ?? null) ? trim((string) $payload['target_type']) : null;
        $payload['target_id'] = filled($payload['target_id'] ?? null) ? (int) $payload['target_id'] : null;
        $payload['config'] = $payload['config'] ?? [];
        $payload['is_active'] = (bool) ($payload['is_active'] ?? false);
        $payload['sort_order'] = (int) ($payload['sort_order'] ?? 0);

        return $payload;
    }

    private function assertValidRule(array $payload, ?PricingRule $rule = null): void
    {
        $errors = [];

        if ($payload['rule_type'] === 'parcel_type') {
            if (($payload['target_type'] ?? null) !== 'package_type' || empty($payload['target_id'])) {
                $errors['target_id'] = 'Parcel type rules must target a package type.';
            }
        }

        if ($payload['rule_type'] === 'route_override') {
            if (($payload['target_type'] ?? null) !== 'city_route' || empty($payload['target_id'])) {
                $errors['target_id'] = 'Route override rules must target a city route.';
            }
        }

        if ($payload['rule_type'] === 'urgency' && blank($payload['rule_key'] ?? null)) {
            $errors['rule_key'] = 'Urgency rules require a rule key such as standard, express, or same_day.';
        }

        if ($payload['rule_type'] === 'weight_tier') {
            $min = data_get($payload, 'config.min_weight');
            $max = data_get($payload, 'config.max_weight');

            if ($min === null) {
                $errors['config.min_weight'] = 'Weight tier rules require a min_weight value.';
            }

            if ($max !== null && (float) $max < (float) $min) {
                $errors['config.max_weight'] = 'max_weight must be greater than or equal to min_weight.';
            }

            if (! isset($errors['config.min_weight']) && $this->hasOverlappingWeightRule($payload, $rule)) {
                $errors['config.range'] = 'This weight tier overlaps with an existing weight tier rule.';
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function hasOverlappingWeightRule(array $payload, ?PricingRule $rule = null): bool
    {
        if (($payload['rule_type'] ?? null) !== 'weight_tier') {
            return false;
        }

        $min = (float) data_get($payload, 'config.min_weight', 0);
        $max = data_get($payload, 'config.max_weight');
        $max = $max === null ? null : (float) $max;

        return PricingRule::query()
            ->where('rule_type', 'weight_tier')
            ->when($rule?->exists, fn ($query) => $query->whereKeyNot($rule->id))
            ->get()
            ->contains(function (PricingRule $existing) use ($min, $max) {
                $existingMin = (float) data_get($existing->config, 'min_weight', 0);
                $existingMax = data_get($existing->config, 'max_weight');
                $existingMax = $existingMax === null ? null : (float) $existingMax;

                return ($max === null || $existingMin <= $max)
                    && ($existingMax === null || $min <= $existingMax);
            });
    }
}
