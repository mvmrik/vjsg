<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Hash;
use App\Models\UserRememberToken;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showHomepage()
    {
        return view('auth.homepage');
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    public function register(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:255|unique:users',
        ]);

        $keys = User::generateKeys();

    // Compute lookup (HMAC over sha256 if PRIVATE_KEY_LOOKUP_KEY is set)
    $lookup = User::computeLookup($keys['private_key']);

    // Store lookup in private_key column (single-column approach A)
    $user = new User();
    $user->username = $request->username;
    $user->public_key = $keys['public_key'];
    $user->private_key = $lookup;
    $user->last_active = now();
    $user->balance = 0;
    $user->save();

        // Add 2 level 1 people as one record
        \App\Models\Person::create(['user_id' => $user->id, 'level' => 1, 'count' => 2]);

        // Auto-login
        $request->session()->put('user_id', $user->id);
        $request->session()->put('logged_in', true);
        $request->session()->save();

        // Return raw keys once so UI can show them to the user immediately.
        return response()->json([
            'success' => true,
            'message' => 'Registration successful!',
            'redirect' => '/map',
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'public_key' => $user->public_key,
                // Raw private key is shown only here right after registration
                'private_key' => $keys['private_key']
            ]
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'private_key' => 'required|string|size:64',
            'remember_me' => 'boolean'
        ]);

        // Compute lookup and find user by single-column lookup
        $lookup = User::computeLookup($request->private_key);
        $user = User::where('private_key', $lookup)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Невалиден частен ключ.'
            ], 401);
        }

        // Update last active
        $user->update(['last_active' => now()]);

        // Create session
        // Log the user in via Laravel Auth so the remember cookie is managed by the framework
        Auth::login($user, $request->remember_me);

        // If remember_me is requested, create a multi-device remember token (selector:validator)
        if ($request->remember_me) {
            $selector = Str::random(24);
            $validator = bin2hex(random_bytes(32));
            $validator_hash = hash('sha256', $validator);

            // store in DB
            $t = UserRememberToken::create([
                'user_id' => $user->id,
                'selector' => $selector,
                'validator_hash' => $validator_hash,
                'device_name' => $request->header('X-Device-Name') ?? null,
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip(),
                'last_used_at' => now(),
                'expires_at' => now()->addDays(30),
            ]);

            // set cookie with selector:validator (base64 encoded)
            $cookieValue = base64_encode($selector.':'.$validator);
            cookie()->queue(cookie()->forever(\App\Http\Middleware\AuthenticateRememberDevice::COOKIE_NAME, $cookieValue));
        }

        // Maintain legacy session keys for backward compatibility
        $request->session()->put('user_id', $user->id);
        $request->session()->put('logged_in', true);
        $request->session()->save();

        return response()->json([
            'success' => true,
            'message' => 'Успешно влизане!',
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'public_key' => $user->public_key
            ]
        ]);
    }

    public function logout(Request $request)
    {
        // If remember-device cookie is present, try to remove its DB entry
        $cookie = $request->cookie(\App\Http\Middleware\AuthenticateRememberDevice::COOKIE_NAME);
        if ($cookie) {
            try {
                $decoded = base64_decode($cookie, true);
                if ($decoded && strpos($decoded, ':') !== false) {
                    [$selector, $validator] = explode(':', $decoded, 2);
                    \App\Models\UserRememberToken::where('selector', $selector)->delete();
                }
            } catch (\Exception $e) {}
            // clear cookie
            cookie()->queue(cookie()->forget(\App\Http\Middleware\AuthenticateRememberDevice::COOKIE_NAME));
        }

        // Ensure Laravel auth is logged out and session flushed
        try { Auth::logout(); } catch (\Exception $e) {}
        $request->session()->flush();
        return response()->json([
            'success' => true,
            'message' => 'Успешно излизане от профила.'
        ]);
    }

    public function profile()
    {
        return view('app');
    }
}
