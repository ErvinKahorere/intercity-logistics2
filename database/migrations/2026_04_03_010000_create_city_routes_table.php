<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('city_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('origin_location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('destination_location_id')->constrained('locations')->cascadeOnDelete();
            $table->unsignedInteger('distance_km');
            $table->decimal('estimated_hours', 5, 1);
            $table->decimal('base_fare', 10, 2)->default(0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['origin_location_id', 'destination_location_id'], 'city_routes_origin_destination_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('city_routes');
    }
};
