<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Session;

class GameController extends Controller
{


    public function getUserData(Request $request)
    {
        $userId = $request->session()->get('user_id');
        
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
                'private_key' => $user->private_key
            ]
        ]);
    }
}
