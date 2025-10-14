<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

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
        
        $user = User::create([
            'username' => $request->username,
            'public_key' => $keys['public_key'],
            'private_key' => $keys['private_key'],
            'last_active' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Registration successful! Please save your keys safely.',
            'user' => [
                'username' => $user->username,
                'public_key' => $user->public_key,
                'private_key' => $user->private_key,
            ]
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'private_key' => 'required|string|size:64',
        ]);

        $user = User::where('private_key', $request->private_key)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Невалиден частен ключ.'
            ], 401);
        }

        // Update last active
        $user->update(['last_active' => now()]);

        // Create session
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
        $request->session()->flush();
        return response()->json([
            'success' => true,
            'message' => 'Успешно излизане от профила.'
        ]);
    }

    public function profile()
    {
        $userId = Session::get('user_id');
        
        if (!$userId) {
            return redirect()->route('homepage')->with('error', 'Please log in first.');
        }

        $user = User::find($userId);
        
        if (!$user) {
            Session::flush();
            return redirect()->route('homepage')->with('error', 'User not found.');
        }

        return view('game.profile', compact('user'));
    }
}
