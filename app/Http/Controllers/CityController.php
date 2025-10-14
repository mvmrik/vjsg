<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CityObject;
use Illuminate\Support\Facades\Session;

class CityController extends Controller
{
    public function index()
    {
        $userId = Session::get('user_id');
        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        // Clear ready_at for any objects whose ready_at is in the past (they are completed)
        $cleaned = CityObject::where('user_id', $userId)
            ->whereNotNull('ready_at')
            ->where('ready_at', '<=', now())
            ->update(['ready_at' => null]);

        $objects = CityObject::where('user_id', $userId)->get();

        return response()->json([
            'success' => true,
            'cleaned' => $cleaned,
            'objects' => $objects
        ]);
    }

    public function save(Request $request)
    {
        $userId = Session::get('user_id');
        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        $request->validate([
            'objects' => 'required|array',
            'objects.*.parcel_id' => 'required|integer',
            'objects.*.object_type' => 'required|string',
            'objects.*.cells' => 'required|array',
            'objects.*.cells.*.x' => 'required|integer|min:0|max:9',
            'objects.*.cells.*.y' => 'required|integer|min:0|max:9'
        ]);

        // Get the parcel_id from the first object (all objects should be for the same parcel)
        $parcelId = $request->objects[0]['parcel_id'] ?? null;
        if (!$parcelId) {
            return response()->json(['success' => false, 'message' => 'Invalid parcel_id'], 400);
        }

        // Determine which incoming objects have IDs (existing) vs new
        $objects = [];
        $incomingIds = [];
        foreach ($request->objects as $objData) {
            if (($objData['parcel_id'] ?? null) !== $parcelId) continue;
            if (!empty($objData['id'])) {
                $incomingIds[] = $objData['id'];
            }
        }

        // Delete existing objects for this parcel that are not present in incoming payload
        $toDeleteQuery = CityObject::where('user_id', $userId)->where('parcel_id', $parcelId);
        if (!empty($incomingIds)) {
            $toDeleteQuery = $toDeleteQuery->whereNotIn('id', $incomingIds);
        }
        $toDeleteQuery->delete();

        // Process incoming objects: update existing ones, create new ones
        foreach ($request->objects as $objData) {
            if (($objData['parcel_id'] ?? null) !== $parcelId) continue;

            if (!empty($objData['id'])) {
                // Update existing object (but do not reset ready_at)
                $existing = CityObject::where('user_id', $userId)->where('id', $objData['id'])->first();
                if ($existing) {
                    $existing->object_type = $objData['object_type'];
                    $existing->cells = $objData['cells'];
                    $existing->properties = $objData['properties'] ?? [];
                    $existing->save();
                    $objects[] = $existing;
                }
            } else {
                // New object: compute ready_at and create
                $cellsCount = count($objData['cells']);
                $buildSeconds = $cellsCount * 60; // 1 minute per cell
                $readyAt = now()->addSeconds($buildSeconds);

                $objects[] = CityObject::create([
                    'user_id' => $userId,
                    'parcel_id' => $objData['parcel_id'],
                    'object_type' => $objData['object_type'],
                    'cells' => $objData['cells'],
                    'properties' => $objData['properties'] ?? [],
                    'ready_at' => $readyAt
                ]);
            }
        }

        // After creating new objects, clear any expired ready_at values for the user
        CityObject::where('user_id', $userId)
            ->whereNotNull('ready_at')
            ->where('ready_at', '<=', now())
            ->update(['ready_at' => null]);

        // Return the full, updated list of objects for the user so frontend stays consistent
        $cleanedAfter = CityObject::where('user_id', $userId)
            ->whereNotNull('ready_at')
            ->where('ready_at', '<=', now())
            ->update(['ready_at' => null]);

        $allObjects = CityObject::where('user_id', $userId)->get();

        return response()->json([
            'success' => true,
            'message' => 'City saved successfully',
            'cleaned_after_save' => $cleanedAfter,
            'objects' => $allObjects
        ]);
    }
}
