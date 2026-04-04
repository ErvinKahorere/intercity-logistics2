<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parcel_status_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parcel_request_id')->constrained('parcel_requests')->cascadeOnDelete();
            $table->string('status');
            $table->string('actor_role')->default('system');
            $table->string('title');
            $table->text('message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parcel_status_updates');
    }
};
