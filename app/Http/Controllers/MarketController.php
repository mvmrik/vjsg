<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\MarketOrder;

class MarketController extends Controller
{
    public function order(Request $request)
    {
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
            if ($side === 'buy') {
                $cost = $price * $quantity;
                $user = DB::table('users')->where('id', $userId)->lockForUpdate()->first();
                $available = intval($user->balance) - intval($user->reserved_balance ?? 0);
                if ($available < $cost) {
                    DB::rollBack();
                    return response()->json(['success' => false, 'message' => 'Insufficient funds'], 400);
                }
                DB::table('users')->where('id', $userId)->increment('reserved_balance', $cost);
            } else {
                // sell: check inventory for any tool instances of this tool_type
                $inv = DB::table('inventories')->where('user_id', $userId)->where('tool_type_id', $toolTypeId)->lockForUpdate()->first();
                $have = $inv ? intval($inv->count) - intval($inv->reserved_count) : 0;
                if ($have < $quantity) {
                    DB::rollBack();
                    return response()->json(['success' => false, 'message' => 'Insufficient items to sell'], 400);
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
            \Log::error('market order create failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Server error'], 500);
        }
    }

    public function orderbook($toolTypeId)
    {
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
}
