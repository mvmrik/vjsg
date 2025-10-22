<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserRememberToken;
use Illuminate\Support\Facades\Auth;

class RememberTokenController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['success' => false, 'message' => 'Not authenticated'], 401);

        $tokens = $user->rememberTokens()->orderBy('last_used_at', 'desc')->get()->map(function ($t) {
            return [
                'id' => $t->id,
                'device_name' => $t->device_name,
                'user_agent' => $t->user_agent,
                'ip' => $t->ip,
                'created_at' => $t->created_at,
                'last_used_at' => $t->last_used_at,
                'expires_at' => $t->expires_at,
            ];
        });

        return response()->json(['success' => true, 'tokens' => $tokens]);
    }

    public function destroy(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['success' => false, 'message' => 'Not authenticated'], 401);

        $token = $user->rememberTokens()->where('id', $id)->first();
        if (!$token) return response()->json(['success' => false, 'message' => 'Token not found'], 404);

        // Remember selector of the token being removed
        $selectorBeingRemoved = $token->selector;

        // Delete the DB record
        $token->delete();

        // If the request has the same remember_device cookie (current device), clear it and log out
        $cookie = $request->cookie(\App\Http\Middleware\AuthenticateRememberDevice::COOKIE_NAME);
        $loggedOut = false;
        if ($cookie) {
            $decoded = null;
            try {
                $decoded = base64_decode($cookie, true);
            } catch (\Exception $e) {}

            if ($decoded && strpos($decoded, ':') !== false) {
                [$sel, $val] = explode(':', $decoded, 2) + [null, null];
                if ($sel && $sel === $selectorBeingRemoved) {
                    // Clear cookie on client and logout current session
                    cookie()->queue(cookie()->forget(\App\Http\Middleware\AuthenticateRememberDevice::COOKIE_NAME));
                    try {
                        // Log out via Auth and clear legacy session flags
                        \Illuminate\Support\Facades\Auth::logout();
                        $request->session()->flush();
                    } catch (\Exception $e) {}
                    $loggedOut = true;
                }
            }
        }

        return response()->json(['success' => true, 'logged_out' => $loggedOut]);
    }

    public function destroyAll(Request $request)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['success' => false, 'message' => 'Not authenticated'], 401);

        $user->rememberTokens()->delete();

        // If current device had a cookie, clear it and logout this session
        $cookie = $request->cookie(\App\Http\Middleware\AuthenticateRememberDevice::COOKIE_NAME);
        $loggedOut = false;
        if ($cookie) {
            cookie()->queue(cookie()->forget(\App\Http\Middleware\AuthenticateRememberDevice::COOKIE_NAME));
            try {
                \Illuminate\Support\Facades\Auth::logout();
                $request->session()->flush();
            } catch (\Exception $e) {}
            $loggedOut = true;
        }

        return response()->json(['success' => true, 'logged_out' => $loggedOut]);
    }
}
