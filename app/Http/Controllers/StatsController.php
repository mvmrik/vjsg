<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Parcel;
use App\Models\CityObject;

class StatsController extends Controller
{
    /**
     * Return per-user stats used by the frontend settings page.
     * - parcels: number of parcels owned
     * - objects: number of city objects owned
     * - population: total people count
     * - hospital_capacity: sum of hospital levels + hospital tools
     * - expectedMortality: percentage (0-100) of expected immediate mortality based on deficit
     */
    public function index(Request $request)
    {
        // Resolve user id from middleware attributes or session
        $userId = $request->attributes->get('game_user_id') ?: $request->session()->get('user_id') ?: \Illuminate\Support\Facades\Auth::id();

        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        try {
            $parcels = Parcel::where('user_id', $userId)->count();
            $objects = CityObject::where('user_id', $userId)->count();

            $population = (int) DB::table('people')->where('user_id', $userId)->sum('count');

            // Hospital capacity = sum(hospital.level) + sum(tools.level for tools attached to hospitals)
            $hospitalLevelSum = (int) DB::table('city_objects')
                ->where('user_id', $userId)
                ->where('object_type', 'hospital')
                ->sum('level');

            $hospitalToolSum = (int) DB::table('tools')
                ->join('city_objects', 'tools.object_id', '=', 'city_objects.id')
                ->where('city_objects.object_type', 'hospital')
                ->where('city_objects.user_id', $userId)
                ->sum('tools.level');

            $hospitalCapacity = $hospitalLevelSum + $hospitalToolSum;

            $deficit = max(0, $population - $hospitalCapacity);

            // Compute expected immediate mortality similar to population:births but scaled down
            $maxRemovable = intval(floor($population * 0.8));
            $originalToRemove = min($deficit, $maxRemovable);
            // Scale mortality down by factor 10 to match daily tick logic
            $toRemove = intval(floor($originalToRemove / 10));

            // Enforce minimum mortality floor of 5% (rounded up), but do not exceed originalToRemove
            $minPercent = 0.05; // 5%
            $minRemovable = intval(ceil($population * $minPercent));
            if ($minRemovable < 1) $minRemovable = 1;
            if ($originalToRemove > 0 && $toRemove < $minRemovable) {
                $toRemove = min($minRemovable, $originalToRemove);
            }

            $expectedMortality = ($population > 0) ? ($toRemove / max(1, $population)) * 100 : 0;
            $expectedMortality = min(80, $expectedMortality);

            return response()->json([
                'success' => true,
                'parcels' => $parcels,
                'objects' => $objects,
                'population' => $population,
                'hospital_capacity' => $hospitalCapacity,
                'expectedMortality' => round($expectedMortality, 4)
            ]);
        } catch (\Exception $e) {
            \Log::error('StatsController@index error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Server error'], 500);
        }
    }
}
