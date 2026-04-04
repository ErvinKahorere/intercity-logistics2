<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parcel_request_id')->nullable()->constrained('parcel_requests')->nullOnDelete();
            $table->string('title');
            $table->string('message', 500);
            $table->string('badge')->nullable();
            $table->string('tone')->default('info');
            $table->string('event_type')->default('general');
            $table->boolean('is_read')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_read', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};
