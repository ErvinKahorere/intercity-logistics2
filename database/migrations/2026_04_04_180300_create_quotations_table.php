<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parcel_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('quotation_number')->unique();
            $table->string('status')->default('issued');
            $table->date('issue_date');
            $table->date('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('pickup_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('dropoff_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('package_type_id')->nullable()->constrained('package_types')->nullOnDelete();
            $table->decimal('weight_kg', 10, 2)->nullable();
            $table->string('load_size')->nullable();
            $table->string('urgency_level')->nullable();
            $table->decimal('distance_km', 10, 2)->default(0);
            $table->decimal('estimated_hours', 8, 2)->default(0);
            $table->decimal('base_fee', 12, 2)->default(0);
            $table->decimal('distance_fee', 12, 2)->default(0);
            $table->decimal('weight_fee', 12, 2)->default(0);
            $table->decimal('urgency_fee', 12, 2)->default(0);
            $table->decimal('special_handling_fee', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->json('pricing_breakdown')->nullable();
            $table->json('customer_snapshot')->nullable();
            $table->json('route_snapshot')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
