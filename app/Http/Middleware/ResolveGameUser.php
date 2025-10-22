<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class ResolveGameUser
{
    /**
     * Attempt to resolve a user id for the current request.
     * It prefers existing session 'user_id', otherwise falls back to Auth::id().
     * If Auth::check() and session keys are missing, populate them so legacy
     * code that uses session('user_id') continues to work.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Ensure session is started
        if (!$request->session()->isStarted()) {
            $request->session()->start();
        }

        $userId = $request->session()->get('user_id');

        if (!$userId && Auth::check()) {
            $user = Auth::user();
            $userId = $user->id;
            // populate legacy session flags for backward compatibility
            $request->session()->put('user_id', $userId);
            $request->session()->put('logged_in', true);
        }

        // Attach resolved id to the request attributes for controllers to use
        if ($userId) {
            $request->attributes->set('game_user_id', $userId);
        }

        return $next($request);
    }
}
