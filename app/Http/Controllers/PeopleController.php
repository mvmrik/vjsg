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
        $userId = Session::get('user_id');
        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        $groups = Person::where('user_id', $userId)->get();

        // Get occupied counts by level
        $occupiedByLevel = OccupiedWorker::where('user_id', $userId)
            ->where('occupied_until', '>', now())
            ->selectRaw('level, SUM(count) as occupied_count')
            ->groupBy('level')
            ->pluck('occupied_count', 'level')
            ->all();

        $total = $groups->sum('count');
        $totalOccupied = array_sum($occupiedByLevel);
        $totalFree = $total - $totalOccupied;

        // Breakdown by level (free counts)
        $byLevel = $groups->mapWithKeys(function ($g) use ($occupiedByLevel) {
            $occupied = $occupiedByLevel[$g->level] ?? 0;
            return [$g->level => $g->count - $occupied];
        })->all();

        return response()->json([
            'success' => true,
            'total' => $totalFree,
            'by_level' => $byLevel,
            'groups' => $groups
        ]);
    }
}
