<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\MarketOrder;

class MarketController extends Controller
{
    public function order(Request $request)
    {
        // Prevent placing orders for free/raw resources that are not tradable
        // Use IDs to avoid i18n issues: tool type IDs 1 and 2 are raw resources.
        $excludedIds = [1, 2];
        $toolTypeIdInput = intval($request->input('tool_type_id'));
        if (in_array($toolTypeIdInput, $excludedIds)) {
            return response()->json(['success' => false, 'message' => 'This item cannot be traded on the market'], 400);
        }

        $userId = $request->session()->get('user_id') ?: auth()->id();
        if (!$userId) return response()->json(['success' => false, 'message' => 'Not authenticated'], 401);

        $data = $request->validate([
            'tool_type_id' => 'required|integer',
            'side' => 'required|in:buy,sell',
            'price' => 'required|integer|min:1',
            'quantity' => 'required|integer|min:1'
        ]);

        $toolTypeId = intval($data['tool_type_id']);
        $side = $data['side'];
        $price = intval($data['price']);
        $quantity = intval($data['quantity']);

        DB::beginTransaction();
        try {
            // Lock user row first to check fee availability (prevents negative balance scenarios)
            $user = DB::table('users')->where('id', $userId)->lockForUpdate()->first();
            $feeBps = intval($user->fee_bps ?? 1000);
            $potentialTradeValue = $price * $quantity;
            $potentialFee = intdiv($potentialTradeValue * $feeBps, 10000);

            if ($side === 'buy') {
                $cost = $potentialTradeValue;
                $available = intval($user->balance) - intval($user->reserved_balance ?? 0);
                // Ensure user can pay both the cost (reserved) and the fee (from balance)
                if ($available < ($cost + $potentialFee)) {
                    DB::rollBack();
                    return response()->json(['success' => false, 'message' => "Insufficient funds. Available: $available, need for order+fee: " . ($cost + $potentialFee)], 400);
                }
                DB::table('users')->where('id', $userId)->increment('reserved_balance', $cost);
            } else {
                // sell: check inventory for any tool instances of this tool_type
                $inv = DB::table('inventories')->where('user_id', $userId)->where('tool_type_id', $toolTypeId)->lockForUpdate()->first();
                $have = $inv ? intval($inv->count) - intval($inv->reserved_count) : 0;
                if ($have < $quantity) {
                    DB::rollBack();
                    $toolTypeName = DB::table('tool_types')->where('id', $toolTypeId)->value('name') ?? 'items';
                    return response()->json(['success' => false, 'message' => "Insufficient $toolTypeName to sell. You have: $have, need: $quantity"], 400);
                }
                // Ensure seller has enough balance to cover the potential fee (prevent negative on fee debit)
                if (intval($user->balance) < $potentialFee) {
                    DB::rollBack();
                    return response()->json(['success' => false, 'message' => "Insufficient balance to cover potential fee: need $potentialFee, have " . intval($user->balance)], 400);
                }
                DB::table('inventories')->where('user_id', $userId)->where('tool_type_id', $toolTypeId)->increment('reserved_count', $quantity);
            }

            $orderId = MarketOrder::create([
                'user_id' => $userId,
                'tool_type_id' => $toolTypeId,
                'side' => $side,
                'price' => $price,
                'quantity' => $quantity,
                'status' => 'open'
            ])->id;

            DB::commit();
            return response()->json(['success' => true, 'order_id' => $orderId]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('market order create failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Server error'], 500);
        }
    }

    public function orderbook($toolTypeId)
    {
        // If the requested tool type is excluded from market, return empty orderbook
        $excludedIds = [1, 2];
        if (in_array(intval($toolTypeId), $excludedIds)) {
            return response()->json(['success' => true, 'bids' => [], 'asks' => []]);
        }

        $bids = MarketOrder::where('tool_type_id', $toolTypeId)->where('side', 'buy')->whereIn('status', ['open','partial'])
            ->select(DB::raw('price, SUM(quantity-filled_quantity) as volume'))
            ->groupBy('price')->orderBy('price','desc')->limit(20)->get();

        $asks = MarketOrder::where('tool_type_id', $toolTypeId)->where('side', 'sell')->whereIn('status', ['open','partial'])
            ->select(DB::raw('price, SUM(quantity-filled_quantity) as volume'))
            ->groupBy('price')->orderBy('price','asc')->limit(20)->get();

        return response()->json(['success' => true, 'bids' => $bids, 'asks' => $asks]);
    }

