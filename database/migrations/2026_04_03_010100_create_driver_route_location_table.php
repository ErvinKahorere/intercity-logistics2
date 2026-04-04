<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('driver_route_location')) {
            return;
        }

        Schema::create('driver_route_location', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_route_id')->constrained('driver_routes')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['driver_route_id', 'location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_route_location');
    }
};
