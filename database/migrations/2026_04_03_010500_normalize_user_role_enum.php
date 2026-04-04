<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'role')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('user','Regular User','Driver','admin') NOT NULL DEFAULT 'user'");
            DB::table('users')->where('role', 'Regular User')->update(['role' => 'user']);
            DB::statement("ALTER TABLE users MODIFY role ENUM('user','Driver','admin') NOT NULL DEFAULT 'user'");
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'role')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('user','Regular User','Driver','admin') NOT NULL DEFAULT 'user'");
            DB::table('users')->where('role', 'user')->update(['role' => 'Regular User']);
            DB::statement("ALTER TABLE users MODIFY role ENUM('Regular User','Driver','admin') NOT NULL DEFAULT 'Regular User'");
        }
    }
};
