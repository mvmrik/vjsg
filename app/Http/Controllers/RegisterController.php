<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    /**
     * Register a new test user.
     *
     * POST /api/register
     * Body: { "username": "name", "locale": "en" }
     * Returns: { success: true, user_id, username, token, base_url }
     */
    public function register(Request $request)
    {
        $data = $request->validate([
            'username' => 'required|string|min:3|max:32|unique:users,username',
            'locale' => 'nullable|string|in:en,bg'
        ]);

        $username = $data['username'];
        $locale = $data['locale'] ?? 'en';

        // Generate keys for the user (public/private). We store lookup for private_key.
        $keys = User::generateKeys();
        $privateLookup = User::computeLookup($keys['private_key']);

            // Create user and store private_key lookup atomically (DB requires private_key)
            $user = User::create([
                'username' => $username,
                'public_key' => $keys['public_key'],
                'private_key' => $privateLookup,
                'last_active' => now(),
                'locale' => $locale,
                'balance' => 0,
            ]);

        // Create a personal access token
        $token = $user->createToken('api-token')->plainTextToken;

        // Base URL (use APP_URL if set, otherwise default to provided server)
        $baseUrl = config('app.url') ?: env('APP_URL', 'https://vjsg.cqlo.info/');

        // Persist created bot users locally so we can reuse them later (no DB migration needed)
        try {
            $storagePath = storage_path('app/bot_players.json');
            $existing = [];
            if (file_exists($storagePath)) {
                $existing = json_decode(file_get_contents($storagePath), true) ?: [];
            }
            $existing[] = [
                'user_id' => $user->id,
                'username' => $user->username,
                'token' => $token,
                'created_at' => now()->toDateTimeString(),
            ];
            @file_put_contents($storagePath, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } catch (\Throwable $e) {
            // ignore storage errors but continue returning token
        }

        return response()->json([
            'success' => true,
            'message' => 'Registration successful',
            'user_id' => $user->id,
            'username' => $user->username,
            'token' => $token,
            'base_url' => $baseUrl
        ], 201);
    }
}

