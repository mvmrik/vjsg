<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Inventory;
use App\Models\MarketOrder;
use App\Models\MarketTrade;
use App\Models\MarketPrice;
use App\Models\ToolType;

class MarketSeeder extends Seeder
{
    public function run()
    {
        // Ensure tool types exist
        $this->call(ToolSeeder::class);

        // Create demo users
        $keys = User::generateKeys();
        $test = User::firstOrCreate(
            ['username' => 'market_seller1'],
            [
                'public_key' => $keys['public_key'],
                'private_key' => $keys['private_key'],
                'last_active' => now(),
            ]
        );

        $keys = User::generateKeys();
        $seller2 = User::firstOrCreate(
            ['username' => 'market_seller2'],
            [
                'public_key' => $keys['public_key'],
                'private_key' => $keys['private_key'],
                'last_active' => now(),
            ]
        );

        $keys = User::generateKeys();
        $buyer = User::firstOrCreate(
            ['username' => 'market_buyer1'],
            [
                'public_key' => $keys['public_key'],
                'private_key' => $keys['private_key'],
                'last_active' => now(),
            ]
        );

        // Give buyer some balance so buy orders look valid
        DB::table('users')->where('id', $buyer->id)->update(['balance' => 100000]);
        DB::table('users')->where('id', $test->id)->update(['balance' => 1000]);
        DB::table('users')->where('id', $seller2->id)->update(['balance' => 500]);

        // Pick 3 tool types to seed market for
        $toolTypeIds = ToolType::limit(3)->pluck('id')->toArray();
        if (empty($toolTypeIds)) {
            // fallback: look up by name
            $toolTypeIds = DB::table('tool_types')->limit(3)->pluck('id')->toArray();
        }

        // Ensure sellers have inventory for sell orders
        foreach ($toolTypeIds as $i => $tt) {
            Inventory::updateOrCreate(
                ['user_id' => $test->id, 'tool_type_id' => $tt],
                ['count' => 20 + ($i * 5), 'temp_count' => 0]
            );
            Inventory::updateOrCreate(
                ['user_id' => $seller2->id, 'tool_type_id' => $tt],
                ['count' => 10 + ($i * 3), 'temp_count' => 0]
            );
        }

        // Clear existing market sample data for the chosen tool types to avoid duplicates
        DB::table('market_orders')->whereIn('tool_type_id', $toolTypeIds)->delete();
        DB::table('market_trades')->whereIn('tool_type_id', $toolTypeIds)->delete();
        DB::table('market_prices')->whereIn('tool_type_id', $toolTypeIds)->delete();

        // Create sample sell and buy orders
        foreach ($toolTypeIds as $index => $tt) {
            // Sellers place sell orders at slightly different prices
            MarketOrder::create([
                'user_id' => $test->id,
                'tool_type_id' => $tt,
                'side' => 'sell',
                'price' => 100 + ($index * 5),
                'quantity' => 5 + ($index * 2),
                'filled_quantity' => 0,
                'status' => 'open',
                'created_at' => now()->subMinutes(30),
                'updated_at' => now()->subMinutes(30),
            ]);

            MarketOrder::create([
                'user_id' => $seller2->id,
                'tool_type_id' => $tt,
                'side' => 'sell',
                'price' => 95 + ($index * 3),
                'quantity' => 3 + $index,
                'filled_quantity' => 0,
                'status' => 'open',
                'created_at' => now()->subMinutes(20),
                'updated_at' => now()->subMinutes(20),
            ]);

            // Buyer places buy orders
            MarketOrder::create([
                'user_id' => $buyer->id,
                'tool_type_id' => $tt,
                'side' => 'buy',
                'price' => 98 + ($index * 2),
                'quantity' => 4,
                'filled_quantity' => 0,
                'status' => 'open',
                'created_at' => now()->subMinutes(10),
                'updated_at' => now()->subMinutes(10),
            ]);

            // Add a sample executed trade to show recent trades
            MarketTrade::create([
                'tool_type_id' => $tt,
                'price' => 99 + $index,
                'quantity' => 2 + $index,
                'buyer_id' => $buyer->id,
                'seller_id' => $test->id,
                'buy_order_id' => null,
                'sell_order_id' => null,
                'executed_at' => now()->subMinutes(5),
                'created_at' => now()->subMinutes(5),
                'updated_at' => now()->subMinutes(5),
            ]);

            // Update market price for this tool
            MarketPrice::updateOrCreate(
                ['tool_type_id' => $tt],
                ['last_price' => 99 + $index, 'vwap_24h' => 98 + $index, 'updated_at' => now()]
            );
        }

        $this->command->info('Market sample data seeded (orders, trades, prices).');
    }
}
