<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parcel_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('parcel_requests', 'city_route_id')) {
                $table->foreignId('city_route_id')->nullable()->after('tracking_number')->constrained('city_routes')->nullOnDelete();
            }

            if (! Schema::hasColumn('parcel_requests', 'urgency_level')) {
                $table->string('urgency_level')->default('standard')->after('load_size');
            }

            if (! Schema::hasColumn('parcel_requests', 'distance_km')) {
                $table->unsignedInteger('distance_km')->nullable()->after('urgency_level');
            }

            if (! Schema::hasColumn('parcel_requests', 'estimated_hours')) {
                $table->decimal('estimated_hours', 5, 1)->nullable()->after('distance_km');
            }

            if (! Schema::hasColumn('parcel_requests', 'base_price')) {
                $table->decimal('base_price', 10, 2)->default(0)->after('estimated_hours');
            }

            if (! Schema::hasColumn('parcel_requests', 'weight_surcharge')) {
                $table->decimal('weight_surcharge', 10, 2)->default(0)->after('base_price');
            }

            if (! Schema::hasColumn('parcel_requests', 'urgency_surcharge')) {
                $table->decimal('urgency_surcharge', 10, 2)->default(0)->after('weight_surcharge');
            }

            if (! Schema::hasColumn('parcel_requests', 'total_price')) {
                $table->decimal('total_price', 10, 2)->default(0)->after('urgency_surcharge');
            }
        });
    }

    public function down(): void
    {
        Schema::table('parcel_requests', function (Blueprint $table) {
            if (Schema::hasColumn('parcel_requests', 'city_route_id')) {
                $table->dropConstrainedForeignId('city_route_id');
            }

            foreach (['urgency_level', 'distance_km', 'estimated_hours', 'base_price', 'weight_surcharge', 'urgency_surcharge', 'total_price'] as $column) {
                if (Schema::hasColumn('parcel_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
