<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class CheckGameAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Start session if not already started
        if (!$request->session()->isStarted()) {
            $request->session()->start();
        }
        
        // Support authentication either via our legacy session flags or via
        // Laravel's Auth system (which supports remember-me cookies).
        if (!$request->session()->get('logged_in') || !$request->session()->get('user_id')) {
            // If Laravel's Auth has an authenticated user (including viaRemember),
            // populate the session keys so older code paths continue to work.
            if (Auth::check()) {
                $user = Auth::user();
                $request->session()->put('user_id', $user->id);
                $request->session()->put('logged_in', true);
            } else {
                if ($request->expectsJson()) {
                    return response()->json(['success' => false, 'message' => 'Not authenticated'], 401);
                }
                return response()->json(['success' => false, 'message' => 'Not authenticated'], 401);
            }
        }

        // Set locale based on user preference
        $userId = $request->session()->get('user_id');
        $user = User::find($userId);
        if ($user && $user->locale) {
            App::setLocale($user->locale);
        } else {
            App::setLocale('en'); // default
        }
        
        return $next($request);
    }
}
