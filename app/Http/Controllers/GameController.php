<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Session;

class GameController extends Controller
{


    public function getUserData(Request $request)
    {
    // Prefer the resolved attribute set by ResolveGameUser middleware.
    // Fallback to session or Auth::id() for safety.
    $userId = $request->attributes->get('game_user_id') ?: $request->session()->get('user_id') ?: \Illuminate\Support\Facades\Auth::id();

        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Not logged in'], 401);
        }

        $user = User::find($userId);
        
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }
        
        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'public_key' => $user->public_key,
                // Do not expose private_key in API responses
                //'private_key' => $user->private_key,
                'locale' => $user->locale,
                'balance' => $user->balance,
                // Per-user market fee in basis points (bps). Frontend will display as percent (fee_bps/100)
                'fee_bps' => isset($user->fee_bps) ? intval($user->fee_bps) : null,
            ]
        ]);
    }

    public function updateUserData(Request $request)
    {
        $userId = $request->session()->get('user_id');
        
        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Not logged in'], 401);
        }

        $user = User::find($userId);
        
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        $validated = $request->validate([
            'locale' => 'sometimes|string|in:en,bg'
        ]);

        if (isset($validated['locale'])) {
            $user->locale = $validated['locale'];
            $user->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'User data updated successfully',
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'public_key' => $user->public_key,
                'private_key' => $user->private_key,
                'locale' => $user->locale,
                'balance' => $user->balance
            ]
        ]);
    }

    // Get per-user game settings
    public function getGameSettings(Request $request)
    {
        $userId = $request->session()->get('user_id');
        if (!$userId) return response()->json(['success' => false, 'message' => 'Not logged in'], 401);
        $settings = \App\Models\GameSetting::where('user_id', $userId)->get()->pluck('value', 'key')->toArray();
        return response()->json(['success' => true, 'settings' => $settings]);
    }

    // Set a per-user game setting
    public function setGameSetting(Request $request)
    {
        $userId = $request->session()->get('user_id');
        if (!$userId) return response()->json(['success' => false, 'message' => 'Not logged in'], 401);

        $validated = $request->validate([
            'key' => 'required|string',
            'value' => 'nullable|string'
        ]);

        // If the key is production_length_hours, validate numeric range
        if ($validated['key'] === 'production_length_hours') {
            $v = intval($validated['value']);
            if ($v < 1 || $v > 24) {
                return response()->json(['success' => false, 'message' => 'Invalid production_length_hours'], 422);
            }
            $validated['value'] = (string)$v;
        }

        $s = \App\Models\GameSetting::updateOrCreate([
            'user_id' => $userId,
            'key' => $validated['key']
        ], ['value' => $validated['value'] ?? null]);

        return response()->json(['success' => true, 'setting' => ['key' => $s->key, 'value' => $s->value]]);
    }
}
