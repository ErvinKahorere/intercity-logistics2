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
        Schema::create('driver_routes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('driver_id')
                ->constrained('drivers')
                ->cascadeOnDelete();

            // Vehicle details
            $table->string('car_make')->nullable();
            $table->string('car_model')->nullable();
            $table->string('car_number')->nullable();
            $table->boolean('available')->default(true);

            $table->timestamps();
        });
    }



    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_routes');
    }
};
