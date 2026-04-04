<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminAndDriverSeeder extends Seeder
{
    public function run(): void
    {
        // Admin
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'email' => 'admin@example.com',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );
        $this->command->info('Demo admin created successfully');
        $this->command->warn('Admin Login: admin@example.com | admin123');
    }
}
