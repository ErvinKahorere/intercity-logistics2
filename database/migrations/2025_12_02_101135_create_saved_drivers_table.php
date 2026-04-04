<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Legacy duplicate migration retained so existing environments keep a consistent migration history.
        if (Schema::hasTable('saved_drivers')) {
            return;
        }

        Schema::create('saved_drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->unique(['user_id', 'driver_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_drivers');
    }
};
