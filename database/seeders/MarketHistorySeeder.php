<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\MarketTrade;
use App\Models\MarketPrice;
use App\Models\ToolType;

class MarketHistorySeeder extends Seeder
{
    /**
     * Generate simulated daily trades for the last N days for each of the first few tool types.
     */
    public function run()
    {
        $days = 30; // last 30 days
        $perDayMin = 5;
        $perDayMax = 20;

        $toolTypeIds = ToolType::limit(5)->pluck('id')->toArray();
        if (empty($toolTypeIds)) {
            $toolTypeIds = DB::table('tool_types')->limit(5)->pluck('id')->toArray();
        }
        if (empty($toolTypeIds)) {
            $this->command->info('No tool types found, skipping MarketHistorySeeder.');
            return;
        }

        // For each tool type, simulate a base price from market_prices or default
        foreach ($toolTypeIds as $tt) {
            $base = DB::table('market_prices')->where('tool_type_id', $tt)->value('last_price') ?: 100;
            // make base integer
            $base = intval($base ?: 100);

            for ($d = $days; $d >= 1; $d--) {
                // pick a day timestamp at midday UTC for that day
                $dt = \Carbon\Carbon::now()->subDays($d)->setTime(12,0,0);

                $count = rand($perDayMin, $perDayMax);
                for ($i = 0; $i < $count; $i++) {
                    // price fluctuates within +/-20% of base slowly over time
                    $volatility = 0.2; // 20%
                    $trend = (rand(-10,10) / 100.0) * ($days - $d) / $days; // small trend
                    $randFactor = (rand(-100,100) / 100.0) * $volatility;
                    $priceFloat = max(1, $base * (1 + $trend + $randFactor));
                    $price = intval(round($priceFloat));

                    $quantity = rand(1, 10);

                    // spread the trades through the day
                    $secondsOffset = rand(0, 24*3600 - 1);
                    $executedAt = $dt->copy()->addSeconds($secondsOffset);

                    MarketTrade::create([
                        'tool_type_id' => $tt,
                        'price' => $price,
                        'quantity' => $quantity,
                        'buyer_id' => 1,
                        'seller_id' => 1,
                        'buy_order_id' => null,
                        'sell_order_id' => null,
                        'executed_at' => $executedAt,
                        'created_at' => $executedAt,
                        'updated_at' => $executedAt,
                    ]);
                }

                // update market_prices last_price to last trade of the day
                $lastPrice = DB::table('market_trades')->where('tool_type_id', $tt)->whereDate('executed_at', $dt->toDateString())->orderBy('executed_at','desc')->value('price');
                if ($lastPrice) {
                    MarketPrice::updateOrCreate(['tool_type_id' => $tt], ['last_price' => $lastPrice, 'vwap_24h' => $lastPrice, 'updated_at' => $dt]);
                }
            }
        }

        $this->command->info('MarketHistorySeeder: inserted simulated trades for last ' . $days . ' days');
    }
}
