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
        try {
            $userId = $request->session()->get('user_id');
            if (!$userId) {
                return response()->json(['success' => false, 'message' => 'Not authenticated'], 401);
            }

            try {
                $request->validate([
                    'lat' => 'required|numeric|between:-90,90',
                    'lng' => 'required|numeric|between:-180,180',
                    'confirmed' => 'boolean'
                ]);
            } catch (\Illuminate\Validation\ValidationException $e) {
                \Log::error('Validation failed for parcel claim', [
                    'errors' => $e->errors(),
                    'data' => $request->all()
                ]);
                return response()->json(['success' => false, 'message' => 'Invalid coordinates'], 400);
            }

            $lat = $request->lat;
            $lng = $request->lng;
            $confirmed = $request->boolean('confirmed', false);

            \Log::info("Parcel claim attempt", [
                'user_id' => $userId,
                'lat' => $lat,
                'lng' => $lng,
                'confirmed' => $confirmed
            ]);

            $price = 1000; // Price for claiming a parcel

            $user = User::find($userId);
            if (!$user) {
                \Log::error("User not found for parcel claim", ['user_id' => $userId]);
                return response()->json(['success' => false, 'message' => 'User not found'], 400);
            }

            \Log::info("User balance check", [
                'user_id' => $userId,
                'balance' => $user->balance,
                'price' => $price
            ]);

            // If not confirmed, check if user has enough balance and return confirm dialog
            if (!$confirmed) {
                if (!isset($user->balance) || $user->balance < $price) {
                    \Log::info("Insufficient balance for initial check", [
                        'user_id' => $userId,
                        'balance' => $user->balance,
                        'price' => $price
                    ]);
                    return response()->json(['success' => false, 'message' => 'Insufficient balance to claim parcel'], 400);
                }
                return response()->json([
                    'success' => true,
                    'needsConfirm' => true,
                    'price' => $price,
                    'message' => "Claiming this parcel costs {$price} coins. Confirm?"
                ]);
            }

            // Double-check balance before proceeding (security check)
            if (!isset($user->balance) || $user->balance < $price) {
                \Log::info("Insufficient balance for confirmed claim", [
                    'user_id' => $userId,
                    'balance' => $user->balance,
                    'price' => $price
                ]);
                return response()->json(['success' => false, 'message' => 'Insufficient balance'], 400);
            }

            // Deduct balance
            $user->balance -= $price;
            $user->save();

            // Check if parcel already exists within ~250m (half of 500m)
            $lng_delta = 0.00225 / cos(deg2rad($lat)); // Adjust for longitude
            $existing = Parcel::whereRaw("ABS(lat - ?) < 0.00225", [$lat]) // ~250m lat difference
                ->whereRaw("ABS(lng - ?) < ?", [$lng, $lng_delta]) // ~250m lng difference
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
                            // Find the closest adjacent parcel (within ~550m range to be safe)
                $lng_delta_adj = 0.005 / cos(deg2rad($lat)); // Adjust for longitude
                $adjacent = Parcel::where('user_id', $userId)
                    ->whereRaw("ABS(lat - ?) <= 0.005", [$lat]) // ~550m
                    ->whereRaw("ABS(lng - ?) <= ?", [$lng, $lng_delta_adj]) // ~550m
                    ->selectRaw("*, ABS(lat - ?) + ABS(lng - ?) as distance", [$lat, $lng])
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
        } catch (\Exception $e) {
            \Log::error('Unexpected error in parcel claim', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->session()->get('user_id'),
                'data' => $request->all()
            ]);
            return response()->json(['success' => false, 'message' => 'Internal server error'], 500);
        }
    }
}
