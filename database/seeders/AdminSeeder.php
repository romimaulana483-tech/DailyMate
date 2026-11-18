<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Create the initial administrator account.
     * Admin accounts are never created through public registration.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@dailymate.test'],
            [
                'name' => 'DailyMate Admin',
                'password' => Hash::make('Admin12345'),
                'role' => 'admin',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
    }
}
