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

            // Compute hospital effect for new level-threshold mortality:
            // hospital effect = sum(hospital.level) + sum(tools.level in hospitals) / 10
            // threshold_level = 5 + round(hospital_effect, half-up)
            // expectedMortality (frontend) = percent of people whose level > threshold_level
            $hospitalLevelSum = (int) DB::table('city_objects')
                ->where('user_id', $userId)
                ->where('object_type', 'hospital')
                ->sum('level');

            $hospitalToolSum = (int) DB::table('tools')
                ->join('city_objects', 'tools.object_id', '=', 'city_objects.id')
                ->where('city_objects.object_type', 'hospital')
                ->where('city_objects.user_id', $userId)
                ->sum('tools.durability');

            $hospitalEffect = $hospitalLevelSum + ($hospitalToolSum / 10.0);
            $roundedEffect = intval(round($hospitalEffect, 0, PHP_ROUND_HALF_UP));
            $thresholdLevel = 5 + $roundedEffect;

            // Count people above threshold
            $above = (int) DB::table('people')
                ->where('user_id', $userId)
                ->where('level', '>', $thresholdLevel)
                ->sum('count');

            // Count currently occupied workers (active productions)
            // Sum all occupied_workers for the user. If an occupied record exists it counts as occupied.
            $occupiedActive = (int) DB::table('occupied_workers')
                ->where('user_id', $userId)
                ->sum('count');

            $expectedMortality = ($population > 0) ? ($above / $population) * 100 : 0;

            // Food sufficiency calculation
            // Get available food (tool_type_id = 3)
            $availableFood = (int) DB::table('inventories')
                ->where('user_id', $userId)
                ->where('tool_type_id', 3)
                ->value('count') ?: 0;

            // Calculate food needed for next level-up (sum of next level for each person)
            $foodNeeded = 0;
            $peopleRows = DB::table('people')
                ->where('user_id', $userId)
                ->select('level', 'count')
                ->get();
            
            foreach ($peopleRows as $row) {
                $level = intval($row->level);
                $count = intval($row->count);
                $nextLevel = $level + 1;
                $foodNeeded += $nextLevel * $count;
            }

            // Calculate food sufficiency as percentage
            $foodSufficiency = ($foodNeeded > 0) ? ($availableFood / $foodNeeded) * 100 : 100;

            // Tools decay rate calculation (workshop-based)
            $toolsDecayRate = \App\Services\ObjectLevelService::getToolsDecayRate($userId);
            
            // Get workshop level for display
            $workshopData = \App\Services\ObjectLevelService::getCachedAggregateRow($userId, 'workshop');
            $workshopLevel = $workshopData ? intval($workshopData['total_level']) : 0;

            return response()->json([
                'success' => true,
                'parcels' => $parcels,
                'objects' => $objects,
                'population' => $population,
                // keep hospital_capacity for backwards compatibility but provide breakdown
                'hospital_capacity' => $hospitalLevelSum + $hospitalToolSum,
                'expectedMortality' => round($expectedMortality, 4),
                'death_threshold_level' => $thresholdLevel,
                'occupied' => $occupiedActive,
                'food_available' => $availableFood,
                'food_needed' => $foodNeeded,
                'food_sufficiency' => round($foodSufficiency, 2),
                'tools_decay_rate' => round($toolsDecayRate, 2),
                'workshop_level' => $workshopLevel
            ]);
        } catch (\Exception $e) {
            \Log::error('StatsController@index error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Server error'], 500);
        }
    }
}