    public function trades($toolTypeId)
    {
        // If the requested tool type is excluded from market, return empty trades
        $excludedIds = [1, 2];
        if (in_array(intval($toolTypeId), $excludedIds)) {
            return response()->json(['success' => true, 'trades' => []]);
        }

        $trades = DB::table('market_trades')->where('tool_type_id', $toolTypeId)->orderBy('executed_at','desc')->limit(100)->get();
        return response()->json(['success' => true, 'trades' => $trades]);
    }

    public function userOrders(Request $request)
    {
        $userId = $request->session()->get('user_id') ?: auth()->id();
        if (!$userId) return response()->json(['success' => false, 'message' => 'Not authenticated'], 401);

        $orders = MarketOrder::where('user_id', $userId)->orderBy('created_at','desc')->limit(200)->get();
        return response()->json(['success' => true, 'orders' => $orders]);
    }

    public function getUserBalance(Request $request)
    {
        $userId = $request->session()->get('user_id') ?: auth()->id();
        if (!$userId) return response()->json(['success' => false, 'message' => 'Not authenticated'], 401);

        $user = DB::table('users')->where('id', $userId)->first();
        if (!$user) return response()->json(['success' => false], 404);

        $available = intval($user->balance) - intval($user->reserved_balance ?? 0);
        return response()->json(['balance' => $available]);
    }

    public function getUserInventory(Request $request, $toolTypeId)
    {
        $userId = $request->session()->get('user_id') ?: auth()->id();
        if (!$userId) return response()->json(['success' => false, 'message' => 'Not authenticated'], 401);

        $inv = DB::table('inventories')->where('user_id', $userId)->where('tool_type_id', $toolTypeId)->first();
        $available = $inv ? intval($inv->count) - intval($inv->reserved_count ?? 0) : 0;
        
        return response()->json(['count' => $available]);
    }

    public function cancelOrder(Request $request, $id)
    {
        $userId = $request->session()->get('user_id') ?: auth()->id();
        if (!$userId) return response()->json(['success' => false, 'message' => 'Not authenticated'], 401);

        $order = DB::table('market_orders')->where('id', $id)->lockForUpdate()->first();
        if (!$order) return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        if (intval($order->user_id) !== intval($userId)) return response()->json(['success' => false, 'message' => 'Not authorized'], 403);

        if (in_array($order->status, ['filled','cancelled'])) {
            return response()->json(['success' => false, 'message' => 'Order cannot be cancelled'], 400);
        }

        $remaining = intval($order->quantity) - intval($order->filled_quantity);
        if ($remaining <= 0) {
            return response()->json(['success' => false, 'message' => 'Nothing to cancel'], 400);
        }

        DB::beginTransaction();
        try {
            if ($order->side === 'buy') {
                $amount = intval($order->price) * $remaining;
                // lock user row and decrement reserved_balance safely
                $user = DB::table('users')->where('id', $userId)->lockForUpdate()->first();
                if ($user) {
                    $newReserved = max(0, intval($user->reserved_balance ?? 0) - $amount);
                    DB::table('users')->where('id', $userId)->update(['reserved_balance' => $newReserved]);
                }
            } else {
                // sell: release reserved_count
                $inv = DB::table('inventories')
                    ->where('user_id', $userId)
                    ->where('tool_type_id', $order->tool_type_id)
                    ->lockForUpdate()
                    ->first();
                if ($inv) {
                    $newReserved = max(0, intval($inv->reserved_count ?? 0) - $remaining);
                    DB::table('inventories')
                        ->where('user_id', $userId)
                        ->where('tool_type_id', $order->tool_type_id)
                        ->update(['reserved_count' => $newReserved]);
                }
            }

            // mark order as cancelled
            DB::table('market_orders')->where('id', $id)->update(['status' => 'cancelled', 'updated_at' => now()]);

            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('market order cancel failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Server error'], 500);
        }
    }
}
