<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parcel_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tracking_number')->unique();
            $table->foreignId('pickup_location_id')->constrained('locations');
            $table->foreignId('dropoff_location_id')->constrained('locations');
            $table->foreignId('package_type_id')->constrained('package_types');
            $table->string('pickup_address')->nullable();
            $table->string('dropoff_address')->nullable();
            $table->string('receiver_name');
            $table->string('receiver_phone');
            $table->decimal('weight_kg', 10, 2)->nullable();
            $table->enum('load_size', ['small', 'medium', 'large', 'heavy', 'oversized'])->default('small');
            $table->decimal('declared_value', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('pending_match');
            $table->foreignId('assigned_driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->json('matched_driver_ids')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parcel_requests');
    }
};
