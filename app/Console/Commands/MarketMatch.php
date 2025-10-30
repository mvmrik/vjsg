<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\MarketOrder;
use App\Models\MarketTrade;
use App\Models\MarketPrice;

class MarketMatch extends Command
{
    protected $signature = 'market:match';
    protected $description = 'Batch match market orders (run every 10 minutes)';

    public function handle()
    {
        $this->info('Starting market matching: ' . now()->toDateTimeString());

        // Get tool types that have open orders
        $toolTypes = DB::table('market_orders')
            ->select('tool_type_id')
            ->whereIn('status', ['open','partial'])
            ->groupBy('tool_type_id')
            ->pluck('tool_type_id');

        foreach ($toolTypes as $toolTypeId) {
            $this->info('Processing tool_type ' . $toolTypeId);
            // Loop matching top orders
            while (true) {
                // Get best buy (highest price, earliest) and best sell (lowest price, earliest)
                $buy = MarketOrder::where('tool_type_id', $toolTypeId)
                    ->whereIn('status', ['open','partial'])
                    ->where('side', 'buy')
                    ->orderBy('price', 'desc')
                    ->orderBy('created_at', 'asc')
                    ->lockForUpdate()
                    ->first();

                $sell = MarketOrder::where('tool_type_id', $toolTypeId)
                    ->whereIn('status', ['open','partial'])
                    ->where('side', 'sell')
                    ->orderBy('price', 'asc')
                    ->orderBy('created_at', 'asc')
                    ->lockForUpdate()
                    ->first();

                if (!$buy || !$sell) break;

                if ($buy->price < $sell->price) break; // no crossing

                // prevent self-trade
                if ($buy->user_id == $sell->user_id) {
                    // skip the older order to avoid self-trade
                    // cancel the newer one (safer to break)
                    break;
                }

                $buyRem = $buy->quantity - $buy->filled_quantity;
                $sellRem = $sell->quantity - $sell->filled_quantity;
                $tradeQty = min($buyRem, $sellRem);

                if ($tradeQty <= 0) break;

                // Price rule: use resting order price (sell's price if sell was resting)
                $tradePrice = $sell->price;

                DB::beginTransaction();
                try {
                    // Insert trade
                    $trade = MarketTrade::create([
                        'tool_type_id' => $toolTypeId,
                        'price' => $tradePrice,
                        'quantity' => $tradeQty,
                        'buyer_id' => $buy->user_id,
                        'seller_id' => $sell->user_id,
                        'buy_order_id' => $buy->id,
                        'sell_order_id' => $sell->id,
                        'executed_at' => now()
                    ]);

                    // Update orders filled quantities
                    $buy->filled_quantity += $tradeQty;
                    if ($buy->filled_quantity >= $buy->quantity) {
                        $buy->status = 'filled';
                    } else {
                        $buy->status = 'partial';
                    }
                    $buy->save();

                    $sell->filled_quantity += $tradeQty;
                    if ($sell->filled_quantity >= $sell->quantity) {
                        $sell->status = 'filled';
                    } else {
                        $sell->status = 'partial';
                    }
                    $sell->save();

                    // Transfer assets and funds
                    $tradeValue = $tradeQty * $tradePrice;

                    // Buyer: decrease reserved_balance by tradeValue
                    DB::table('users')->where('id', $buy->user_id)
                        ->decrement('reserved_balance', $tradeValue);

                    // Seller inventory: decrement reserved_count and decrement count
                    $sellerInv = DB::table('inventories')
                        ->where('user_id', $sell->user_id)
                        ->where('tool_type_id', $toolTypeId)
                        ->lockForUpdate()
                        ->first();

                    if ($sellerInv) {
                        $newReserved = max(0, intval($sellerInv->reserved_count) - $tradeQty);
                        $newCount = max(0, intval($sellerInv->count) - $tradeQty);
                        DB::table('inventories')
                            ->where('user_id', $sell->user_id)
                            ->where('tool_type_id', $toolTypeId)
                            ->update(['reserved_count' => $newReserved, 'count' => $newCount]);
                    } else {
                        // If seller has no inventory record, log and skip (shouldn't happen)
                        \Log::warning('market:match seller inventory missing for user ' . $sell->user_id . ' tool ' . $toolTypeId);
                    }

                    // Buyer inventory: increment count (create if not exists)
                    $buyerInv = DB::table('inventories')
                        ->where('user_id', $buy->user_id)
                        ->where('tool_type_id', $toolTypeId)
                        ->lockForUpdate()
                        ->first();

                    if ($buyerInv) {
                        DB::table('inventories')
                            ->where('user_id', $buy->user_id)
                            ->where('tool_type_id', $toolTypeId)
                            ->update(['count' => intval($buyerInv->count) + $tradeQty]);
                    } else {
                        DB::table('inventories')->insert([
                            'user_id' => $buy->user_id,
                            'tool_type_id' => $toolTypeId,
                            'count' => $tradeQty,
                            'temp_count' => 0,
                            'reserved_count' => 0,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }

                    // Credit seller balance
                    DB::table('users')->where('id', $sell->user_id)
                        ->increment('balance', $tradeValue);

                    // Apply fee to taker (incoming order). We'll treat the buyer as taker if buy was created later than sell
                    $taker = $buy->created_at > $sell->created_at ? $buy : $sell;
                    $takerUserId = $taker->user_id;
                    $feeBps = intval(DB::table('users')->where('id', $takerUserId)->value('fee_bps') ?? 1000);
                    $fee = intdiv($tradeValue * $feeBps, 10000);
                    if ($fee > 0) {
                        // deduct fee from taker's balance and credit to treasury
                        DB::table('users')->where('id', $takerUserId)->decrement('balance', $fee);
                        DB::table('market_treasury')->where('id', 1)->increment('balance', $fee);
                    }

                    // Update market price
                    DB::table('market_prices')->updateOrInsert(
                        ['tool_type_id' => $toolTypeId],
                        ['last_price' => $tradePrice, 'updated_at' => now()]
                    );

                    DB::commit();
                    $this->info("Traded {$tradeQty} @ {$tradePrice} for tool {$toolTypeId}");
                } catch (\Exception $e) {
                    DB::rollBack();
                    \Log::error('market:match failed: ' . $e->getMessage());
                    break;
                }
            }
        }

        $this->info('Market matching finished.');
        return 0;
    }
}
