<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id(); // UNSIGNED BIGINT (important!)
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->dateTime('appointment_time')->nullable();
            $table->string('status')->default('available');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('schedules');
    }
};
