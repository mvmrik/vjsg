<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Parcel;
use App\Models\User;

class ParcelsController extends Controller
{
    // Return all parcels (could be limited / paged later)
    public function index(Request $request)
    {
        $parcels = Parcel::with('user:id,username')->get();
        return response()->json(['success' => true, 'parcels' => $parcels]);
    }

    // Claim a parcel for the current session user
    public function claim(Request $request)
    {
        $userId = $request->session()->get('user_id');
        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        $lat = $request->lat;
        $lng = $request->lng;

        // Check if parcel already exists within ~5m (rough approximation)
        $existing = Parcel::whereRaw("ABS(lat - ?) < 0.00005", [$lat]) // ~5m lat difference
            ->whereRaw("ABS(lng - ?) < 0.00005", [$lng]) // ~5m lng difference at equator
            ->first();
        if ($existing) {
            return response()->json(['success' => false, 'message' => 'This area is already claimed'], 409);
        }

        // Allow claim only if adjacent to an existing parcel of the user OR user has no parcels yet
        $userHasAny = Parcel::where('user_id', $userId)->exists();

        $allowed = false;
        $cityX = 0;
        $cityY = 0;

        if (!$userHasAny) {
            $allowed = true;
            // First parcel at (0,0)
            \Log::info("First parcel for user {$userId}");
        } else {
            // Find the closest adjacent parcel
            $adjacent = Parcel::where('user_id', $userId)
                ->selectRaw("*, ABS(lat - ?) + ABS(lng - ?) as distance", [$lat, $lng])
                ->havingRaw("ABS(lat - ?) <= 0.00015", [$lat])
                ->havingRaw("ABS(lng - ?) <= 0.00015", [$lng])
                ->orderBy('distance')
                ->first();

            if ($adjacent) {
                $allowed = true;
                // Calculate city coordinates based on direction from adjacent parcel
                $latDiff = $lat - $adjacent->lat;
                $lngDiff = $lng - $adjacent->lng;

                // Convert to approximate meters
                $latMeters = $latDiff * 111000;
                $lngMeters = $lngDiff * 111000 * cos(deg2rad($adjacent->lat));

                \Log::info("Lat diff: {$latDiff}, Lng diff: {$lngDiff}, Lat meters: {$latMeters}, Lng meters: {$lngMeters}");

                // Determine direction - prioritize the axis with larger absolute difference
                if (abs($latMeters) > abs($lngMeters)) {
                    // North/South dominates
                    $cityX = $adjacent->city_x;
                    $cityY = $adjacent->city_y + ($latMeters > 0 ? 1 : -1);
                    \Log::info("Direction: N/S, new city: ({$cityX}, {$cityY})");
                } else {
                    // East/West dominates (or equal)
                    $cityX = $adjacent->city_x + ($lngMeters > 0 ? 1 : -1);
                    $cityY = $adjacent->city_y;
                    \Log::info("Direction: E/W, new city: ({$cityX}, {$cityY})");
                }

                // Check if position is already taken
                $existingCity = Parcel::where('user_id', $userId)
                    ->where('city_x', $cityX)
                    ->where('city_y', $cityY)
                    ->first();
                if ($existingCity) {
                    return response()->json(['success' => false, 'message' => 'City position already occupied'], 409);
                }

                \Log::info("Adjacent parcel found: {$adjacent->id} at ({$adjacent->city_x}, {$adjacent->city_y}) -> new position ({$cityX}, {$cityY})");
            } else {
                \Log::info("No adjacent parcel found for user {$userId} at ({$lat}, {$lng})");
            }
        }

        if (!$allowed) {
            return response()->json(['success' => false, 'message' => 'Parcel must be adjacent to your existing parcel'], 403);
        }

        $parcel = Parcel::create([
            'user_id' => $userId,
            'lat' => $lat,
            'lng' => $lng,
            'city_x' => $cityX,
            'city_y' => $cityY,
            'type' => $request->input('type', null)
        ]);

        return response()->json(['success' => true, 'parcel' => $parcel]);
    }
}
