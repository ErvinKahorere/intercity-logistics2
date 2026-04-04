<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('driver_route_package', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('driver_route_id');
            $table->unsignedBigInteger('package_type_id');
            $table->timestamps();

            $table->foreign('driver_route_id')->references('id')->on('driver_routes')->onDelete('cascade');
            $table->foreign('package_type_id')->references('id')->on('package_types')->onDelete('cascade');
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_route_package');
    }
};
