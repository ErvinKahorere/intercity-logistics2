<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parcel_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 100)->index();
            $table->string('template_key', 120)->nullable();
            $table->string('provider', 50)->default('log');
            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone', 40)->nullable();
            $table->string('normalized_phone', 20)->nullable()->index();
            $table->text('message');
            $table->string('status', 30)->default('queued')->index();
            $table->string('provider_message_id')->nullable()->index();
            $table->json('provider_response')->nullable();
            $table->json('meta')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_notification_logs');
    }
};
