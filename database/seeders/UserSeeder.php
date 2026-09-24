<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1 Organizer
        User::updateOrCreate(
            ['email' => 'organizer@example.com'],
            [
                'name' => 'Demo Organizer',
                'password' => Hash::make('password123'),
                'role' => User::ROLE_ORGANIZER,
                'email_verified_at' => now(),
            ]
        );

        // 2 Attendees
        User::updateOrCreate(
            ['email' => 'attendee1@example.com'],
            [
                'name' => 'Alice Attendee',
                'password' => Hash::make('password123'),
                'role' => User::ROLE_ATTENDEE,
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'attendee2@example.com'],
            [
                'name' => 'Bob Attendee',
                'password' => Hash::make('password123'),
                'role' => User::ROLE_ATTENDEE,
                'email_verified_at' => now(),
            ]
        );
    }
}
