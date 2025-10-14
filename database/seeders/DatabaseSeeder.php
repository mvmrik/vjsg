<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $keys = \App\Models\User::generateKeys();

        \App\Models\User::create([
            'username' => 'testuser',
            'public_key' => $keys['public_key'],
            'private_key' => $keys['private_key'],
            'last_active' => now(),
        ]);

        // Removed parcel seeding for testing first claim
    }
}
