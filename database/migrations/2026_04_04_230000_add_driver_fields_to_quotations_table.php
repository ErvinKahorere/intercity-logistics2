<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            if (! Schema::hasColumn('quotations', 'driver_id')) {
                $table->foreignId('driver_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('quotations', 'driver_snapshot')) {
                $table->json('driver_snapshot')->nullable()->after('customer_snapshot');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            if (Schema::hasColumn('quotations', 'driver_snapshot')) {
                $table->dropColumn('driver_snapshot');
            }

            if (Schema::hasColumn('quotations', 'driver_id')) {
                $table->dropConstrainedForeignId('driver_id');
            }
        });
    }
};
