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

        \App\Models\User::firstOrCreate(
            ['username' => 'testuser'],
            [
                'public_key' => $keys['public_key'],
                'private_key' => $keys['private_key'],
                'last_active' => now(),
            ]
        );
        // Seed object types if table exists and is empty
        if (\Schema::hasTable('object_types')) {
            $count = \App\Models\ObjectType::count();
            if ($count === 0) {
                \App\Models\ObjectType::insert([
                    ['type' => 'house', 'name' => 'Къща', 'icon' => 'cilHome', 'build_time_minutes' => 5, 'meta' => json_encode(['width'=>3,'height'=>3]), 'created_at' => now(), 'updated_at' => now()],
                    ['type' => 'tree', 'name' => 'Дърво', 'icon' => 'cilTree', 'build_time_minutes' => 1, 'meta' => json_encode(['width'=>1,'height'=>1]), 'created_at' => now(), 'updated_at' => now()],
                    ['type' => 'well', 'name' => 'Кладенец', 'icon' => 'cilDrop', 'build_time_minutes' => 2, 'meta' => json_encode(['width'=>2,'height'=>2]), 'created_at' => now(), 'updated_at' => now()],
                    ['type' => 'barn', 'name' => 'Хамбар', 'icon' => 'cilStorage', 'build_time_minutes' => 8, 'meta' => json_encode(['width'=>4,'height'=>2]), 'created_at' => now(), 'updated_at' => now()]
                ]);
            }
        }

            // Seed tool types and market demo data
            if (\Schema::hasTable('tool_types')) {
                $this->call(ToolSeeder::class);
            }
            if (\Schema::hasTable('market_orders')) {
                $this->call(MarketSeeder::class);
            }
            if (\Schema::hasTable('market_trades')) {
                // Add simulated history only when requested (safe default for local/dev)
                $this->call(MarketHistorySeeder::class);
            }
    }
}
