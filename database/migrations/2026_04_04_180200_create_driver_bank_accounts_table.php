<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status')->default('incomplete');
            $table->string('account_holder_name');
            $table->string('bank_name');
            $table->string('branch_name')->nullable();
            $table->string('branch_code')->nullable();
            $table->text('account_number');
            $table->string('account_number_last4', 4)->nullable();
            $table->string('account_type');
            $table->string('payout_reference_name')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_bank_accounts');
    }
};
