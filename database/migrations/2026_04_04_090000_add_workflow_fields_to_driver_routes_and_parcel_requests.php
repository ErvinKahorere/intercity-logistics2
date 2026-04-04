<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_routes', function (Blueprint $table) {
            if (! Schema::hasColumn('driver_routes', 'vehicle_type')) {
                $table->string('vehicle_type')->default('bakkie')->after('driver_id');
            }

            if (! Schema::hasColumn('driver_routes', 'max_load_size')) {
                $table->enum('max_load_size', ['small', 'medium', 'large', 'heavy', 'oversized'])
                    ->default('medium')
                    ->after('vehicle_type');
            }

            if (! Schema::hasColumn('driver_routes', 'is_refrigerated')) {
                $table->boolean('is_refrigerated')->default(false)->after('max_load_size');
            }
        });

        Schema::table('parcel_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('parcel_requests', 'matched_at')) {
                $table->timestamp('matched_at')->nullable()->after('matched_driver_ids');
            }

            if (! Schema::hasColumn('parcel_requests', 'accepted_at')) {
                $table->timestamp('accepted_at')->nullable()->after('matched_at');
            }

            if (! Schema::hasColumn('parcel_requests', 'picked_up_at')) {
                $table->timestamp('picked_up_at')->nullable()->after('accepted_at');
            }

            if (! Schema::hasColumn('parcel_requests', 'in_transit_at')) {
                $table->timestamp('in_transit_at')->nullable()->after('picked_up_at');
            }

            if (! Schema::hasColumn('parcel_requests', 'arrived_at')) {
                $table->timestamp('arrived_at')->nullable()->after('in_transit_at');
            }

            if (! Schema::hasColumn('parcel_requests', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('arrived_at');
            }

            if (! Schema::hasColumn('parcel_requests', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('delivered_at');
            }

            if (! Schema::hasColumn('parcel_requests', 'final_price')) {
                $table->decimal('final_price', 10, 2)->nullable()->after('total_price');
            }

            if (! Schema::hasColumn('parcel_requests', 'status_note')) {
                $table->string('status_note')->nullable()->after('notes');
            }
        });

        DB::table('parcel_requests')
            ->where('status', 'pending_match')
            ->update(['status' => 'pending']);

    }

    public function down(): void
    {
        Schema::table('driver_routes', function (Blueprint $table) {
            foreach (['vehicle_type', 'max_load_size', 'is_refrigerated'] as $column) {
                if (Schema::hasColumn('driver_routes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('parcel_requests', function (Blueprint $table) {
            foreach ([
                'matched_at',
                'accepted_at',
                'picked_up_at',
                'in_transit_at',
                'arrived_at',
                'delivered_at',
                'cancelled_at',
                'final_price',
                'status_note',
            ] as $column) {
                if (Schema::hasColumn('parcel_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
