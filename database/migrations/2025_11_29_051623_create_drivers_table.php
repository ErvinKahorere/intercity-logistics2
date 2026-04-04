<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('drivers')) {
            Schema::table('drivers', function (Blueprint $table) {
                if (! Schema::hasColumn('drivers', 'user_id')) {
                    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                }

                if (! Schema::hasColumn('drivers', 'phone')) {
                    $table->string('phone')->nullable();
                }

                if (! Schema::hasColumn('drivers', 'status')) {
                    $table->string('status')->default('active');
                }
            });

            return;
        }

        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('phone')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
