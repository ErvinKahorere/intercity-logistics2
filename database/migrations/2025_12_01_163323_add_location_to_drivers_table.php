<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('drivers') || Schema::hasColumn('drivers', 'location')) {
            return;
        }

        Schema::table('drivers', function (Blueprint $table) {
            $table->string('location')->nullable()->after('user_id');
        });
    }

    public function down()
    {
        if (! Schema::hasTable('drivers') || ! Schema::hasColumn('drivers', 'location')) {
            return;
        }

        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn('location');
        });
    }
};
