<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_licences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->string('licence_type_code');
            $table->string('licence_type_name');
            $table->string('licence_number')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date');
            $table->string('document_path');
            $table->string('document_original_name')->nullable();
            $table->string('document_mime_type')->nullable();
            $table->unsignedBigInteger('document_size')->nullable();
            $table->string('verification_status')->default('pending');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['driver_id', 'verification_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_licences');
    }
};
