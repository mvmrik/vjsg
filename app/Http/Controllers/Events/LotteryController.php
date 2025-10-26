<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\Events\Event;
use App\Models\Events\EventLottery;

class LotteryController extends Controller
{
    // Join the active lottery
    public function enter(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);

        $event = Event::where('is_active', true)->where('key', 'lottery')->first();
        if (!$event) return response()->json(['success' => false, 'message' => 'No active lottery'], 400);

        try {
            // log incoming payload for debugging
            \Log::debug('Lottery enter payload', $request->all());
            $data = $request->validate([
                'numbers' => 'required|array',
                'numbers.*' => 'integer|min:1|max:49',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        }

        $numbers = array_values(array_unique($data['numbers']));
        $count = count($numbers);
        if ($count < 6 || $count > 9) {
            return response()->json(['success' => false, 'message' => 'Choose between 6 and 9 numbers'], 400);
        }

        // stake multipliers
        $multipliers = [6 => 1, 7 => 10, 8 => 100, 9 => 1000];
        $mult = $multipliers[$count] ?? 1;
        $base = 1; // base stake, can be changed later to config
        $stake = intval($base) * $mult;

        // We'll implement communal jackpot instant draw: add stake to event.jackpot_balance,
        // compute payout as percentage of the (updated) jackpot, deduct payout from jackpot,
        // mark entry settled and return result immediately.
        DB::beginTransaction();
        try {
            // lock the event row to serialize concurrent plays
            $event = Event::where('id', $event->id)->lockForUpdate()->first();

            // If client provided an observed_jackpot, ensure it's not stale (i.e. server jackpot < observed)
            $observed = $request->input('observed_jackpot');
            if ($observed !== null) {
                $observed = intval($observed);
                if (($event->jackpot_balance ?? 0) < $observed) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'code' => 'JACKPOT_DECREASED',
                        'message' => 'Jackpot decreased since you last saw it',
                        'current_jackpot' => $event->jackpot_balance ?? 0,
                    ], 409);
                }
            }

            // check balance
            if (($user->balance ?? 0) < $stake) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Insufficient balance'], 400);
            }

            // deduct stake from user
            $user->balance = $user->balance - $stake;
            $user->save();

            // add stake to communal jackpot
            $event->jackpot_balance = ($event->jackpot_balance ?? 0) + $stake;
            $event->save();

            // store bet (will be settled immediately below)
            $entry = EventLottery::create([
                'event_id' => $event->id,
                'user_id' => $user->id,
                'numbers' => $numbers,
                'choice_count' => $count,
                'stake' => $stake,
                'settled' => false,
            ]);

            // Draw 6 unique numbers for this player's instant result
            $pool = range(1, 49);
            shuffle($pool);
            $drawNumbers = array_slice($pool, 0, 6);
            sort($drawNumbers);

            // Compute matches between player's numbers and draw
            $matches = count(array_intersect($numbers, $drawNumbers));

            // Payout percentages from user's specification
            $tierPercents = [6 => 1.0, 5 => 0.20, 4 => 0.04, 3 => 0.01];

            $payout = 0;
            $currentJackpot = $event->jackpot_balance;
            if ($matches >= 3) {
                $percent = $tierPercents[$matches] ?? 0;
                $payout = (int) floor($percent * $currentJackpot);
                if ($matches === 3 && $payout < 1) $payout = 1;
            }

            // If payout > 0, credit user and deduct from jackpot
            if ($payout > 0) {
                $user->balance = ($user->balance ?? 0) + $payout;
                $user->save();

                // deduct payout from event jackpot_balance
                $event->jackpot_balance = max(0, ($event->jackpot_balance ?? 0) - $payout);
                $event->save();
            }

            // mark entry settled and record payout/draw
            $entry->payout = $payout;
            $entry->settled = true;
            $entry->meta = array_merge($entry->meta ?? [], ['draw_numbers' => $drawNumbers]);
            $entry->save();

            DB::commit();

            // Return draw result with entry and new balance
            return response()->json([
                'success' => true,
                'entry' => $entry,
                'draw' => $drawNumbers,
                'payout' => $payout,
                'matches' => $matches,
                'new_balance' => $user->balance,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Lottery enter error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Server error'], 500);
        }
    }

    // Get current jackpot (sum of stakes)
    public function jackpot(Request $request)
    {
        $event = Event::where('is_active', true)->where('key', 'lottery')->first();
        if (!$event) return response()->json(['success' => true, 'jackpot' => 0]);
        // Return the stored jackpot balance (communal pool)
        $balance = $event->jackpot_balance ?? 0;
        return response()->json(['success' => true, 'jackpot' => $balance]);
    }


    // Return current user's betting history for the lottery
    public function history(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        $entries = EventLottery::where('user_id', $user->id)->orderBy('created_at', 'desc')->get();
        return response()->json(['success' => true, 'entries' => $entries]);
    }
}
