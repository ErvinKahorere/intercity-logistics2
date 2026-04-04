<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('drivers')) {
            Schema::create('drivers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('location')->nullable();
                $table->string('phone')->nullable();
                $table->string('status')->default('active');
                $table->foreignId('pickup_location_id')->nullable()->constrained('locations');
                $table->foreignId('delivery_location_id')->nullable()->constrained('locations');
                $table->string('package_type')->nullable();
                $table->timestamps();
            });

            return;
        }

        Schema::table('drivers', function (Blueprint $table) {
            if (! Schema::hasColumn('drivers', 'pickup_location_id')) {
                $table->foreignId('pickup_location_id')->nullable()->constrained('locations');
            }

            if (! Schema::hasColumn('drivers', 'delivery_location_id')) {
                $table->foreignId('delivery_location_id')->nullable()->constrained('locations');
            }

            if (! Schema::hasColumn('drivers', 'package_type')) {
                $table->string('package_type')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('drivers')) {
            return;
        }

        Schema::table('drivers', function (Blueprint $table) {
            if (Schema::hasColumn('drivers', 'pickup_location_id')) {
                $table->dropConstrainedForeignId('pickup_location_id');
            }

            if (Schema::hasColumn('drivers', 'delivery_location_id')) {
                $table->dropConstrainedForeignId('delivery_location_id');
            }

            if (Schema::hasColumn('drivers', 'package_type')) {
                $table->dropColumn('package_type');
            }
        });
    }
};
