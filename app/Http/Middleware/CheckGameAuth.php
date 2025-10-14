<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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
        
        if (!$request->session()->get('logged_in') || !$request->session()->get('user_id')) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Not authenticated'], 401);
            }
            return response()->json(['success' => false, 'message' => 'Not authenticated'], 401);
        }
        
        return $next($request);
    }
}
