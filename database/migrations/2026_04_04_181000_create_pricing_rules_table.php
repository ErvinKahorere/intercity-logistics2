<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pricing_rules')) {
            Schema::create('pricing_rules', function (Blueprint $table) {
                $table->id();
                $table->string('rule_type', 50);
                $table->string('rule_key', 100)->nullable();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('target_type', 50)->nullable();
                $table->unsignedBigInteger('target_id')->nullable();
                $table->json('config')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['rule_type', 'is_active']);
                $table->index(['target_type', 'target_id']);
            });
        }

        if (DB::table('pricing_rules')->exists()) {
            return;
        }

        DB::table('pricing_rules')->insert([
            [
                'rule_type' => 'global',
                'rule_key' => 'default',
                'name' => 'Default Pricing Profile',
                'description' => 'Primary global logistics pricing defaults.',
                'config' => json_encode([
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
                    'special_handling_terms' => ['fragile', 'forklift', 'mining', 'refrigerated'],
                    'extra_handling_default' => 30,
                    'high_weight_extra_handling' => 65,
                ]),
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'rule_type' => 'weight_tier',
                'rule_key' => '0-5',
                'name' => '0 to 5 kg',
                'description' => null,
                'config' => json_encode(['min_weight' => 0, 'max_weight' => 5, 'fee' => 0, 'incremental_fee_per_kg' => 0]),
                'is_active' => true,
                'sort_order' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'rule_type' => 'weight_tier',
                'rule_key' => '5-20',
                'name' => '5 to 20 kg',
                'description' => null,
                'config' => json_encode(['min_weight' => 5.01, 'max_weight' => 20, 'fee' => 65, 'incremental_fee_per_kg' => 0]),
                'is_active' => true,
                'sort_order' => 20,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'rule_type' => 'weight_tier',
                'rule_key' => '20-100',
                'name' => '20 to 100 kg',
                'description' => null,
                'config' => json_encode(['min_weight' => 20.01, 'max_weight' => 100, 'fee' => 180, 'incremental_fee_per_kg' => 0]),
                'is_active' => true,
                'sort_order' => 30,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'rule_type' => 'weight_tier',
                'rule_key' => '100+',
                'name' => '100 kg and above',
                'description' => null,
                'config' => json_encode(['min_weight' => 100.01, 'max_weight' => null, 'fee' => 180, 'incremental_fee_per_kg' => 3.6]),
                'is_active' => true,
                'sort_order' => 40,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'rule_type' => 'urgency',
                'rule_key' => 'standard',
                'name' => 'Standard',
                'description' => null,
                'config' => json_encode(['multiplier' => 0, 'flat_fee' => 0]),
                'is_active' => true,
                'sort_order' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'rule_type' => 'urgency',
                'rule_key' => 'express',
                'name' => 'Express',
                'description' => null,
                'config' => json_encode(['multiplier' => 0.18, 'flat_fee' => 0]),
                'is_active' => true,
                'sort_order' => 20,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'rule_type' => 'urgency',
                'rule_key' => 'same_day',
                'name' => 'Same Day',
                'description' => null,
                'config' => json_encode(['multiplier' => 0.33, 'flat_fee' => 0]),
                'is_active' => true,
                'sort_order' => 30,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        if (Schema::hasTable('package_types')) {
            $packageTypes = DB::table('package_types')->get();

            foreach ($packageTypes as $packageType) {
                DB::table('pricing_rules')->insert([
                    'rule_type' => 'parcel_type',
                    'rule_key' => Str::slug((string) ($packageType->pricing_category ?: $packageType->name)),
                    'name' => $packageType->name . ' Pricing',
                    'description' => 'Package-type specific pricing behavior.',
                    'target_type' => 'package_type',
                    'target_id' => $packageType->id,
                    'config' => json_encode([
                        'pricing_category' => $packageType->pricing_category,
                        'multiplier' => $packageType->pricing_multiplier ?? 1,
                        'special_handling_fee' => $packageType->special_handling_fee ?? 0,
                    ]),
                    'is_active' => true,
                    'sort_order' => 10,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_rules');
    }
};
