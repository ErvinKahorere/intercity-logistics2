<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('saved_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained()->onDelete('cascade'); // the driver being saved
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // the user who saved the driver
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_contacts');
    }
};
