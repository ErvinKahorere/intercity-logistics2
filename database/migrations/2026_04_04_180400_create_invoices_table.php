<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parcel_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_number')->unique();
            $table->string('status')->default('issued');
            $table->string('payment_status')->default('pending');
            $table->string('booking_reference')->nullable();
            $table->string('tracking_number')->nullable();
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->decimal('base_fee', 12, 2)->default(0);
            $table->decimal('distance_fee', 12, 2)->default(0);
            $table->decimal('weight_fee', 12, 2)->default(0);
            $table->decimal('urgency_fee', 12, 2)->default(0);
            $table->decimal('special_handling_fee', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->json('pricing_breakdown')->nullable();
            $table->json('customer_snapshot')->nullable();
            $table->json('driver_snapshot')->nullable();
            $table->json('route_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
