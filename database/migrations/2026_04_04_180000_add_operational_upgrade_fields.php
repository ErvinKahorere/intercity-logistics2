<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            if (! Schema::hasColumn('drivers', 'designation')) {
                $table->string('designation')->nullable()->after('status');
            }

            if (! Schema::hasColumn('drivers', 'speciality')) {
                $table->string('speciality')->nullable()->after('designation');
            }

            if (! Schema::hasColumn('drivers', 'about')) {
                $table->text('about')->nullable()->after('speciality');
            }

            if (! Schema::hasColumn('drivers', 'verification_status')) {
                $table->string('verification_status')->default('unverified')->after('about');
            }

            if (! Schema::hasColumn('drivers', 'verification_submitted_at')) {
                $table->timestamp('verification_submitted_at')->nullable()->after('verification_status');
            }

            if (! Schema::hasColumn('drivers', 'verification_rejection_reason')) {
                $table->text('verification_rejection_reason')->nullable()->after('verification_submitted_at');
            }

            if (! Schema::hasColumn('drivers', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('verification_rejection_reason');
            }

            if (! Schema::hasColumn('drivers', 'verified_by')) {
                $table->foreignId('verified_by')->nullable()->after('verified_at')->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('locations', function (Blueprint $table) {
            if (! Schema::hasColumn('locations', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('name');
            }

            if (! Schema::hasColumn('locations', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }

            if (! Schema::hasColumn('locations', 'region')) {
                $table->string('region')->nullable()->after('longitude');
            }
        });

        Schema::table('package_types', function (Blueprint $table) {
            if (! Schema::hasColumn('package_types', 'pricing_category')) {
                $table->string('pricing_category')->default('small_parcel')->after('name');
            }

            if (! Schema::hasColumn('package_types', 'pricing_multiplier')) {
                $table->decimal('pricing_multiplier', 6, 2)->default(1)->after('pricing_category');
            }

            if (! Schema::hasColumn('package_types', 'special_handling_fee')) {
                $table->decimal('special_handling_fee', 10, 2)->default(0)->after('pricing_multiplier');
            }
        });

        Schema::table('city_routes', function (Blueprint $table) {
            if (! Schema::hasColumn('city_routes', 'route_code')) {
                $table->string('route_code')->nullable()->after('destination_location_id');
            }

            if (! Schema::hasColumn('city_routes', 'distance_source')) {
                $table->string('distance_source')->default('operational')->after('distance_km');
            }

            if (! Schema::hasColumn('city_routes', 'road_adjustment_factor')) {
                $table->decimal('road_adjustment_factor', 5, 2)->default(1.18)->after('distance_source');
            }

            if (! Schema::hasColumn('city_routes', 'per_km_rate')) {
                $table->decimal('per_km_rate', 10, 2)->default(2.35)->after('base_fare');
            }

            if (! Schema::hasColumn('city_routes', 'minimum_price')) {
                $table->decimal('minimum_price', 10, 2)->default(120)->after('per_km_rate');
            }

            if (! Schema::hasColumn('city_routes', 'reverse_route_enabled')) {
                $table->boolean('reverse_route_enabled')->default(true)->after('minimum_price');
            }

            if (! Schema::hasColumn('city_routes', 'operational_notes')) {
                $table->text('operational_notes')->nullable()->after('reverse_route_enabled');
            }
        });

        Schema::table('parcel_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('parcel_requests', 'distance_fee')) {
                $table->decimal('distance_fee', 10, 2)->default(0)->after('base_price');
            }

            if (! Schema::hasColumn('parcel_requests', 'special_handling_fee')) {
                $table->decimal('special_handling_fee', 10, 2)->default(0)->after('urgency_surcharge');
            }

            if (! Schema::hasColumn('parcel_requests', 'minimum_charge')) {
                $table->decimal('minimum_charge', 10, 2)->default(0)->after('special_handling_fee');
            }

            if (! Schema::hasColumn('parcel_requests', 'parcel_multiplier')) {
                $table->decimal('parcel_multiplier', 6, 2)->default(1)->after('minimum_charge');
            }

            if (! Schema::hasColumn('parcel_requests', 'pricing_breakdown')) {
                $table->json('pricing_breakdown')->nullable()->after('parcel_multiplier');
            }
        });
    }

    public function down(): void
    {
        Schema::table('parcel_requests', function (Blueprint $table) {
            $columns = ['distance_fee', 'special_handling_fee', 'minimum_charge', 'parcel_multiplier', 'pricing_breakdown'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('parcel_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('city_routes', function (Blueprint $table) {
            $columns = ['route_code', 'distance_source', 'road_adjustment_factor', 'per_km_rate', 'minimum_price', 'reverse_route_enabled', 'operational_notes'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('city_routes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('package_types', function (Blueprint $table) {
            $columns = ['pricing_category', 'pricing_multiplier', 'special_handling_fee'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('package_types', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('locations', function (Blueprint $table) {
            $columns = ['latitude', 'longitude', 'region'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('locations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('drivers', function (Blueprint $table) {
            if (Schema::hasColumn('drivers', 'verified_by')) {
                $table->dropConstrainedForeignId('verified_by');
            }

            $columns = ['designation', 'speciality', 'about', 'verification_status', 'verification_submitted_at', 'verification_rejection_reason', 'verified_at'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('drivers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
