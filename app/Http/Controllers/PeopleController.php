<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use App\Models\Person;

class PeopleController extends Controller
{
    public function index(Request $request)
    {
        $userId = Session::get('user_id');
        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        $groups = Person::where('user_id', $userId)->get();

        $total = $groups->sum('count');

        // Breakdown by level
        $byLevel = $groups->mapWithKeys(function ($g) {
            return [$g->level => $g->count];
        })->all();

        return response()->json([
            'success' => true,
            'total' => $total,
            'by_level' => $byLevel,
            'groups' => $groups
        ]);
    }
}
