<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\UserRememberToken;
use Illuminate\Support\Facades\Auth;

class AuthenticateRememberDevice
{
    // Cookie name used for remember-multi
    public const COOKIE_NAME = 'remember_device';

    public function handle(Request $request, Closure $next): Response
    {
        // If user already authenticated, continue
        if (Auth::check()) {
            return $next($request);
        }

        $cookie = $request->cookie(self::COOKIE_NAME);
        if (!$cookie) {
            return $next($request);
        }

        // cookie format: base64(selector:validator) or selector|validator plain
        $value = null;
        try {
            $decoded = base64_decode($cookie, true);
            if ($decoded !== false && strpos($decoded, ':') !== false) {
                $value = $decoded;
            } elseif (strpos($cookie, ':') !== false) {
                $value = $cookie;
            } elseif (strpos($cookie, '|') !== false) {
                $value = str_replace('|', ':', $cookie);
            }
        } catch (\Exception $e) {
            // ignore malformed cookie
        }

        if (!$value) {
            return $next($request);
        }

        [$selector, $validator] = explode(':', $value, 2) + [null, null];
        if (!$selector || !$validator) {
            return $next($request);
        }

        $record = UserRememberToken::where('selector', $selector)->first();
        if (!$record) {
            // If there is no DB record for this selector, clear the cookie to avoid loops
            try { cookie()->queue(cookie()->forget(self::COOKIE_NAME)); } catch (\Exception $e) {}
            return $next($request);
        }

        // Validate
        $hash = hash('sha256', $validator);
        if (!hash_equals($record->validator_hash, $hash)) {
            // Possible stolen credential — remove the record and clear cookie
            try { $record->delete(); } catch (\Exception $e) {}
            try { cookie()->queue(cookie()->forget(self::COOKIE_NAME)); } catch (\Exception $e) {}
            return $next($request);
        }

        // Check expiry
        if ($record->expires_at && now()->greaterThan($record->expires_at)) {
            try { $record->delete(); } catch (\Exception $e) {}
            try { cookie()->queue(cookie()->forget(self::COOKIE_NAME)); } catch (\Exception $e) {}
            return $next($request);
        }

        // All good: log the user in
        // Log the user in and ensure the session contains legacy keys used by the app UI
        Auth::loginUsingId($record->user_id);
        try {
            if (!$request->session()->isStarted()) {
                $request->session()->start();
            }
            $request->session()->put('user_id', $record->user_id);
            $request->session()->put('logged_in', true);
            $request->session()->save();
        } catch (\Exception $e) {
            // ignore session write failures, user will still be authenticated via Auth
        }

        // Update last_used_at
        $record->last_used_at = now();
        $record->save();

        // Refresh the cookie with the same selector:validator to extend client lifetime
        try {
            $cookieName = self::COOKIE_NAME;
            $raw = $selector.':'.$validator;
            cookie()->queue(cookie()->forever($cookieName, base64_encode($raw)));
        } catch (\Exception $e) {
            // ignore cookie queue errors
        }

        return $next($request);
    }
}
