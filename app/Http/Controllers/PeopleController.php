<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use App\Models\Person;
use App\Models\OccupiedWorker;

class PeopleController extends Controller
{
    public function index(Request $request)
    {
        $userId = Session::get('user_id') ?: \Illuminate\Support\Facades\Auth::id();
        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        $groups = Person::where('user_id', $userId)->get();

        // Under the simplified model, `people` already represents free workers only.
        // So simply return counts from the `people` table directly.
        $totalFree = $groups->sum('count');

        // Breakdown by level (free counts)
        $byLevel = $groups->mapWithKeys(function ($g) {
            return [$g->level => intval($g->count)];
        })->all();

        return response()->json([
            'success' => true,
            'total' => $totalFree,
            'by_level' => $byLevel,
            'groups' => $groups
        ]);
    }
}
